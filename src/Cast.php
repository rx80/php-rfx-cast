<?php declare(strict_types=1);
#SPDX-License-Identifier: LGPL-2.1-only or GPL-2.0-only
namespace rfx\Type;

use TypeError;

final class Cast
{
    /**
     * Cast some object `$obj` as type `$as`.
     * This uses a dirty serialize trick.
     * Before using, beware of the dangers of `unserialize`. READ THE DOCS!
     * @template T of object
     * @param object $obj The source object
     * @param class-string<T> $as The type to cast it as
     * @param true|string[] $allowedClasses List of any other classes (besides `$as`) to allow being instantiated.
     *                                      `true` to allow *any* class to be loaded (dangerous).
     * @return T
     */
    public static function as(object $obj, string $as, $allowedClasses = [])
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
}
