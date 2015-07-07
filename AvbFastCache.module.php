<?php

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

    public $phpFastCache;
    protected $CachePath;

    public function __construct() {
        $this->set('storage', 'files');
        $this->set('prefix', 'cache_');
        $this->set('path', 'AvbFastCache');
        $this->set('securityKey', 'auto');
        $this->set('fallback', 'files');
    }


    /**
     * Initialize the module
     *
     */
    public function init($storage="", $config=array()) {

        $this->addHookAfter('ProcessPageSort::execute', $this, '_setPageModified');

        if($this->storage === 'cache' || $this->storage === 'sqlite') {
            $this->phpFastCache = wire('cache');
        } else {
            $this->CachePath = wire('config')->paths->assets . $this->path;
            if(!file_exists($this->CachePath)) $this->___install();

            if(!class_exists('phpFastCache')) {
                require_once(dirname(__FILE__) . self::phpFastCacheLibraryPath);
            }

            if(empty($config)) {
                $config = phpFastCache::$config = array(
                    "storage" => $this->storage,
                    "default_chmod" => 0777,
                    "htaccess" => true,
                    "path" => $this->CachePath,
                    "securityKey" =>  $this->securityKey,
                    "fallback" => $this->fallback,
                );
            }

            $config['storage'] = ($storage != "") ? $storage : $this->storage;

            $storage = strtolower($storage);
            if($storage == "" || $storage == "auto") {
                $storage = phpFastCache::getAutoClass($config);
            }

            $this->phpFastCache = phpFastCache($storage, $config);
        }
    }

    /**
     * Get or Set cache data
     *
     * @param $keyword
     * @param null $func
     * @return mixed|string
     */
    public function getSet($keyword, $func = null) {

        $keyword = $this->prefix . $keyword;
        $expire = $this->expire;
        if($this->storage === 'cache' || $this->storage === 'sqlite') {
            $cache = wire('cache');
            return $cache->get($keyword, $expire, $func);
        } else {
            $cacheData = $this->phpFastCache->get($keyword);
            if(is_null($cacheData)) {
                if(!is_null($func)) {
                    $cache = wire('cache');
                    // Create new database cache record
                    $value = $cache->get($keyword, $expire, $func);

                    if($value !== false) {
                        $this->phpFastCache->set($keyword, $value, $expire);
                        $cacheData = $value;
                    }
                }
            }
            return $cacheData;
        }
    }

    public function setCache($keyword, $value = "", $time = 0, $option = array()) {
        return $this->phpFastCache->set($keyword, $value, $time, $option);
    }

    public function getCache($keyword, $option = array()) {
        return $this->phpFastCache->get($keyword, $option);
    }

    function getInfo($keyword, $option = array()) {
        return $this->phpFastCache->getInfo($keyword, $option);
    }

    function delete($keyword, $option = array()) {
        return $this->phpFastCache->delete($keyword,$option);
    }

    function stats($option = array()) {
        return $this->phpFastCache->stats($option);
    }

    function clean($option = array()) {
        return $this->phpFastCache->clean($option);
    }

    function isExisting($keyword) {
        return $this->phpFastCache->isExisting($keyword);
    }

    // todo: search
    function search($query) {
        return $this->phpFastCache->search($query);
    }

    function increment($keyword, $step = 1 , $option = array()) {
        return $this->phpFastCache->increment($keyword, $step, $option);
    }

    function decrement($keyword, $step = 1 , $option = array()) {
        return $this->phpFastCache->decrement($keyword, $step, $option);
    }

    function touch($keyword, $time = 300, $option = array()) {
        return $this->phpFastCache->touch($keyword, $time, $option);
    }

    public function setMulti($list = array()) {
        $this->phpFastCache->setMulti($list);
    }

    public function getMulti($list = array()) {
        return $this->phpFastCache->getMulti($list);
    }

    public function getInfoMulti($list = array()) {
        return $this->phpFastCache->getInfoMulti($list);
    }

    public function deleteMulti($list = array()) {
        $this->phpFastCache->deleteMulti($list);
    }

    public function isExistingMulti($list = array()) {
        return $this->phpFastCache->isExistingMulti($list);
    }

    public function incrementMulti($list = array()) {
        return $this->phpFastCache->incrementMulti($list);
    }

    public function decrementMulti($list = array()) {
        return $this->phpFastCache->decrementMulti($list);
    }

    public function touchMulti($list = array()) {
        return $this->phpFastCache->touchMulti($list);
    }

    public function setup($config_name,$value = "") {
        $this->phpFastCache->setup($config_name, $value);
    }

    /*
    function __get($name) {
        return $this->phpFastCache->__get($name);
    }

    function __set($name, $v) {
        return $this->phpFastCache->__set($name, $v);
    }

    public function __call($name, $args) {
        return $this->phpFastCache->__call($name, $args);
    }
    */

    public function getPath($create_path = false) {
        return $this->phpFastCache->getPath($create_path);
    }

    public function systemInfo() {
        return $this->phpFastCache->systemInfo();
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
    public function _setPageModified($event) {
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
     * Configure the AvbFastCache Modules
     *
     */
    public static function getModuleConfigInputfields(array $data) {
        if(!class_exists('AvbFastCacheConfig')) {
            require(dirname(__FILE__) . '/AvbFastCacheConfig.php');
        }
        $c = new AvbFastCacheConfig($data);
        return $c->getConfig();
    }

    /**
     * Install Module Create Cache Path
     */
    protected function ___install() {
        if(!is_dir($this->CachePath)) @mkdir($this->CachePath, 0777);
    }

    /**
     * Uninstall Module and Delete Cache Path
     */
    protected function ___uninstall() {
        $this->removeCacheDir($this->CachePath);
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