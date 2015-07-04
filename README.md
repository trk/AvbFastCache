AvbFastCache Module
====================================
### Module Author

* [İskender TOTOĞLU](http://altivebir.com)
* [phpfastcache](http://www.phpfastcache.com/)

**Usage Almost Like original phpfastcache library, added some function to use WireCache also :**

* Module have **expire date** option and cache **prefix** option, **prefix** for clean database cache records, and **expire date** for manually change expire date from admin panel
* Just changed **get()** to **getCache()** and **set()** to **setCache()** methods, because its owerwriting other methods. Added **getSet()** function like core **WireCache::get()**
* Added a hook method after **ProcessPageSort::execute**, because when you sort pages, this action not updating modified dates for sorted pages

**You can use it like ProcessWire WireCache**

**$modules->AvbFastCache->getSet() function using $cache->get() method for parse ProcessWire data and first step is Database Record, Second step is chosen storage type.**
```php
$_c = $modules->AvbFastCache;

echo $_c->getSet('cacheName', function($page)) {
    $output = "<h1>{$page->title}</h1>";
    $output .= "<div>{$page->body}</div>";
    
    return $output;
}

// Get last modification date for given id
echo $_c->getLastModified(1);

// Get last modification date for given parent_id, this will check pages parent_id=1 and will return last modified page date
echo $_c->getLastModified(1, true);

// Get last modification date for given parent_id, this will check pages parent_id=1 and template=basic-page will return last modified page date
echo $_c->getLastModified(1, true, 'basic-page);
```

You can check **phpfastcache** usage from [phpfastcache wiki](https://github.com/khoaofgod/phpfastcache/wiki) or [phpfastcache offical website](http://www.phpfastcache.com/#example)