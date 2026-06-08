<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\MessageSerializableInterface;
use JsonSerializable;
use Stringable;

/**
 * ViewObject for Messages.
 */
class MessageSerializable implements JsonSerializable, MessageSerializableInterface, Stringable
{
    /**
     * @param string $severity
     * @param string $text
     * @param array  $textParameters
     */
    public function __construct(protected $severity, protected $text, protected $textParameters = [])
    {
    }

    /**
     * @param string $severity
     * @param string $text           #TranslationKey
     * @param array  $textParameters
     *
     * @return MessageSerializable
     */
    public static function createMessage($severity, $text, $textParameters = [])
    {
        return new self($severity, $text, $textParameters);
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * @param string $severity
     */
    public function setSeverity($severity): MessageSerializableInterface
    {
        $this->severity = $severity;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @param string $text
     */
    public function setText($text): MessageSerializableInterface
    {
        $this->text = $text;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->text;
    }

    public function getTextParameters(): array
    {
        return $this->textParameters;
    }

    /**
     * @param array $textParameters
     */
    public function setTextParameters($textParameters)
    {
        $this->textParameters = $textParameters;
    }

    public function jsonSerialize(): array
    {
        return ['message' => $this->text, 'severity' => $this->severity];
    }
}
