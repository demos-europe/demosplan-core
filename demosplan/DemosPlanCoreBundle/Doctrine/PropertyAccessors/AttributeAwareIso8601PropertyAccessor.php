<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Doctrine\PropertyAccessors;

use Carbon\Carbon;
use DateTimeInterface;
use Doctrine\Persistence\ObjectManager;
use EDT\DqlQuerying\PropertyAccessors\Iso8601PropertyAccessor;
use ReflectionProperty;

/**
 * Replacement for {@see Iso8601PropertyAccessor} that resolves the Doctrine column
 * type via {@see ObjectManager::getClassMetadata()} instead of the
 * {@see \Doctrine\Common\Annotations\AnnotationReader}.
 *
 * Why: EDT's AnnotationReader only parses docblock annotations and ignores PHP 8
 * attributes. After converting all entities to `#[ORM\Column(...)]` the upstream
 * accessor stops adjusting `datetime` fields, so raw DateTime instances reach the
 * EDT transformer and fail serialization.
 */
class AttributeAwareIso8601PropertyAccessor extends Iso8601PropertyAccessor
{
    public function __construct(ObjectManager $objectManager)
    {
        parent::__construct($objectManager);
    }

    protected function adjustReturnValue(mixed $value, ReflectionProperty $reflectionProperty): mixed
    {
        if (null === $value || !$value instanceof DateTimeInterface) {
            return $value;
        }

        $declaringClass = $reflectionProperty->getDeclaringClass()->getName();
        if ($this->objectManager->getMetadataFactory()->isTransient($declaringClass)) {
            return $value;
        }

        $fieldType = $this->objectManager
            ->getClassMetadata($declaringClass)
            ->getTypeOfField($reflectionProperty->getName());

        if ('datetime' === $fieldType) {
            return Carbon::instance($value)->toIso8601String();
        }

        return $value;
    }
}
