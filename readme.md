# Cast / convert a stdClass (or any other class) to another class
![Minimum PHP version: 7.4.0](https://img.shields.io/badge/php-7.4.0%2B-blue.svg)

## ABOUT
Allows you to cast a stdClass (or any other class) to some other class.

## INSTALL
```bash
composer require rfx/cast
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
// Create a proper class that defines those properties
use rfx\Type\Cast;
class Point {
    public string $name;
    public int $x;
    public int $y;
}

function getPoint(): Point {
    // Get your object (from a json source, some external lib, ...).
    // As demonstration we create one here from an array.
    $obj = (object)['name' => 'P1', 'x' => 4, 'y' => 5];
    // Cast it
    return Cast::as($obj, Point::class);
}

$p = getPoint();
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

### Speed
If performance is important and:
 - you are casting many objects of the same type
 - all your objects are shallow (no nesting)
 - all the properties are scalar types

then you can use the faster alternative, which is as performant as a normal constructor:
```php
// Create a proper class that defines those properties
use rfx\Type\Cast;
class Point {
    public string $name;
    public int $x;
    public int $y;
}

/** @return Point[] */
function getPoints(): array {
    // Get your objects (from a json source, some external lib, ...).
    // As demonstration we create a million from an array.
    $obj = (object)['name' => 'P1', 'x' => 4, 'y' => 5];
    $objs = array_fill(0, 1000000, $obj);
    // Create a factory
    $cf = new Cast(Point::class);
    // Cast them all
    return array_map([$cf, 'cast'], $objs);
}

$p = getPoints();
```

## LIMITATIONS
This section needs to be written, and some future plans added.
