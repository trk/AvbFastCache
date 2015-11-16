<?php if(!defined("PROCESSWIRE")) die();

/**
 * Class AvbFastCache
 *
 * @author          : İskender TOTOĞLU, @ukyo (community), @trk (Github)
 * @website         : http://altivebir.com
 * @projectWebsite  : https://github.com/trk/AvbFastCache
 */
class AvbFastCache extends WireData implements Module, ConfigurableModule {

    const phpFastCacheVersion = '3.0.18';
    const phpFastCacheLibraryPath = "/Libraries/phpfastcache/3.0.0/phpfastcache.php";

    protected static $moduleCachePath;
    protected static $fullCachePath;

    /**
     * AvbFastCache Module Info
     *
     * @return array
     */
    public static function getModuleInfo() {
        return array(
            'title' => 'AvbFastCache',
            'summary' => __('Allow to use "phpFastCache" with ProcessWire'),
            'version' => 15,
            'author' => 'İskender TOTOĞLU | @ukyo(community), @trk (Github), http://altivebir.com',
            'icon' => 'clock-o',
            'href' => 'https://github.com/trk/AvbFastCache',
            'singular' => true,
            'autoload' => true,
            'requires' => 'ProcessWire>=2.6.1'
        );
    }

    /**
     * Default AvbFastCache Modules Configurations
     *
     * @return array
     */
    static public function getDefaultData() {
        return array(
            'updateModified' => 1, // Activate modified date update for sorted pages
            'updateAllSortedPagesModified' => 0, // Update all posted ids modified (parent_id, moved_id, ids)
            'updateSortedAndParentModified' => 1, // Update just moved and parent page modified (parent_id, moved_id)
            'updateSortedAndParentsModified' => 0, // Update moved and all parents of moved page modified (moved_id, wire('pages')->get(moved_id)->parents)
            'updateSortedParentModified' => 0, // Update only moved parent page modified date ? This will update only (parent_id).
            'storage' => 'auto',
            'expire' => 1,
            'path' => 'avb.fast.cache',
            'securityKey' => 'auto',
            'fallback' => 'files'
        );
    }

    public function __construct() {
        foreach(self::getDefaultData() as $key => $value) $this->$key = $value;
    }


    /**
     * Initialize the module
     *
     */
    public function init() {
        $this->addHookAfter('ProcessPageSort::execute', $this, '_setPageModified');

        // Create paths and check paths are ok ?
        self::$moduleCachePath = wire('config')->paths->assets . $this->className . DIRECTORY_SEPARATOR;
        self::$fullCachePath =  self::$moduleCachePath . $this->path;
        if(!file_exists(self::$fullCachePath)) $this->___install();

        // Call phpFastCache Library
        if(!class_exists('phpFastCache')) {
            require_once(dirname(__FILE__) . self::phpFastCacheLibraryPath);
        }
    }

    /**
     * Get configs from module config panel
     * @return array
     */
    public function getConfig() {
        return array(
            "storage"   =>  $this->storage,
            "default_chmod" => 0777,
            "htaccess"      => true,
            "path"      =>  self::$fullCachePath,
            "securityKey"   =>  $this->securityKey,
            "fallback"  => $this->fallback,
        );
    }

    /**
     * Get last modified page modified date from given $id, $parent_id, $templates_id or from all
     *
     * @param bool $id
     * @param string $template
     * @return mixed|string
     */
    public function getLastModified($id=FALSE, $template=NULL, $useLanguageID=FALSE) {
        if(!is_null($id)) {
            if(is_bool($id) != true) {
                $where = (!is_null($template) && $template != "") ? " INNER JOIN templates ON pages.templates_id = templates.id" : "";
                $where .= " WHERE pages.id={$id} OR pages.parent_id={$id}";
                $where .= (!is_null($template) && $template != "") ? " AND templates.name='{$template}'" : "";
                $qry = "SELECT UNIX_TIMESTAMP(MAX(modified)) as modified FROM pages {$where}";
                $results = wire('db')->query($qry);
                if($results->num_rows > 0) {
                    $result = $results->fetch_assoc();
                    if(wire('config')->debug) $this->message($qry . " Result is : {$result['modified']}", Notice::log);
                    if($useLanguageID === TRUE) return $result['modified'] . wire('user')->language->id;
                    return $result['modified'];
                }
            }
        }
        return "";
    }

    /**
     * Hook for Sorted Pages, update sorted pages modified date
     *
     * @param $event
     */
    protected function _setPageModified($event) {
        // If update modified active execute this !
        if($this->updateModified) {
            // Get Posted Variables
            $parent_id = $this->input->post->parent_id;
            $move_id = $this->input->post->id;
            $ids = explode(',', $this->input->post->sort);

            $updateIds = array();

            // Set sort ids
            if($this->updateAllSortedPagesModified) {
                foreach($ids as $sort => $id) $updateIds[(int) $sort] = (int) $id;
            }

            // Set moved_id
            if($this->updateSortedAndParentModified || $this->updateAllSortedPagesModified || $this->updateSortedAndParentsModified) {
                if(!in_array($move_id, $updateIds)) $updateIds[] = $move_id;
            }

            // Set moved_id parents
            if($this->updateSortedAndParentsModified) {
                // Loop parents and check for ids exist ?
                $parents = wire('pages')->get($move_id)->parents;
                if($parents) {
                    foreach($parents as $parent) if(!in_array($parent->id, $updateIds)) $updateIds[] = $parent->id;
                }
            }

            // Set moved_id parent
            if($this->updateSortedParentModified || $this->updateAllSortedPagesModified || $this->updateSortedAndParentModified) {
                // If parent_id not in {$updateIds} set parent id
                if(!in_array($parent_id, $updateIds)) $updateIds[] = $parent_id;
            }

            // Update modified dates
            if(!empty($updateIds)) {
                $countIds = count($updateIds);
                $x=1;
                $selector = "";
                // Prepare Update Selector
                foreach($updateIds as $id) {
                    $y=$x++;
                    $separator = ($y!=$countIds) ? " OR " : "";
                    $selector .= "pages.id={$id}" . $separator;
                }

                wire('db')->query("UPDATE pages SET pages.modified=NOW() WHERE {$selector}");
                if(wire('config')->debug) $this->message("HookAfter::ProcessPageSort::execute, modified date updated for given selector : ({$selector})", Notice::log);
            }
        }
    }

    /**
     * Trace helper
     *
     * @param $var
     * @param bool $return
     */
    public function trace($var, $return=false) {
        echo '<pre>' . print_r($var, $return) .'</pre>';
    }

    /**
     * Configure the AvbFastCache Module
     */
    public static function getModuleConfigInputfields(array $data) {

        $fields = new InputfieldWrapper();
        $modules = wire('modules');
        $data = array_merge(self::getDefaultData(), $data);

        // Cache Expire Time
        $fieldName = "expire";
        $f = $modules->get('InputfieldInteger');
        $f->attr('name', $fieldName);
        $f->attr('value', $data[$fieldName]);
        $f->attr('min', 0);
        $f->attr('type', 'number');
        $f->label = __("Cache Expire Time");
        $f->notes = __("For example: 0 = unlimited time, 1 = 1 second, 60 = 1 minute, 600 = 10 minutes, 3600 = 1 hour, 86400 = 1 day, 604800 = 1 week, 2419200 = 1 month.");
        $f->required = true;
        $fields->add($f);

        // Update Sorted Pages Wrapper
        $wrapperSortedPages = $modules->get('InputfieldFieldset');
        $wrapperSortedPages->label = __('Update sorted pages modified dates ?');


        // Option : Activate, Update modified dates for sorted pages
        $fieldName = "updateModified";
        $value = empty($data[$fieldName]) ? '' : 'checked';
        $f = $modules->get('InputfieldCheckbox');
        $f->label = __("Update sorted pages modified dates ? If not active, hook for ProcessPageSort::execute won't execute !");
        $f->attr('name', $fieldName);
        $f->attr('checked',$value );
        $wrapperSortedPages->add($f);

        // Option : Update all given ids by post
        $fieldName = "updateAllSortedPagesModified";
        $value = empty($data[$fieldName]) ? '' : 'checked';
        $f = $modules->get('InputfieldCheckbox');
        $f->label = __("Update all posted ids modified dates ? this will update (parent_id, moved_id, ids).");
        $f->attr('name', $fieldName);
        $f->attr('checked',$value );
        $wrapperSortedPages->add($f);

        // Option : Update just moved and parent
        $fieldName = "updateSortedAndParentModified";
        $value = empty($data[$fieldName]) ? '' : 'checked';
        $f = $modules->get('InputfieldCheckbox');
        $f->label = __("Update just moved and parent page modified dates ? This will update (parent_id, moved_id).");
        $f->attr('name', $fieldName);
        $f->attr('checked',$value );
        $wrapperSortedPages->add($f);

        // Option : Update moved and all parents
        $fieldName = "updateSortedAndParentsModified";
        $value = empty($data[$fieldName]) ? '' : 'checked';
        $f = $modules->get('InputfieldCheckbox');
        $f->label = __("Update moved and all parents of moved page modified dates ? This will update (moved_id, wire('pages')->get(moved_id)->parents).");
        $f->attr('name', $fieldName);
        $f->attr('checked',$value );
        $wrapperSortedPages->add($f);

        // Option : Update only moved parent
        $fieldName = "updateSortedParentModified";
        $value = empty($data[$fieldName]) ? '' : 'checked';
        $f = $modules->get('InputfieldCheckbox');
        $f->label = __("Update only moved parent page modified date ? This will update only (parent_id).");
        $f->attr('name', $fieldName);
        $f->attr('checked',$value );
        $wrapperSortedPages->add($f);

        $fields->add($wrapperSortedPages);

        // Cache Storage Type
        $fieldName = "storage";
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', $fieldName);
        $f->label = __("Storage");
        $f->description = __("auto, files, sqlite, apc, cookie, memcache, memcached, predis, redis, wincache, xcache, ssdb");
        $f->required = true;
        $f->addOptions(array(
            'auto' => 'auto',
            'files' => 'files',
            'sqlite' => 'sqlite',
            'apc' => 'apc',
            'cookie' => 'cookie',
            'memcache' => 'memcache',
            'memcached' => 'memcached',
            'predis' => 'predis',
            'redis' => 'redis',
            'wincache' => 'wincache',
            'xcache' => 'xcache',
            'ssdb' => 'ssdb'
        ));
        $f->value = $data[$fieldName];
        $fields->add($f);

        // Cache Path
        $fieldName = "path";
        $f = $modules->get('InputfieldText');
        $f->attr('name', $fieldName);
        $f->attr('value', $data[$fieldName]);
        $f->label = __("Cache Path");
        $f->description = __("You can write a cache path with out start and end a directory separator '/'");
        $f->notes = sprintf(__("We are putting automatic separator to your cache path, **example: %s** <- this will be -> **%s**"), $data[$fieldName], self::$fullCachePath);
        $f->required = true;
        $fields->add($f);

        // Security Key
        $fieldName = "securityKey";
        $f = $modules->get('InputfieldText');
        $f->attr('name', $fieldName);
        $f->attr('value', $data[$fieldName]);
        $f->label = __("Security Key");
        $f->description = __("auto will use domain name, set it to 1 string if you use alias domain name");
        $f->required = true;
        $fields->add($f);

        // Fallback
        $fieldName = "fallback";
        $f = $modules->get('InputfieldText');
        $f->attr('name', $fieldName);
        $f->attr('value', $data[$fieldName]);
        $f->label = __("Fallback");
        $f->required = true;
        $fields->add($f);

        // Cache Info and Clear Cache Button
        $filesRecords       = self::getNumberOfFilesAndSizes();

        if(wire('input')->get('clearCache') == 'clearFilesCache') {
            $avbfastcache = new AvbFastCache();
            $cache = phpFastCache($data['storage'], $avbfastcache->getConfig());
            $cache->clean();

            wire()->message(sprintf(__('Total : %s files deleted and these deleted files size %s.'), $filesRecords['nbfiles'], $filesRecords['bytestotal']), Notice::log);
            wire('session')->redirect(wire('page')->httpUrl.'edit?name=' . wire('input')->get('name'));
        }

        $f =$modules->get("InputfieldMarkup");
        $f->label = __("File Cache Info");
        $f->icon = 'folder';
        $f->description = sprintf(__('Total cached files: %s, Used space: %s'), $filesRecords['nbfiles'], $filesRecords['bytestotal']);

        // Hidden Form Field
        $h = $modules->get("InputfieldHidden");
        $h->name = "clearFilesCache";
        $h->value = "clearFilesCache";
        $f->add($h);

        $fb = $modules->get('InputfieldButton');
        $fb->icon = "trash";
        $fb->name = 'clearFilesCache';
        $fb->value = __('Clear Files Cache');
        $fb->href = 'edit?name='.wire('input')->get('name').'&clearCache=clearFilesCache';
        $f->add($fb);

        $fields->add($f);

        return $fields;
    }

    /**
     * Install Module Create Cache Path
     */
    protected function ___install() {
        if(!is_dir(self::$moduleCachePath)) @mkdir(self::$moduleCachePath, 0755);
        if(!is_dir(self::$fullCachePath)) @mkdir(self::$fullCachePath, 0777);
    }

    /**
     * Uninstall Module and Delete Cache Path
     */
    protected function ___uninstall() {
        $this->removeCacheDir(self::$fullCachePath);
    }

    /**
     * Get Number Of Cached Files and Sizes
     *
     * @param null $path
     * @return array
     */
    protected static function getNumberOfFilesAndSizes($path=null) {
        if(is_null($path)) $path = self::$fullCachePath;

        if($path != "") {
            $items = new RecursiveDirectoryIterator($path);

            $bytestotal=0;
            $nbfiles=0;
            foreach (new RecursiveIteratorIterator($items) as $filename => $file) {
                $filesize = $file->getSize();
                $bytestotal += $filesize;
                $nbfiles++;
            }

            $label  = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
            for ($i = 0; $bytestotal >= 1024 AND $i < (count($label) - 1); $bytestotal /= 1024, $i++);

            return array(
                'nbfiles' => number_format($nbfiles),
                'bytestotal' => round($bytestotal) . ' ' .$label[$i]
            );
        }
    }

    /**
     * Remove Cache Dir
     *
     * @param $dir
     */
    protected function removeCacheDir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir")
                        $this->removeCacheDir($dir."/".$object);
                    else unlink   ($dir."/".$object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}
