AvbFastCache Module
====================================
### Module Author

* [İskender TOTOĞLU](http://altivebir.com)

### Big Thanks to **phpFastCache** authors

* [phpFastCache](http://www.phpfastcache.com/)

**Usage Almost Like original phpFastCache library :**

I made some modification on original **phpFastCache** library for use it with ProcessWire. On my side i tested **files** and **sqlite** storage types and looks working well.

You can set default settings from module setting panel or you can use it like original library. From module setting panel you can set **storage** type, cache **path**, **security key**, **fallback** and also you can delete cached data from module settings panel.

Modified set function, working like core **$cache->get** function, this function will check a cached data exist ? if not save cache data and return cached data back.

```php
// Load Module
$AvbFastCache = $modules->AvbFastCache;
// Set cache settings from module
$_c = phpFastCache($AvbFastCache->storage, $AvbFastCache->getConfig(), $AvbFastCache->expire);

$output = $_c->set("cacheKeyword", function($page)) {
    $output = '<h1>{$page->title}</h1>';
    $output .= "<div class='body'>{$page->body}</div>";
    
    return $output;
});


//=> OR

// Do MemCache
$_c2 = phpFastCache("memcached");

// Write to Cache Save API Calls and Return Cache Data
echo $_c2->set("identity_keyword", function()) {
    $results = cURL->get("http://www.youtube.com/api/json/url/keyword/page");

    $output = "";
    foreach($results as $video) {
        $output .= $vieo->title; // Output Your Contents HERE
    }

    return $output;
}, 3600*24);

// This will check id=1 or parent_id=1 and will return last modified page UNIX_TIMESTAMP as result
echo $AvbFastCache->getLastModified(1);

// This will check id=1 or parent_id=1 and template=basic-page and will return last modified page UNIX_TIMESTAMP as result
echo $AvbFastCache->getLastModified(1, 'basic-page);
```

You can check **phpFastCache** usage from [phpFastCache wiki](https://github.com/khoaofgod/phpFastCache/wiki) or [phpFastCache offical website](http://www.phpfastcache.com/#example)

**Note :** I didn't tested this module with older **ProcessWire** versions, tested with **2.6.1** or **newer** versions. Module not have core dependency, it could work also older versions but need to check for be sure !