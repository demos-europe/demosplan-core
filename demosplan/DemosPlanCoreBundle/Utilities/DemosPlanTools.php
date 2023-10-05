<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utilities;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class DemosPlanTools
{
    /**
     * Wrapper for varExport to avoid memory errors.
     *
     * @param mixed $expression
     * @param bool  $return
     *
     * @return mixed
     */
    public static function varExport($expression, $return = false)
    {
        try {
            // if object generate array to limit the "level of information to the first level
            if (is_object($expression)) {
                $expression = self::toArray($expression);

                return var_export($expression, $return);
            }

            // prevent infinity loop by recursion of objects (in objects)
            if (is_array($expression)) {
                $expression = self::replaceObjectsInArray($expression, $return);
            }

            return var_export($expression, $return);
        } catch (ReflectionException) {
            return [];
        }
    }

    /**
     * Replaces every Object in the given array by the value got by getIdent() or getId().
     * If there not a method like getIdent() or getId(), the object will be remplaced by the string "removed Object".
     *
     * @param array $array
     * @param bool  $return
     */
    private static function replaceObjectsInArray($array, $return): array
    {
        foreach ($array as $item => $value) {
            if (is_array($value)) {
                $value = self::replaceObjectsInArray($value, $return);
                $array[$item] = $value;
            }

            if (is_object($value)) {
                if (method_exists($value, 'getId')) {
                    $array[$item] = $value->getId();
                } elseif (method_exists($value, 'getIdent')) {
                    $array[$item] = $value->getIdent();
                } else {
                    $array[$item] = 'removed Object';
                }
            }
        }

        return $array;
    }

    /**
     * Converts an object to an array
     * The attributes and values of the object will be stored in the resulting array.
     * The methods of the object will not be converted.
     *
     * @param object $object , which is to be converted
     *
     * @return array with properties of the given object as keys and the associated values
     *
     * @throws ReflectionException
     */
    private static function toArray($object)
    {
        if (null === $object) {
            return $object;
        }

        $array = [];
        $reflect = new ReflectionClass($object);
        $properties = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        foreach ($properties as $singleprop) {
            $key = $singleprop->getName();
            $method = 'get'.ucfirst($key);

            if (method_exists($object, $method)) {
                if ('id' === $key) {
                    $array['ident'] = $object->$method();
                }
                $array[$key] = $object->$method();
                continue;
            }
            $methodIs = 'is'.ucfirst($key);

            if (method_exists($object, $methodIs)) {
                $array[$key] = $object->$methodIs();
                continue;
            }
            $methodKey = ucfirst($key);
            if (method_exists($object, $methodKey)) {
                $array[$key] = $object->$methodKey();
                continue;
            }
        }

        return $array;
    }

    /**
     * Adds a value to a cache.
     *
     * @param string       $key
     * @param string|array $value
     * @param int          $ttl      TimeToLive in Seconds
     * @param bool         $override override existing variable
     */
    public static function cacheAdd($key, $value, $ttl = 0, $override = true): bool
    {
        if (function_exists('apcu_add')) {
            if ($override) {
                return apcu_store(self::getCachePrefix().$key, $value, $ttl);
            }

            return apcu_add(self::getCachePrefix().$key, $value, $ttl);
        }

        return false;
    }

    /**
     * Gets a value from cache.
     *
     * @param string $key
     *
     * @return mixed|false
     */
    public static function cacheGet($key)
    {
        if (function_exists('apcu_fetch')) {
            return apcu_fetch(self::getCachePrefix().$key);
        }

        return false;
    }

    /**
     * Checks whether a cache key exists.
     *
     * @param string $key
     */
    public static function cacheExists($key): bool
    {
        if (function_exists('apcu_exists')) {
            return apcu_exists(self::getCachePrefix().$key);
        }

        return false;
    }

    /**
     * Deletes all caches.
     */
    public static function cacheClear()
    {
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * Get an cache prefix that ist unique for the project to avoid cache clashes.
     *
     * @return string
     */
    protected static function getCachePrefix()
    {
        // $_SERVER may be used in this case as nothing else is available in
        // static context :(
        return md5((string) $_SERVER['DOCUMENT_ROOT']);
    }
}
