<?php

/**
 * Implementation class for AvbFastCache::getModuleConfigInputfields
 *
 * @author          : İskender TOTOĞLU, @ukyo (community), @trk (Github)
 * @website         : http://altivebir.com
 * @projectWebsite  : https://github.com/trk/AvbFastCache
 */
class AvbFastCacheConfig extends Wire {

    // Minimum ProcessWire version required to run AvbFastCache
    const requiredVersion = '2.6.1';

    const defaultPath = "AvbFastCache";
    const cacheDatabaseTable = 'caches';
    const defaultPrefix = 'cache_';

    protected $path;
    protected $prefix;
    protected $table;

    protected $inputfields;
    protected $data = array();

    public function __construct(array $data) {
        $this->table = self::cacheDatabaseTable;
        $this->data = $data;
        $this->inputfields = new InputfieldWrapper();
    }

    public function getConfig() {

        // check that they have the required PW version
        if(version_compare(wire('config')->version, self::requiredVersion, '<')) {
            $this->error("AvbFastCache requires ProcessWire " . self::requiredVersion . " or newer. You need to update your ProcessWire version before using AvbFastCache.");
        }

        // Set Path
        if(array_key_exists('path', $this->data) && $this->data['path'] != "") $path = $this->data['path'];
        else $path = self::defaultPath;

        $this->path = wire('config')->paths->assets . $path;

        // Set Prefix
        $this->prefix = (isset($this->data['prefix']) && $this->data['prefix'] != "") ? $this->data['prefix'] : self::defaultPrefix;

        $this->configDatabaseCacheInfo();
        if(file_exists($this->path)) $this->configFilesCacheInfo();
        $this->configCacheExpireTime();
        $this->configStorage();
        $this->configPrefix();
        $this->configCachePath();
        $this->configSecurityKey();
        $this->configFallback();

        return $this->inputfields;
    }

    protected function configDatabaseCacheInfo() {

        $databaseRecords    = $this->getNumberOfDatabaseCacheRecords();

        if(wire('input')->get('clearCache') === 'clearDatabaseCache') {
            $this->deleteDatabaseCacheRecords();

            $this->message(sprintf(__('Total %s database cache records cleared for given "%s" prefix name.'), $databaseRecords, $this->prefix), Notice::log);

            wire('session')->redirect(wire('page')->httpUrl.'edit?name=' . wire('input')->get('name'));
        }

        $f = wire('modules')->get("InputfieldMarkup");
        $f->columnWidth = 50;
        $f->icon = "database";
        $f->label = __("Database Cache Info");
        $f->description = sprintf(__('Total : %s database records found for given prefix.'), $databaseRecords);

        // Hidden Form Field
        $h = wire('modules')->get("InputfieldHidden");
        $h->name = "clearDatabaseCache";
        $h->value = "clearDatabaseCache";
        $f->add($h);

        $fb = wire('modules')->get('InputfieldButton');
        $fb->icon = "trash";
        $fb->name = 'clearDatabaseCache';
        $fb->value = __('Clear Database Cache');
        $fb->href = 'edit?name='.wire('input')->get('name').'&clearCache=clearDatabaseCache';
        $f->add($fb);

        $this->inputfields->add($f);
    }

    /**
     * Cache Info and Clear Cache Form
     */
    protected function configFilesCacheInfo() {

        $filesRecords       = $this->getNumberOfFilesAndSizes();

        if(wire('input')->get('clearCache') == 'clearFilesCache') {
            $phpFastCache = wire('modules')->get('AvbFastCache');
            $phpFastCache->clean();

            $this->message(sprintf(__('Total : %s files deleted and these deleted files size %s.'), $filesRecords['nbfiles'], $filesRecords['bytestotal']), Notice::log);
            wire('session')->redirect(wire('page')->httpUrl.'edit?name=' . wire('input')->get('name'));
        }

        $f = wire('modules')->get("InputfieldMarkup");
        $f->columnWidth = 50;
        $f->label = __("File Cache Info");
        $f->icon = 'folder';
        $f->description = sprintf(__('Total cached files: %s, Used space: %s'), $filesRecords['nbfiles'], $filesRecords['bytestotal']);

        // Hidden Form Field
        $h = wire('modules')->get("InputfieldHidden");
        $h->name = "clearFilesCache";
        $h->value = "clearFilesCache";
        $f->add($h);

        $fb = wire('modules')->get('InputfieldButton');
        $fb->icon = "trash";
        $fb->name = 'clearFilesCache';
        $fb->value = __('Clear Files Cache');
        $fb->href = 'edit?name='.wire('input')->get('name').'&clearCache=clearFilesCache';
        $f->add($fb);

        $this->inputfields->add($f);
    }

    /**
     * Cache Expire Time
     */
    protected function configCacheExpireTime() {
        $fieldName = "expire";
        $value = !empty($this->data[$fieldName]) ? $this->data[$fieldName] : 1;

        $f = wire('modules')->get('InputfieldInteger');
        $f->attr('name', $fieldName);
        $f->attr('value', $value);
        $f->attr('min', 0);
        $f->attr('type', 'number');
        $f->label = __("Cache Expire Time");
        $f->notes = __("For example: 0 = mean (for wire cache, don't cache), (for files cache unlimited time), 1 = 1 second, 60 = 1 minute, 600 = 10 minutes, 3600 = 1 hour, 86400 = 1 day, 604800 = 1 week, 2419200 = 1 month.");
        $f->required = true;
        $this->inputfields->add($f);
    }

    /**
     * Cache Storage Type
     */
    protected function configStorage() {
        $fieldName = "storage";
        $value = !empty($this->data[$fieldName]) ? $this->data[$fieldName] : 'auto';

        $f = wire('modules')->get('InputfieldSelect');
        $f->attr('name', $fieldName);
        $f->label = __("Storage");
        $f->description = __("auto, wire cache, files, sqlite, apc, cookie, memcache, memcached, predis, redis, wincache, xcache");
        $f->required = true;
        $f->addOptions(array(
            'auto' => 'auto',
            'cache' => 'wire cache',
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
        $f->value = $value;
        $this->inputfields->add($f);
    }

    /**
     * Cache Record Prefix
     */
    protected function configPrefix() {
        $fieldName = "prefix";
        $value = !empty($this->data[$fieldName]) ? $this->data[$fieldName] : 'cache_';

        $f = wire('modules')->get('InputfieldText');
        $f->attr('name', $fieldName);
        $f->attr('value', $value);
        $f->label = __("Prefix");
        $f->description = __("This prefix will be added to your cache keyword and this will use for delete database cache data.");
        $f->notes = __("ProcessWire using 'Modules' prefix for cache modules data and don't use 'Modules' prefix, we offer something like 'cache_'.");
        $f->required = true;
        $this->inputfields->add($f);
    }

    /**
     * Cache Path
     */
    protected function configCachePath() {
        $fieldName = "path";
        $value = !empty($this->data[$fieldName]) ? $this->data[$fieldName] : self::defaultPath;

        $f = wire('modules')->get('InputfieldText');
        $f->attr('name', $fieldName);
        $f->attr('value', $value);
        $f->label = __("Cache Path");
        $f->description = __("You can write a cache path with out start and end a directory separator '/'");
        $f->notes = sprintf(__("We are putting automatic separator to your cache path, **example: AvbFastCache** <- this will be -> **%sAvbFastCache**"), wire('config')->paths->assets);
        $f->required = true;
        $this->inputfields->add($f);
    }

    /**
     * Security Key
     */
    protected function configSecurityKey() {
        $fieldName = "securityKey";
        $value = !empty($this->data[$fieldName]) ? $this->data[$fieldName] : 'auto';

        $f = wire('modules')->get('InputfieldText');
        $f->attr('name', $fieldName);
        $f->attr('value', $value);
        $f->label = __("Security Key");
        $f->description = __("auto will use domain name, set it to 1 string if you use alias domain name");
        $f->required = true;
        $this->inputfields->add($f);
    }

    /**
     * Fallback
     */
    protected function configFallback() {
        $fieldName = "fallback";
        $value = !empty($this->data[$fieldName]) ? $this->data[$fieldName] : 'files';

        $f = wire('modules')->get('InputfieldText');
        $f->attr('name', $fieldName);
        $f->attr('value', $value);
        $f->label = __("Fallback");
        $f->required = true;
        $this->inputfields->add($f);
    }

    /**
     * Get Number Of Cached Files and Sizes
     *
     * @param null $path
     * @return array
     */
    protected function getNumberOfFilesAndSizes($path=null) {
        if(is_null($path)) $path = $this->path;

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
     * Get Number Of Database Records For Cached Data
     *
     * @return int
     */
    protected function getNumberOfDatabaseCacheRecords() {
        $return = 0;
        $result = wire('db')->query("SELECT COUNT(`name`) AS result FROM {$this->table} WHERE `name` LIKE '{$this->prefix}%'");
        if($result->num_rows > 0) {
            $res = $result->fetch_assoc();
            $return = $res['result'];
        }
        return $return;
    }

    /**
     * Delete Database Caches, If caches prefix start with given prefix name
     */
    protected function deleteDatabaseCacheRecords() {
        wire('db')->query("DELETE FROM {$this->table} where `name` LIKE '{$this->prefix}%'");
    }

}