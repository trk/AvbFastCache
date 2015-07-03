AvbFastCache Module
====================================
### Module Author

* [İskender TOTOĞLU](http://altivebir.com)
* [phpfastcache](http://www.phpfastcache.com/)

**Usage Almost Like original phpfastcache library, added some function to use WireCache also :**

* Just changed **get()** to **getCache()** and **set()** to **setCache()** methods, because its owerwriting other methods. Added **getSet()** function like core **WireCache::get()**
* Added a hook method after **Page::save()** for save last modified time and saved template modified time.

```
$fastcache = $modules->Fastcache;

echo $fastcache->getSet('cacheName', function($page)) {
    $output = "<h1>{$page->title}</h1>";
    $output .= "<div>{$page->body}</div>";
    
    return $output;
}

// or

$cacheData = $fastcache->getCache('cacheName');

if($cacheData == null) {
    $data = "<h1>{$page->title}</h1>";
    $data .= "<div>{$page->body}</div>";
    
    $fastcache->setCache('cacheName', $data);
    
    $cacheData = $data;
}
```