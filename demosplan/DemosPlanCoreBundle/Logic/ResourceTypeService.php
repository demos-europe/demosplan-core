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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\ResourceTypeServiceInterface;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertyUpdateAccessException;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Contracts\AccessException;
use Exception;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function array_key_exists;

class ResourceTypeService implements ResourceTypeServiceInterface
{
    final public const VALIDATION_GROUP_DEFAULT = 'Default';

    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * Assumes that for every given property name a corresponding
     * setter method (`'set'` concatenated with the upper-cased property name) exists that accepts the given property value.
     *
     * It is also assumed that relationships are unidirectional, meaning that the value is only
     * set at the given object.
     *
     * You probably want to call {@link ResourceTypeService::validateObject()} afterwards.
     *
     * @param object              $object     the setter methods of this object are invoked to set properties
     * @param array<string,mixed> $properties the keys indicate the setters to be used; the values
     *                                        will be passed directly when the setter of the property
     *                                        is invoked, no validation or transformation is done
     *
     * @throws Exception
     */
    public function updateObjectNaive(object $object, array $properties): void
    {
        foreach ($properties as $propertyName => $propertyValue) {
            $setter = 'set'.ucfirst($propertyName);
            $object->$setter($propertyValue);
        }
    }

    /**
     * Checks if all the keys in the given $properties are present as keys in $allowedProperties.
     *
     * @param array<string,mixed>       $properties        the properties that must be present in the other array
     * @param array<string,string|null> $allowedProperties only the keys matter, values are ignored
     *
     * @throws PropertyUpdateAccessException
     */
    public function checkWriteAccess(ResourceTypeInterface $type, array $properties, array $allowedProperties): void
    {
        foreach ($properties as $propertyName => $propertyValue) {
            if (!is_string($propertyName)) {
                throw PropertyUpdateAccessException::intPropertyKey($type, $propertyName);
            }
            if (!array_key_exists($propertyName, $allowedProperties)) {
                $propertyNames = array_keys($allowedProperties);
                throw PropertyUpdateAccessException::notAvailable($type, $propertyName, ...$propertyNames);
            }
        }
    }

    /**
     * Checks if all the given keys in $requiredProperties are present as keys in the given $properties.
     *
     * @param array<string,mixed>       $properties
     * @param array<string,string|null> $requiredProperties
     *
     * @throws AccessException thrown if one or more required properties are not present
     */
    public function checkRequiredProperties(ResourceTypeInterface $type, array $properties, array $requiredProperties): void
    {
        $missingProperties = array_diff_key($requiredProperties, $properties);
        if (0 !== count($missingProperties)) {
            $missingPropertiesString = implode(',', array_keys($missingProperties));

            throw new AccessException($type, "The following properties are required but were not provided when creating a new {$type->getTypeName()} resource: $missingPropertiesString");
        }
    }

    public function validateObject(object $entity, array $groups = null): void
    {
        $violationList = $this->validator->validate($entity, null, $groups);
        if (0 < $violationList->count()) {
            throw ViolationsException::fromConstraintViolationList($violationList);
        }
    }
}
