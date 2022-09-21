<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validate;

use demosplan\DemosPlanCoreBundle\Utilities\Json;
use JsonException;
use JsonSchema\Exception\InvalidSchemaException;
use JsonSchema\Validator;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class JsonSchemaValidator
{
    /**
     * @throws InvalidSchemaException On invalid content
     * @throws JsonException          On invalid schema or json object file
     */
    public function validate(string $json, string $schemaPath): void
    {
        if (!$jsonSchema = file_get_contents($schemaPath)) {
            throw new FileNotFoundException('File not found in path: '.$schemaPath);
        }

        $validator = new Validator();

        $schemaObject = Json::decodeToMatchingType($jsonSchema);
        $jsonObject = Json::decodeToMatchingType($json);

        $validator->validate($jsonObject, $schemaObject);

        if (!$validator->isValid()) {
            $errorMsgs = [];

            foreach ($validator->getErrors() as $error) {
                $errorMsg = 'Json Schema Error. Field "'.$error['property'].'". ';
                $errorMsg .= $error['message'];
                $errorMsgs[] = $errorMsg;
            }

            $errorMsg = implode("\n", $errorMsgs);
            throw new InvalidSchemaException($errorMsg);
        }
    }
}
