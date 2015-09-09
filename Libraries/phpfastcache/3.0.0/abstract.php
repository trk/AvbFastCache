<?php

abstract class BasePhpFastCache {

    var $tmp = array();

    // default options, this will be merge to Driver's Options
    var $config = array();


    var $fallback = false;
    var $instant;

    /**
     * Set and get cached data some function added for work with ProcessWire
     *
     * @TODO For work with processwire, Don't forget when updating library
     *
     * @param $keyword
     * @param null $func
     * @param int $time
     * @param array $option
     * @param bool $return
     * @return bool|null
     * @throws WireException
     */
    public function set($keyword, $func = null, $time = 0, $option = array(), $return=TRUE) {
        // IF we have already cached data return it
        if($return === TRUE && !is_null($this->get($keyword))) return $this->get($keyword);
        // Set default expire time
        if(phpFastCache::$expire != FALSE && is_int(phpFastCache::$expire) && $time == 0) $time = phpFastCache::$expire;

        if(!is_null($func) && is_callable($func)) {
            // generate the cache now from the given callable function
            $value = $this->renderCacheValue($func);
        } else {
            $value = $func;
        }

        if(is_object($value)) {
            if($value instanceof PageArray) {
                $value = $this->pageArrayToArray($value);
            } else if(method_exists($value, '__toString')) {
                $value = (string) $value;
            } else {
                throw new WireException("WireCache::save does not know how to cache values of type " . get_class($value));
            }
        }

        /*
         * Infinity Time
         * Khoa. B
         */
        if((Int)$time <= 0) {
            // 5 years, however memcached or memory cached will gone when u restart it
            // just recommended for sqlite. files
            $time = 3600*24*365*5;
        }
        /*
         * Temporary disabled phpFastCache::$disabled = true
         * Khoa. B
         */
        if(phpFastCache::$disabled === true) {
            return false;
        }
        $object = array(
            "value" => $value,
            "write_time"  => @date("U"),
            "expired_in"  => $time,
            "expired_time"  => @date("U") + (Int)$time,
        );

        $this->driver_set($keyword,$object,$time,$option);

        return $value;
    }

    /**
     * Render and save a cache value, when given a function to do so
     *
     * Provided $func may specify any arguments that correspond with the names of API vars
     * and it will be sent those arguments.
     *
     * Provided $func may either echo or return it's output. If any value is returned by
     * the function it will be used as the cache value. If no value is returned, then
     * the output buffer will be used as the cache value.
     *
     * @TODO For work with processwire, Don't forget when updating library
     *
     * @param callable $func
     * @return bool|string
     * @since Version 2.5.28
     *
     */
    protected function renderCacheValue($func) {

        $ref = new ReflectionFunction($func);
        $params = $ref->getParameters(); // requested arguments
        $args = array(); // arguments we provide

        foreach($params as $param) {
            $arg = null;
            // if requested param is an API variable we will provide it
            if(preg_match('/\$([_a-zA-Z0-9]+)\b/', $param, $matches)) $arg = Wire()->wire($matches[1]);
            $args[] = $arg;
        }

        ob_start();

        if(count($args)) {
            $value = call_user_func_array($func, $args);
        } else {
            $value = $func();
        }

        $out = ob_get_contents();
        ob_end_clean();

        if(empty($value) && !empty($out)) $value = $out;

        return $value;
    }

    /**
     * Given a PageArray, convert it to a cachable array
     *
     * @TODO For work with processwire, Don't forget when updating library
     *
     * @param PageArray $items
     * @return array
     * @throws WireException
     * @since Version 2.5.28
     *
     */
    protected function pageArrayToArray(PageArray $items) {

        $templates = array();
        $ids = array();
        $pageClasses = array();

        foreach($items as $item) {
            $templates[$item->template->id] = $item->template->id;
            $ids[] = $item->id;
            $pageClass = $item->className();
            $pageClasses[$pageClass] = $pageClass;
        }

        if(count($pageClasses) > 1) {
            throw new WireException("Can't cache multiple page types together: " . implode(', ', $pageClasses));
        }

        $data = array(
            'PageArray' => $ids,
            'template'  => count($templates) == 1 ? reset($templates) : 0,
        );

        $pageClass = reset($pageClasses);
        if($pageClass && $pageClass != 'Page') $data['pageClass'] = $pageClass;

        $pageArrayClass = $items->className();
        if($pageArrayClass != 'PageArray') $data['pageArrayClass'] = $pageArrayClass;

        return $data;
    }

//    /*
//     * Basic Functions
//     */
//
//    public function set($keyword, $value = "", $time = 0, $option = array() ) {
//        /*
//         * Infinity Time
//         * Khoa. B
//         */
//        if((Int)$time <= 0) {
//            // 5 years, however memcached or memory cached will gone when u restart it
//            // just recommended for sqlite. files
//            $time = 3600*24*365*5;
//        }
//        /*
//         * Temporary disabled phpFastCache::$disabled = true
//         * Khoa. B
//         */
//        if(phpFastCache::$disabled === true) {
//            return false;
//        }
//        $object = array(
//            "value" => $value,
//            "write_time"  => @date("U"),
//            "expired_in"  => $time,
//            "expired_time"  => @date("U") + (Int)$time,
//        );
//
//        return $this->driver_set($keyword,$object,$time,$option);
//
//    }

    public function get($keyword, $option = array()) {
        /*
       * Temporary disabled phpFastCache::$disabled = true
       * Khoa. B
       */

        if(phpFastCache::$disabled === true) {
            return null;
        }

        $object = $this->driver_get($keyword,$option);

        if($object == null) {
            return null;
        }
		
		$value = isset( $object['value'] ) ? $object['value'] : null;
		return isset( $option['all_keys'] ) && $option['all_keys'] ? $object : $value;
    }


    function getInfo($keyword, $option = array()) {
        $object = $this->driver_get($keyword,$option);

        if($object == null) {
            return null;
        }
        return $object;
    }

    function delete($keyword, $option = array()) {
        return $this->driver_delete($keyword,$option);
    }

    function stats($option = array()) {
        return $this->driver_stats($option);
    }

    function clean($option = array()) {
        return $this->driver_clean($option);
    }

    function isExisting($keyword) {
        if(method_exists($this,"driver_isExisting")) {
            return $this->driver_isExisting($keyword);
        }

        $data = $this->get($keyword);
        if($data == null) {
            return false;
        } else {
            return true;
        }

    }

    // Searches though the cache for keys that match the given query.
    // todo: search
    function search($query) {
        if(method_exists($this,"driver_search")) {
            return $this->driver_search($query);
        }
        throw new Exception('Search method is not supported by this driver.');
    }

    function increment($keyword, $step = 1 , $option = array()) {
        $object = $this->get($keyword, array('all_keys' => true));
        if($object == null) {
            return false;
        } else {
            $value = (Int)$object['value'] + (Int)$step;
            $time = $object['expired_time'] - @date("U");
            $this->set($keyword,$value, $time, $option);
            return true;
        }
    }

    function decrement($keyword, $step = 1 , $option = array()) {
        $object = $this->get($keyword, array('all_keys' => true));
        if($object == null) {
            return false;
        } else {
            $value = (Int)$object['value'] - (Int)$step;
            $time = $object['expired_time'] - @date("U");
            $this->set($keyword,$value, $time, $option);
            return true;
        }
    }
    /*
     * Extend more time
     */
    function touch($keyword, $time = 300, $option = array()) {
        $object = $this->get($keyword, array('all_keys' => true));
        if($object == null) {
            return false;
        } else {
            $value = $object['value'];
            $time = $object['expired_time'] - @date("U") + $time;
            $this->set($keyword, $value,$time, $option);
            return true;
        }
    }


    /*
    * Other Functions Built-int for phpFastCache since 1.3
    */

    public function setMulti($list = array()) {
        foreach($list as $array) {
            $this->set($array[0], isset($array[1]) ? $array[1] : 0, isset($array[2]) ? $array[2] : array());
        }
    }

    public function getMulti($list = array()) {
        $res = array();
        foreach($list as $array) {
            $name = $array[0];
            $res[$name] = $this->get($name, isset($array[1]) ? $array[1] : array());
        }
        return $res;
    }

    public function getInfoMulti($list = array()) {
        $res = array();
        foreach($list as $array) {
            $name = $array[0];
            $res[$name] = $this->getInfo($name, isset($array[1]) ? $array[1] : array());
        }
        return $res;
    }

    public function deleteMulti($list = array()) {
        foreach($list as $array) {
            $this->delete($array[0], isset($array[1]) ? $array[1] : array());
        }
    }

    public function isExistingMulti($list = array()) {
        $res = array();
        foreach($list as $array) {
            $name = $array[0];
            $res[$name] = $this->isExisting($name);
        }
        return $res;
    }

    public function incrementMulti($list = array()) {
        $res = array();
        foreach($list as $array) {
            $name = $array[0];
            $res[$name] = $this->increment($name, $array[1], isset($array[2]) ? $array[2] : array());
        }
        return $res;
    }

    public function decrementMulti($list = array()) {
        $res = array();
        foreach($list as $array) {
            $name = $array[0];
            $res[$name] = $this->decrement($name, $array[1], isset($array[2]) ? $array[2] : array());
        }
        return $res;
    }

    public function touchMulti($list = array()) {
        $res = array();
        foreach($list as $array) {
            $name = $array[0];
            $res[$name] = $this->touch($name, $array[1], isset($array[2]) ? $array[2] : array());
        }
        return $res;
    }


    public function setup($config_name,$value = "") {
        /*
         * Config for class
         */
        if(is_array($config_name)) {
            $this->config = $config_name;
        } else {
            $this->config[$config_name] = $value;
        }

    }

    /*
     * Magic Functions
     */


    function __get($name) {
        return $this->get($name);
    }


    function __set($name, $v) {
        if(isset($v[1]) && is_numeric($v[1])) {
            return $this->set($name,$v[0],$v[1], isset($v[2]) ? $v[2] : array() );
        } else {
            throw new Exception("Example ->$name = array('VALUE', 300);",98);
        }
    }

    public function __call($name, $args) {
        $str = implode(",",$args);
        eval('return $this->instant->$name('.$str.');');
    }


    /*
     * Base Functions
     */



    protected function backup() {
        return phpFastCache(phpFastCache::$config['fallback']);
    }


    protected function required_extension($name) {
        require_once(dirname(__FILE__)."/../_extensions/".$name);
    }


    protected function readfile($file) {
        if(function_exists("file_get_contents")) {
            return @file_get_contents($file);
        } else {
            $string = "";

            $file_handle = @fopen($file, "r");
            if(!$file_handle) {
                throw new Exception("Can't Read File",96);

            }
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                $string .= $line;
            }
            fclose($file_handle);

            return $string;
        }
    }



    /*
     * return PATH for Files & PDO only
     */


    public function getPath($create_path = false) {
        return phpFastCache::getPath($create_path,$this->config);
    }


    /*
     * Object for Files & SQLite
     */
    protected function encode($data) {
        return serialize($data);
    }

    protected function decode($value) {
        $x = @unserialize($value);
        if($x == false) {
            return $value;
        } else {
            return $x;
        }
    }



    /*
     * Auto Create .htaccess to protect cache folder
     */

    protected function htaccessGen($path = "") {
        if($this->option("htaccess") == true) {

            if(!@file_exists($path."/.htaccess")) {
                //   echo "write me";
                $html = "order deny, allow \r\n
deny from all \r\n
allow from 127.0.0.1";

                $f = @fopen($path."/.htaccess","w+");
                if(!$f) {
                    throw new Exception("Can't create .htaccess",97);
                }
                fwrite($f,$html);
                fclose($f);


            } else {
                //   echo "got me";
            }
        }

    }

    /*
    * Check phpModules or CGI
    */

    protected function isPHPModule() {
       return phpFastCache::isPHPModule();
    }

    /*
         * return System Information
     */
    public function systemInfo() {
        $backup_option = $this->option;
        if(count($this->option("system")) == 0 ) {
            $this->option['system']['driver'] = "files";
            $this->option['system']['drivers'] = array();
            $dir = @opendir(dirname(__FILE__)."/drivers/");
            if(!$dir) {
                throw new Exception("Can't open file dir ext",100);
            }

            while($file = @readdir($dir)) {
                if($file!="." && $file!=".." && strpos($file,".php") !== false) {
                    require_once(dirname(__FILE__)."/drivers/".$file);
                    $namex = str_replace(".php","",$file);
                    $class = "phpfastcache_".$namex;
                    $this->option['skipError'] = true;
                    $driver = new $class($this->option);
                    $driver->option = $this->option;
                    if($driver->checkdriver()) {
                        $this->option['system']['drivers'][$namex] = true;
                        $this->option['system']['driver'] = $namex;
                    } else {
                        $this->option['system']['drivers'][$namex] = false;
                    }
                }
            }


            /*
             * PDO is highest priority with SQLite
             */
            if($this->option['system']['drivers']['sqlite'] == true) {
                $this->option['system']['driver'] = "sqlite";
            }




        }

        $example = new phpfastcache_example($this->config);
        $this->option("path",$example->getPath(true));
        $this->option = $backup_option;
        return $this->option;
    }


    protected function isExistingDriver($class) {
        if(@file_exists(dirname(__FILE__)."/drivers/".$class.".php")) {
            require_once(dirname(__FILE__)."/drivers/".$class.".php");
            if(class_exists("phpfastcache_".$class)) {
                return true;
            }
        }

        return false;
    }



    protected function __setChmodAuto() {
        return phpFastCache::__setChmodAuto($this->config);
    }


}