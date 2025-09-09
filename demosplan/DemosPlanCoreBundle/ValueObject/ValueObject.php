<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use DemosEurope\DemosplanAddon\Contracts\ValueObject\ValueObjectInterface;
use demosplan\DemosPlanCoreBundle\Exception\ValueObjectException;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class ValueObject implements JsonSerializable, ValueObjectInterface
{
    public const ACCESSOR_REGEX = '/(get|set)?(.+)/';

    public const TWIG_LOCATION = 'vendor'.DIRECTORY_SEPARATOR.'twig'.DIRECTORY_SEPARATOR.'twig';

    /**
     * Setters can only be used when ValueObject is not locked
     * Getters can only be used when ValueObject is locked.
     *
     * @var bool
     */
    private $locked = false;

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function setProperty($name, $value): self
    {
        $this->verifySettability($name);

        $this->{$name} = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getProperty(?string $name)
    {
        $this->checkIfLocked();

        if (!property_exists($this, $name)) {
            throw ValueObjectException::unknownProperty($name, static::class);
        }

        return $this->{$name};
    }

    protected function checkIfLocked(): void
    {
        if (!$this->locked) {
            throw ValueObjectException::mustLockFirst();
        }
    }

    /**
     * Returns either this instance when setting or the value to get on getting.
     *
     * @param mixed[]|array $arguments
     *
     * @return $this|mixed
     */
    public function __call(string $name, array $arguments = [])
    {
        preg_match(self::ACCESSOR_REGEX, $name, $match);

        // First item is complete $name, second set|get, third the property name
        if (3 !== count($match)) {
            throw ValueObjectException::failedToParseAccessor($name);
        }

        $property = lcfirst($match[2]);

        $backTrace = debug_backtrace(
            DEBUG_BACKTRACE_IGNORE_ARGS,
            1
        );

        // if we're called from inside a twig template, only allow getting values
        if (0 < strpos($backTrace[0]['file'], self::TWIG_LOCATION)) {
            if (0 < strlen($match[1])) {
                throw ValueObjectException::noAccessorAllowedFromTwig();
            }

            return $this->getProperty($match[2]);
        }

        switch ($match[1]) {
            case 'set':
                if (1 !== count($arguments)) {
                    throw ValueObjectException::mustProvideArgument($arguments);
                }

                return $this->setProperty($property, $arguments[0]);

            case 'get':
                return $this->getProperty($property);

            default:
                throw ValueObjectException::unknownAccessorPrefix($match[1]);
        }
    }

    /**
     * ValueObject needs to be locked in order to read values.
     *
     * @return $this
     */
    public function lock(): ValueObjectInterface
    {
        $this->locked = true;

        return $this;
    }

    /**
     * Creates a ReflectionClass and returns an array with all properties
     * as keys and their values as values.
     *
     * note: the property lock is skipped
     */
    public function jsonSerialize(): array
    {
        try {
            $reflection = new ReflectionClass($this);

            return collect($reflection->getProperties(ReflectionProperty::IS_PROTECTED))
                ->flatMap(
                    fn(ReflectionProperty $property) => [$property->getName() => $this->{$property->getName()}]
                )
                ->toArray();
        } catch (ReflectionException) {
            // this can in theory only happen if reflection is disabled
            // if so, other things will have broken long before we arrive here
            // thus returning an empty array to keep type safety is "good enough"
            return [];
        }
    }

    protected function verifySettability(string $name): void
    {
        if ($this->locked) {
            throw ValueObjectException::noChangeAllowedWhenLocked();
        }

        if (!property_exists($this, $name)) {
            throw ValueObjectException::unknownProperty($name, static::class);
        }
    }
}
