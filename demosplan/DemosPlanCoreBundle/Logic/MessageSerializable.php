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

/**
 * ViewObject for Messages.
 */
class MessageSerializable implements JsonSerializable, MessageSerializableInterface
{
    protected $severity = '';
    protected $text = '';
    protected $textParameters = [];

    /**
     * @param string $severity
     * @param string $text
     * @param array  $textParameters
     */
    public function __construct($severity, $text, $textParameters = [])
    {
        $this->severity = $severity;
        $this->text = $text;
        $this->textParameters = $textParameters;
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

    /**
     * @return string
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * @param string $severity
     *
     * @return MessageSerializable
     */
    public function setSeverity($severity)
    {
        $this->severity = $severity;

        return $this;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param string $text
     *
     * @return MessageSerializable
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    public function __toString()
    {
        return $this->text;
    }

    /**
     * @return array
     */
    public function getTextParameters()
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
