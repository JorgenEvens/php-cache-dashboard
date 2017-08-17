php-cache-dashboard
===================

A dashboard for
[PHP Opcache](http://php.net/manual/en/intro.opcache.php),
[APCu](http://php.net/manual/en/intro.apcu.php) and
[realpath](http://php.net/manual/en/function.realpath-cache-get.php)

Try it out at the [demo page](https://je-php-cache-dashboard-demo.herokuapp.com/).

## Prerequisites

 - PHP

and one or more of the supported caches

 - PHP OpCache (opcache extension for php5, included by default in php5.5+)
 - APC or APCu extension
 - Realpath cache ( available since PHP 5.3.2+ )

## Supported operations

 - View memory statistics
 - View hit rate
 - Select keys based on regular expression
 - Delete keys based on regular expression
 - Selecting all keys
 - Deleting keys without regular expressions
 - Sort on any data column
 - View APCu entry contents

## Usage

Simply drop the `cache.php` file somewhere on your webserver, preferably somewhere **private**, and that is it!
Navigate to the page using your browser and you will receive cache information.

![Screenshot of php-cache-dashboard](http://jorgen.evens.eu/github/php-cache-dashboard.png)

## Disabling caches

Information about specific caches can be disabled by setting the `ENABLE_<cache>` key to false.
The default code tests whether the specific cache is available and supported before enabling it.

### APC / APCu

```php
<?php
// Enable APC
define('ENABLE_APC', true);

// Disable APC
define('ENABLE_APC', false);
```

### OPcache

```php
<?php
// Enable OPcache
define('ENABLE_OPCACHE', true);

// Disable OPcache
define('ENABLE_OPCACHE', false);
```

### Realpath

```php
<?php
// Enable Realpath
define('ENABLE_REALPATH', true);

// Disable Realpath
define('ENABLE_REALPATH', false);
```

## Contributing

I really appreciate any contribution you would like to make, so don't hesitate to report an issue or submit pull requests.

## About me

Hi, my name is [Jorgen Evens](https://jorgen.evens.eu). By day I built things (mainly in PHP and JavaScript) for [Ambassify](https://ambassify.com) and by night I tinker around with these kinds of projects.
