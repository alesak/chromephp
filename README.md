## Overview

ChromePhp is a PHP library for the [Chrome Logger](https://chrome.google.com/extensions/detail/noaneddfkdjfnfdakjjmocngnfkfehhd) Google Chrome extension.

This library allows you to log data to the Chrome console.

## Requirements

- PHP 7.4 or later

## Installation

1. Install the browser extension from: https://chrome.google.com/extensions/detail/noaneddfkdjfnfdakjjmocngnfkfehhd
2. Click the extension icon in the browser to enable it for the current tab's domain
3. Install ChromePhp

__It's not recommended to use this library on production!__
```
    composer require --dev rame0/yandex-market-object
```

## Log some data

```php
// First require autoload
require __DIR__ . '/vendor/autoload.php';
```
#### ChromePhp::log
```php
ChromePhp::log('Some log message');
ChromePhp::log('Some other log message');
```
#### Result
![1](https://user-images.githubusercontent.com/2233607/116883874-c051ee00-ac2e-11eb-99dc-5c3819cdaad5.png)


#### ChromePhp::warn
```php
ChromePhp::warn('Some warning');
ChromePhp::warn('Some other warning');
```
#### Result
![2](https://user-images.githubusercontent.com/2233607/116883897-c6e06580-ac2e-11eb-9587-3f65a0db2f3e.png)


#### ChromePhp::error
```php
ChromePhp::error('Some error');
ChromePhp::error('Some other error');
```
#### Result
![3](https://user-images.githubusercontent.com/2233607/116883911-cba51980-ac2e-11eb-92a1-4d692d3a58a2.png)


#### ChromePhp::table
```php
$table = [];
for ($i = 0; $i < 5; $i++) {
    $row = [];
    for ($c = 1; $c < 3; $c++) {
        $row['Col ' . $c] = $i;
    }
    $table[] = $row;
}
ChromePhp::table($table);
```
#### Result
![4](https://user-images.githubusercontent.com/2233607/116883927-d1026400-ac2e-11eb-8012-64a5896e71cc.png)


#### ChromePhp::exception
```php
$ex = new InvalidArgumentException('Some exception!');
ChromePhp::exception($ex);

try{
    new PDO('asdf');
}catch (PDOException $ex){
    ChromePhp::exception($ex);
}
```
#### Result
![5](https://user-images.githubusercontent.com/2233607/116883949-d65fae80-ac2e-11eb-8598-362b575b3096.png)


More information can be found here:
http://www.chromelogger.com
