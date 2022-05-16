# Cast / convert a stdClass (or any other class) to another class
![Minimum PHP version: 7.4.0](https://img.shields.io/badge/php-7.4.0%2B-blue.svg)

## ABOUT
Allows you to cast a stdClass (or any other class) to some other class.

## INSTALL
```bash
composer require rfx/rfx-cast
```

## USAGE

### Problem
So you received a beautiful object that you want to use
```
stdClass Object
(
    [name] => P1
    [x] => 4
    [y] => 6
)
```
However:
 - Your IDE hates it
 - All static analysis tools hate it
 - No autocompletion
 - No type checks

### Solution
```php
// 1. Create a proper class that defines those properties
class Point {
    public string $name;
    public int $x;
    public int $y;
}

// 2. Get your object (from a json source, some external lib, ...). As demonstration we create one here from an array.
$obj = (object)['name' => 'P1', 'x' => 5, 'y' => 6];

// 2. Just cast it
use rfx\Type\Cast;
$point = Cast::as($obj, Point::class);
```

### Result
```
Point Object
(
    [name] => P1
    [x] => 5
    [y] => 6
)
```
 - Your IDE loves you again
 - All static analysis tools will help you find problems
 - Autocompletion, type checks, etc. work again

## LIMITATIONS
This section needs to be written, and some future plans added.
