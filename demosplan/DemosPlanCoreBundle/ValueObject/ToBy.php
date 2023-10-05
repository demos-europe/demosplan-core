<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use ArrayAccess;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * This class is to be used instead of creating an `array{to: string, by: string}` manually.
 *
 * Using this class allows the IDE to keep track of the creations. This prepares
 * the code for successive refactoring of the arrays into proper objects.
 *
 * The name of the `ToBy` object is for now considered temporary and can
 * be changed later to something more appropriate if necessary.
 */
class ToBy implements ArrayAccess, Stringable
{
    final public const DIRECTION_ASC = 'asc';
    final public const DIRECTION_DESC = 'desc';

    public function __construct(private $propertyName, private $direction)
    {
    }

    /**
     * @return array{to: string, by: string}
     */
    public static function createArray($propertyName, $direction): array
    {
        return [
            'to' => $direction,
            'by' => $propertyName,
        ];
    }

    public static function createEmptyArray(): array
    {
        return [];
    }

    public static function create($propertyName, $direction): self
    {
        return new self($propertyName, $direction);
    }

    public static function createFromArray(array $array, string $defaultPropertyName, string $defaultDirection = self::DIRECTION_ASC): self
    {
        return new self(
            $array['by'] ?? $defaultPropertyName,
            $array['to'] ?? $defaultDirection
        );
    }

    public static function createFromString(string $sort): self
    {
        $direction = self::DIRECTION_ASC;
        $propertyName = $sort;

        if (str_starts_with($sort, '-')) {
            $direction = self::DIRECTION_DESC;
            $propertyName = substr($sort, 1);
        }

        return self::create($propertyName, $direction);
    }

    public function setPropertyName($name): void
    {
        $this->propertyName = $name;
    }

    public function setDirection($direction): void
    {
        $this->direction = $direction;
    }

    public function offsetExists($offset): bool
    {
        return 'to' === $offset || 'by' === $offset;
    }

    public function offsetGet($offset): mixed
    {
        if ('to' === $offset) {
            return $this->direction;
        }

        if ('by' === $offset) {
            return $this->propertyName;
        }

        throw new InvalidArgumentException("Unknown offset: $offset");
    }

    public function offsetSet($offset, $value): void
    {
        if ('to' === $offset) {
            $this->direction = $value;
        }

        if ('by' === $offset) {
            $this->propertyName = $value;
        }

        throw new InvalidArgumentException("Unknown offset: $offset");
    }

    public function offsetUnset($offset): void
    {
        throw new LogicException("Can't unset offset: $offset");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return self::createArray($this->propertyName, $this->direction);
    }

    public function getDirection()
    {
        return $this->direction;
    }

    public function getPropertyName()
    {
        return $this->propertyName;
    }

    public function __toString(): string
    {
        $direction = '';
        if (self::DIRECTION_DESC === $this->direction) {
            $direction = '-';
        }

        return $direction.$this->propertyName;
    }
}
