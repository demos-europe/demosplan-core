<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use JsonSchema\Exception\InvalidSchemaException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Validates that Drafts Info format. If it does not fit the expected schema
 * throws an Exception.
 *
 * Class DraftsInfoValidator
 */
class DraftsInfoValidator
{
    /**
     * @var JsonSchemaValidator
     */
    private $jsonValidator;

    /**
     * @var string
     */
    private $schemaFilePath;

    public function __construct(JsonSchemaValidator $jsonValidator, string $schemaFilePath)
    {
        $this->jsonValidator = $jsonValidator;
        $this->schemaFilePath = $schemaFilePath;
    }

    /**
     * @throws FileNotFoundException
     * @throws InvalidSchemaException
     */
    public function validate(string $draftsInfo): void
    {
        $this->jsonValidator->validate($draftsInfo, $this->schemaFilePath);
    }
}
