<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use UnexpectedValueException;

class ServiceImporterException extends UnexpectedValueException
{
    protected $errorParagraphs = [];

    /**
     * @return array
     */
    public function getErrorParagraphs()
    {
        return $this->errorParagraphs;
    }

    /**
     * @param array $errorParagraphs
     */
    public function setErrorParagraphs($errorParagraphs)
    {
        $this->errorParagraphs = $errorParagraphs;
    }

    /**
     * @param string $errorParagraph
     */
    public function addErrorParagraph($errorParagraph)
    {
        $this->errorParagraphs[] = $errorParagraph;
    }
}
