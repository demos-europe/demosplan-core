<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class EntityHelper
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Converts an object to an array
     * The attributes and values of the object will be stored in the resulting array.
     * The methods of the object will not be converted.
     *
     * @param object|array|null $object , which is to be converted
     *
     * @return array|null with properties of the given object as keys and the associated values
     *
     * @throws ReflectionException
     */
    public function toArray($object): ?array
    {
        if (null === $object) {
            return $object;
        }

        if (is_array($object)) {
            return $object;
        }

        $array = [];
        $reflect = new ReflectionClass($object);
        $properties = $reflect->getProperties(
            ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE
        );

        foreach ($properties as $singleprop) {
            $key = $singleprop->getName();
            $method = $this->findGetterMethodForPropertyName($key, $object);
            if ('getId' === $method) {
                $array['ident'] = $object->getId();
            }
            if ('getIdent' === $method) {
                $array['id'] = $object->getIdent();
            }
            if (null !== $method) {
                $array[$key] = $object->$method();
            }
        }

        return $array;
    }

    /**
     * Find a getter method in $object with a name similar to the property name given with $key.
     * For example: method names tested for the $key 'foobar' are (in this order)
     * <ol>
     * <li>getFoobar
     * <li>isFoobar
     * <li>hasFoobar
     * <li>canFoobar
     * <li>Foobar
     * </ol>
     * If none of these methods exist in $object then null is returned.
     *
     * @param string $key    the property name to search a getter method for
     * @param mixed  $object the object to search for a method with the tested names in
     *
     * @return string|null the first tested method name that exists in $object or null if none matched
     */
    public function findGetterMethodForPropertyName($key, $object): ?string
    {
        $method = 'get'.ucfirst($key);
        if (method_exists($object, $method)) {
            return $method;
        }

        $methodIs = 'is'.ucfirst($key);
        if (method_exists($object, $methodIs)) {
            return $methodIs;
        }

        $methodHas = 'has'.ucfirst($key);
        if (method_exists($object, $methodHas)) {
            return $methodHas;
        }

        $methodCan = 'can'.ucfirst($key);
        if (method_exists($object, $methodCan)) {
            return $methodCan;
        }

        $methodKey = ucfirst($key);
        if (method_exists($object, $methodKey)) {
            return $methodKey;
        }

        return null;
    }

    /**
     * Tries to extract the IDs from the given array of arrays|objects.
     *
     * @param array $arrayOfArrayOrEntities
     *
     * @return string[]
     */
    public function extractIds($arrayOfArrayOrEntities): array
    {
        $ids = [];
        foreach ($arrayOfArrayOrEntities as $arrayOrEntity) {
            $ids[] = $this->extractId($arrayOrEntity);
        }

        return $ids;
    }

    /**
     * Tries to extract an ID from the given array|$arrayOrObject.
     * If array is given without key 'id' or 'ident' will lead into an InvalidArgumentException.
     *
     * @param CoreEntity|array $arrayOrObject
     *
     * @return string|null
     *
     * @throws InvalidArgumentException
     */
    public function extractId($arrayOrObject)
    {
        try {
            if ($arrayOrObject instanceof CoreEntity) {
                if (method_exists($arrayOrObject, 'getId')) {
                    return $arrayOrObject->getId();
                }
                if (method_exists($arrayOrObject, 'getIdent')) {
                    return $arrayOrObject->getIdent();
                }

                throw new InvalidArgumentException('Given object has no Id getter');
            }

            if (is_array($arrayOrObject)) {
                return $this->extractIdFromEntityArray($arrayOrObject);
            }

            $type = gettype($arrayOrObject);

            throw new InvalidArgumentException("Given object is neither array nor CoreEntity but {$type}");
        } catch (Exception $e) {
            $this->logger->warning(
                'Unable to get ID from given arrayOrObject. ', [$e]);
            throw new InvalidArgumentException('Unable to get ID from given arrayOrObject. ', 0, $e);
        }
    }

    /**
     * Finds the ID of an entity in its array representation.
     *
     * @param array $entityArray a {@see CoreEntity} in its array representation
     *
     * @return string|null the ID as string or null if the ID is explicitly set as such
     *
     * @throws InvalidArgumentException if no ID was found or the ID is not of type string or null
     */
    protected function extractIdFromEntityArray(array $entityArray)
    {
        if (array_key_exists('id', $entityArray)) {
            $id = $entityArray['id'];
        } elseif (array_key_exists('ident', $entityArray)) {
            $id = $entityArray['ident'];
        } else {
            throw new InvalidArgumentException('Neither the key \'id\' nor \'ident\' exist in the given array');
        }

        if (null === $id || is_string($id)) {
            return $id;
        }

        $type = gettype($id);

        throw new InvalidArgumentException("ID is of unexpected type {$type}");
    }
}
