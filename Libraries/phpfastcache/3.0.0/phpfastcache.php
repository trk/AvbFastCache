<?php
/*
 * khoaofgod@gmail.com
 * Website: http://www.phpfastcache.com
 * Example at our website, any bugs, problems, please visit http://faster.phpfastcache.com
 */


require_once(dirname(__FILE__)."/abstract.php");
require_once(dirname(__FILE__)."/driver.php");

// short function
if(!function_exists("__c")) {
	function __c($storage = "", $option = array(), $expire=FALSE) {
		return phpFastCache($storage, $option, $expire);
	}
}

// main function
if(!function_exists("phpFastCache")) {
	function phpFastCache($storage = "auto", $config = array(), $expire=FALSE) {
        if($expire != FALSE && is_int($expire)) phpFastCache::$expire = $expire; // @TODO Added for use default expire time (changeable), Don't forget when updating library
        $storage = strtolower($storage);
        if(empty($config)) {
            $config = phpFastCache::$config;
        }

        if($storage == "" || $storage == "auto") {
            $storage = phpFastCache::getAutoClass($config);
        }


        $instance = md5(json_encode($config).$storage);
		if(!isset(phpFastCache_instances::$instances[$instance])) {
            $class = "phpfastcache_".$storage;
            phpFastCache::required($storage);
			phpFastCache_instances::$instances[$instance] = new $class($config);
		}

		return phpFastCache_instances::$instances[$instance];
	}
}

class phpFastCache_instances {
	public static $instances = array();
}


// main class
class phpFastCache {
    public static $expire = FALSE; // @TODO Added for use default expire time (changeable), Don't forget when updating library
    public static $disabled = false;
	public static $config = array(
        "storage"       =>  "", // blank for auto
        "default_chmod" =>  0777, // 0777 , 0666, 0644
		/*
		 * Fall back when old driver is not support
		 */
		"fallback"  => "files",

		"securityKey"   =>  "auto",
		"htaccess"      => true,
		"path"      =>  "",

		"memcache"        =>  array(
			array("127.0.0.1",11211,1),
			//  array("new.host.ip",11211,1),
		),

		"redis"         =>  array(
			"host"  => "127.0.0.1",
			"port"  =>  "",
			"password"  =>  "",
			"database"  =>  "",
			"timeout"   =>  ""
		),

        "ssdb"         =>  array(
			"host"  => "127.0.0.1",
			"port"  =>  8888,
			"password"  =>  "",
			"timeout"   =>  ""
		),

		"extensions"    =>  array(),
	);

    protected static $tmp = array();
    var $instance;

    function __construct($storage = "", $config = array(), $expire=FALSE) {

        if($expire != FALSE && is_int($expire)) phpFastCache::$expire = $expire; // @TODO Added for use default expire time (changeable), Don't forget when updating library

        if(empty($config)) {
            $config = phpFastCache::$config;
        }
        $config['storage'] = $storage;

        $storage = strtolower($storage);
        if($storage == "" || $storage == "auto") {
            $storage = self::getAutoClass($config);
        }

        $this->instance = phpFastCache($storage,$config,$expire);
    }




    public function __call($name, $args) {
        return call_user_func_array(array($this->instance, $name), $args);
    }


    /*
     * Cores
     */

    public static function getAutoClass($config) {

        $driver = "files";
        $path = self::getPath(false,$config);
        if(is_writeable($path)) {
            $driver = "files";
        }else if(extension_loaded('apc') && ini_get('apc.enabled') && strpos(PHP_SAPI,"CGI") === false) {
            $driver = "apc";
        }else if(class_exists("memcached")) {
            $driver = "memcached";
        }elseif(extension_loaded('wincache') && function_exists("wincache_ucache_set")) {
            $driver = "wincache";
        }elseif(extension_loaded('xcache') && function_exists("xcache_get")) {
            $driver = "xcache";
        }else if(function_exists("memcache_connect")) {
            $driver = "memcache";
        }else if(class_exists("Redis")) {
            $driver = "redis";
        }else {
            $driver = "files";
        }


        return $driver;

    }

    public static function getPath($skip_create_path = false, $config) {
        if ( !isset($config['path']) || $config['path'] == '' )
        {

            // revision 618
            if(self::isPHPModule()) {
                $tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
                $path = $tmp_dir;
            } else {
                $path = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'],"/")."/../" : rtrim(dirname(__FILE__),"/")."/";
            }

            if(self::$config['path'] != "") {
                $path = $config['path'];
            }

        } else {
            $path = $config['path'];
        }

        $securityKey = array_key_exists('securityKey',$config) ? $config['securityKey'] : "";
        if($securityKey == "" || $securityKey == "auto") {
            $securityKey = self::$config['securityKey'];
            if($securityKey == "auto" || $securityKey == "") {
                $securityKey = isset($_SERVER['HTTP_HOST']) ? preg_replace('/^www./','',strtolower($_SERVER['HTTP_HOST'])) : "default";
            }
        }
        if($securityKey != "") {
            $securityKey.= "/";
        }
        
        $securityKey = self::cleanFileName($securityKey);

        $full_path = $path."/".$securityKey;
        $full_pathx = md5($full_path);




        if($skip_create_path  == false && !isset(self::$tmp[$full_pathx])) {

            if(!@file_exists($full_path) || !@is_writable($full_path)) {
                if(!@file_exists($full_path)) {
                    @mkdir($full_path,self::__setChmodAuto($config));
                }
                if(!@is_writable($full_path)) {
                    @chmod($full_path,self::__setChmodAuto($config));
                }
                if(!@file_exists($full_path) || !@is_writable($full_path)) {
					throw new Exception("PLEASE CREATE OR CHMOD ".$full_path." - 0777 OR ANY WRITABLE PERMISSION!",92);
                }
            }


            self::$tmp[$full_pathx] = true;
            self::htaccessGen($full_path, array_key_exists('htaccess',$config) ? $config['htaccess'] : false);
        }

        return realpath($full_path);

    }
    
    public static function cleanFileName($filename) {
        $regex = ['/[\?\[\]\/\\\=\<\>\:\;\,\'\"\&\$\#\*\(\)\|\~\`\!\{\}]/','/\.$/','/^\./'];
        $replace = ['-','',''];
        return preg_replace($regex,$replace,$filename);
    }


    public static function __setChmodAuto($config) {
        if(!isset($config['default_chmod']) || $config['default_chmod'] == "" || is_null($config['default_chmod'])) {
            return 0777;
        } else {
            return $config['default_chmod'];
        }
    }

    protected static function getOS() {
        $os = array(
            "os" => PHP_OS,
            "php" => PHP_SAPI,
            "system"    => php_uname(),
            "unique"    => md5(php_uname().PHP_OS.PHP_SAPI)
        );
        return $os;
    }

    public static function isPHPModule() {
        if(PHP_SAPI == "apache2handler") {
            return true;
        } else {
            if(strpos(PHP_SAPI,"handler") !== false) {
                return true;
            }
        }
        return false;
    }

    protected static function htaccessGen($path, $create = true) {

        if($create == true) {
            if(!is_writeable($path)) {
                try {
                    chmod($path,0777);
                }
                catch(Exception $e) {
					throw new Exception("PLEASE CHMOD ".$path." - 0777 OR ANY WRITABLE PERMISSION!",92);
                }
            }
            if(!@file_exists($path."/.htaccess")) {
                //   echo "write me";
                $html = "order deny, allow \r\n
deny from all \r\n
allow from 127.0.0.1";

                $f = @fopen($path."/.htaccess","w+");
                if(!$f) {
					throw new Exception("PLEASE CHMOD ".$path." - 0777 OR ANY WRITABLE PERMISSION!",92);
                }
                fwrite($f,$html);
                fclose($f);


            }
        }

    }


    public static function setup($name,$value = "") {
        if(is_array($name)) {
            self::$config = $name;
        } else {
            self::$config[$name] = $value;
        }
    }

    public static function debug($something) {
        echo "Starting Debugging ...<br>\r\n ";
        if(is_array($something)) {
            echo "<pre>";
            print_r($something);
            echo "</pre>";
            var_dump($something);
        } else {
            echo $something;
        }
        echo "\r\n<br> Ended";
        exit;
    }

    public static function required($class) {
        require_once(dirname(__FILE__)."/drivers/".$class.".php");
    }


}
