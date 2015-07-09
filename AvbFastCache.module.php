<?php if(!defined("PROCESSWIRE")) die();

/**
 * Class AvbFastCache
 *
 * @author          : İskender TOTOĞLU, @ukyo (community), @trk (Github)
 * @website         : http://altivebir.com
 * @projectWebsite  : https://github.com/trk/AvbFastCache
 */
class AvbFastCache extends WireData implements Module, ConfigurableModule {

    const phpFastCacheVersion = '3.0.6';
    const phpFastCacheLibraryPath = "/Libraries/phpfastcache/3.0.0/phpfastcache.php";

    protected $moduleCachePath;
    protected $fullCachePath;

    /**
     * AvbFastCache Module Info
     *
     * @return array
     */
    public static function getModuleInfo() {
        return array(
            'title' => 'AvbFastCache',
            'summary' => __('Allow to use "phpFastCache" with ProcessWire'),
            'version' => 9,
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
        $this->moduleCachePath = wire('config')->paths->assets . $this->className . DIRECTORY_SEPARATOR;
        $this->fullCachePath =  $this->moduleCachePath . $this->path;
        if(!file_exists($this->fullCachePath)) $this->___install();

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
            "path"      =>  $this->fullCachePath,
            "securityKey"   =>  $this->securityKey,
            "fallback"  => $this->fallback,
        );
    }

    /**
     * Get last modified page modified date from given $id, $parent_id, $templates_id or from all
     *
     * @param bool $parent_id
     * @param string $template
     * @return mixed|string
     */
    public function getLastModified($parent_id=FALSE, $template=NULL, $useLanguageID=FALSE) {
        if(!is_null($parent_id)) {
            if(is_bool($parent_id) != true) {
                $where = (!is_null($template) && $template != "") ? " INNER JOIN templates ON pages.templates_id = templates.id" : "";
                $where .= " WHERE parent_id={$parent_id}";
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
        // Get Variables
        $parent_id = $this->input->post->parent_id;
        $move_id = $this->input->post->id;
        $ids = str_replace(',', '|', $this->input->post->sort);

        $ids .= $parent_id;
        if(!strpos($ids, $move_id)) $ids .= '|'.$move_id; // This id included inside "$ids", but need to be sure !
        // Set selector for ids
        $ids = "id={$ids}";
        if(wire('config')->debug) $this->message("Sorted pages ids selector : {$ids}", Notice::log);
        // Loop and save sorted pages by given {$ids}
        foreach(wire('pages')->find($ids) as $page) {
            $page->save();
            if(wire('config')->debug) $this->message("Page modified date updated for sorted page, sorted page is :: id={$page->id} | title={$page->title}.", Notice::log);
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
    public function getModuleConfigInputfields(array $data) {

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

        // Cache Storage Type
        $fieldName = "storage";
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', $fieldName);
        $f->label = __("Storage");
        $f->description = __("auto, files, sqlite, apc, cookie, memcache, memcached, predis, redis, wincache, xcache");
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
            'xcache' => 'xcache'
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
        $f->notes = sprintf(__("We are putting automatic separator to your cache path, **example: %s** <- this will be -> **%s**"), $data[$fieldName], $this->fullCachePath);
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
        $filesRecords       = $this->getNumberOfFilesAndSizes();

        if(wire('input')->get('clearCache') == 'clearFilesCache') {
            $cache = phpFastCache($this->storage, $this->getConfig());
            $cache->clean();

            $this->message(sprintf(__('Total : %s files deleted and these deleted files size %s.'), $filesRecords['nbfiles'], $filesRecords['bytestotal']), Notice::log);
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
        if(!is_dir($this->moduleCachePath)) @mkdir($this->moduleCachePath, 0755);
        if(!is_dir($this->fullCachePath)) @mkdir($this->fullCachePath, 0777);
    }

    /**
     * Uninstall Module and Delete Cache Path
     */
    protected function ___uninstall() {
        $this->removeCacheDir($this->fullCachePath);
    }

    /**
     * Get Number Of Cached Files and Sizes
     *
     * @param null $path
     * @return array
     */
    protected function getNumberOfFilesAndSizes($path=null) {
        if(is_null($path)) $path = $this->fullCachePath;

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