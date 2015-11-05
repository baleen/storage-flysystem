This package is part of the Baleen ecosystem. It uses the League's Flysystem to store the migration status into a file. 

Status
======
[![Build Status](https://travis-ci.org/baleen/storage-flysystem.svg?branch=master)](https://travis-ci.org/baleen/storage-flysystem)
[![Code Coverage](https://scrutinizer-ci.com/g/baleen/storage-flysystem/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/baleen/storage-flysystem/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/baleen/storage-flysystem/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/baleen/storage-flysystem/?branch=master)
[![SensioLabsInsight](https://img.shields.io/sensiolabs/i/6251e1ff-532d-4dad-a831-93dcf0561a49.svg)](https://insight.sensiolabs.com/projects/6251e1ff-532d-4dad-a831-93dcf0561a49)
[![Packagist](https://img.shields.io/packagist/v/baleen/storage-flysystem.svg)](https://packagist.org/packages/baleen/storage-flysystem)

[![Author](http://img.shields.io/badge/author-@gabriel_somoza-blue.svg)](https://twitter.com/gabriel_somoza)
[![License](https://img.shields.io/packagist/l/baleen/storage-flysystem.svg)](https://github.com/baleen/storage-flysystem/blob/master/LICENSE)

**NB!:** This project is still an early release. Please do not use in 
production-critical environments. Refer to the [LICENSE](https://github.com/baleen/storage-flysystem/blob/master/LICENSE)
for more information.

Installation (Composer)
=======================
Installation with Composer is simple:  

    composer require baleen/storage-flysystem
    
Usage
=====

The following example illustrates how you can use FlyStorage together with Flysystem's Local adapter. 

```php
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

$adapter = new Local(__DIR__.'/path/to/root');
$filesystem = new Filesystem($adapter);

$storage = new FlyStorage($filesystem); // default filename is ".baleen_versions"

// and then, for example:
$migratedVersions = $storage->fetchAll();

// another example (save a new migrated version):
$v = new \Baleen\Migrations\Version('newVersion', true);
$storage->save($v);
```

LICENSE
=======
MIT - for more details please refer to [LICENSE](https://github.com/baleen/migrations/blob/master/LICENSE) at the root 
directory.
