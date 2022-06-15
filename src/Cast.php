<?php declare(strict_types=1);
#SPDX-License-Identifier: LGPL-2.1-only or GPL-2.0-only
namespace rfx\Type;

use ArgumentCountError;
use Generator;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionProperty;
use TypeError;

/**
 * @template O of object
 */
final class Cast
{
    /** When casting, throw a TypeError when a property that does not exist on target is encountered. */
    public const CAST_UNKNOWN_THROW = 1;
    /** When casting, silently skip any property that does not exist on target class. */
    public const CAST_UNKNOWN_IGNORE = 2;
    /**
     * When casting, dynamically assign any property that does not exist on target class.
     * TODO: This will be deprecated in PHP-8.2, and error in PHP-9.0. https://wiki.php.net/rfc/deprecate_dynamic_properties
     */
    public const CAST_UNKNOWN_DYNAMIC = 3;

    /** @var string[] */
    private array $propertyNames;
    /** @var class-string<O> */
    private string $as;
    private bool $useConstructor;

    /**
     * Cast factory. Quickest method if we're casting a lot of objects.
     * @param class-string<O> $as
     * @throws ReflectionException
     */
    public function __construct(string $as, bool $useConstructor = false)
    {
        $this->useConstructor = $useConstructor;
        $this->as = $as;
        assert(class_exists($this->as));
        $reflection = new ReflectionClass($this->as);
        $this->propertyNames = [];
        foreach ($reflection->getProperties() as $p) {
            $this->propertyNames[] = $p->getName();
        }
    }

    /**
     * @param object $obj
     * @return O
     * @throws ReflectionException
     */
    public function cast(object $obj)
    {
        $dest = $this->useConstructor
            ? new $this->as
            : (new ReflectionClass($this->as))->newInstanceWithoutConstructor();
        foreach ($this->propertyNames as $name) {
            $dest->$name = $obj->$name;
        }
        return $dest;
    }

    /**
     * TODO: initialize class properties (static and not) to default values when instantiating
     * Cast some object `$obj` as type `$as` using reflection, recursively handling nested objects.
     * @param object $obj The source object
     * @param class-string<O> $as The type to cast it as
     * @param bool $useConstructor If true, use `new` to create the target object
     * @param int $unknownAction Action to take when an unknown property is encountered (see self::CAST_UNKNOWN_*)
     * @phpstan-param self::CAST_UNKNOWN_* $unknownAction
     * @return O
     * @throws ReflectionException
     * @throws ArgumentCountError
     * @throws TypeError
     */
    public static function as(object $obj, string $as, bool $useConstructor = false, int $unknownAction = self::CAST_UNKNOWN_THROW)
    {
        /**
         * Create an instance of `$as`.
         * This may throw `ReflectionException` if `$avoidConstructor` is `true`
         * It may throw `ArgumentCountError` if `$avoidConstructor` is `false`
         * @var O
         */
        $dest = $useConstructor
            ? new $as()
            : (new ReflectionClass($as))->newInstanceWithoutConstructor();
        assert($dest instanceof $as);

        $destReflection = new ReflectionObject($dest);
        foreach (self::propertiesOf($obj) as $name => $value) {
            if ($destReflection->hasProperty($name)) {
                $destProp = $destReflection->getProperty($name);
                $destProp->setAccessible(true);
                if ($destProp->isStatic()) {
                    continue;
                }
                $destProp->setValue(
                    $dest,
                    self::valueOf($value, $destProp, $useConstructor, $unknownAction)
                );
                continue;
            }
            switch ($unknownAction) {
                case self::CAST_UNKNOWN_THROW:
                    throw new TypeError('Destination object missing property: ' . $name);
                case self::CAST_UNKNOWN_IGNORE:
                    continue 2;
                case self::CAST_UNKNOWN_DYNAMIC:
                    $dest->$name = $value;
            }
        }

        return $dest;
    }

    /**
     * Iterate over `$obj`'s properties, generating [name => value]
     * @param object $obj
     * @return Generator<non-empty-string, mixed>
     */
    private static function propertiesOf(object $obj): Generator
    {
        $reflection = new ReflectionObject($obj);
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $propName = $property->getName();
            if ($propName === '') {
                throw new TypeError('Property with empty name in source object');
            }
            yield $propName => $property->getValue($obj);
        }
    }

    /**
     * TODO: support intersection and union types (https://www.php.net/manual/en/class.reflectiontype.php)
     * @param mixed $value
     * @param ReflectionProperty $prop
     * @param bool $useConstructor
     * @param int $unknownAction
     * @phpstan-param self::CAST_UNKNOWN_* $unknownAction
     * @return mixed
     * @throws ReflectionException
     * @throws ArgumentCountError
     * @throws TypeError
     */
    private static function valueOf($value, ReflectionProperty $prop, bool $useConstructor, int $unknownAction)
    {
        $destType = $prop->getType();
        if (!($destType instanceof ReflectionNamedType) || $destType->isBuiltin()) {
            return $value;
        }
        $destClass = $destType->getName();
        assert(class_exists($destClass));
        return self::as(
            (object)$value,
            $destClass,
            $useConstructor,
            $unknownAction
        );
    }

    /**
     * Cast some object `$obj` as type `$as`.
     * This uses a dirty serialize trick.
     * This is not recursive, nested objects remain unchanged.
     * Before using, beware of the dangers of `unserialize`. READ THE DOCS!
     * @param object $obj The source object
     * @param class-string<O> $as The type to cast it as
     * @param true|string[] $allowedClasses List of any other classes (besides `$as`) to allow being instantiated.
     *                                      `true` to allow *any* class to be loaded (dangerous).
     * @return O
     */
    public static function dirty(object $obj, string $as, $allowedClasses = [])
    {
        assert(is_array($allowedClasses) || $allowedClasses === true, "Invalid argument value for 'allowedClasses'");
        assert(
            $allowedClasses === true
            || array_sum(array_map('class_exists', $allowedClasses)) === count($allowedClasses),
            "Some classes in 'allowedClasses' do not exist"
        );
        assert(class_exists($as), "Target class does not exist: $as");

        $serialized = preg_replace(
            '/^(O:)(\d+)(:")([^"]+)(":)/',
            '${1}' . strlen($as) . '${3}' . preg_quote($as) . '${5}',
            serialize($obj)
        );
        assert($serialized !== null);

        if ($allowedClasses !== true) {
            $allowedClasses[] = $as;
        }
        $out = unserialize($serialized, ['allowed_classes' => $allowedClasses]);
        if (!($out instanceof $as)) {
            throw new TypeError("Cast failed");
        }

        return $out;
    }

    /**
     * @param object $obj
     * @param class-string<O> $as
     * @return O
     */
    public static function quick(object $obj, string $as)
    {
        $dest = new $as();
        $reflection = new ReflectionObject($obj);
        foreach ($reflection->getProperties() as $property) {
            $propName = $property->getName();
            $dest->$propName = $property->getValue($obj);
        }

        return $dest;
    }
}
