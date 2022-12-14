<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Rpc;

use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use JsonException;
use JsonSchema\Exception\InvalidSchemaException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

class RpcValidator
{
    /**
     * Path to the file that defines a generic JSON-RPC conform schema for a request.
     */
    public const RPC_JSON_SCHEMA_PATH_REQUEST = 'demosplan/DemosPlanCoreBundle/Resources/config/json-schemas/rpc-schema-request.json';

    /**
     * Path to the file that defines a generic JSON-RPC schema for a result.
     */
    public const RPC_JSON_SCHEMA_PATH_RESULT = 'demosplan/DemosPlanCoreBundle/Resources/config/json-schemas/rpc-schema-result.json';

    /**
     * @var JsonSchemaValidator
     */
    public $jsonSchemaValidator;

    public function __construct(JsonSchemaValidator $jsonSchemaValidator)
    {
        $this->jsonSchemaValidator = $jsonSchemaValidator;
    }

    /**
     * Validates that a string fits into Standard Json RPC Request and, if provided, also to a
     * specific Json RPC definition.
     *
     * @param string $schemaPath - If not empty, RPC Json Schema path specific for a given service request
     *
     * @throws InvalidSchemaException
     * @throws JsonException
     */
    public function validateRpcJsonRequest(string $json, string $schemaPath = ''): void
    {
        $this->jsonSchemaValidator->validate($json, DemosPlanPath::getRootPath(self::RPC_JSON_SCHEMA_PATH_REQUEST));
        if ('' !== $schemaPath) {
            $this->jsonSchemaValidator->validate($json, $schemaPath);
        }
    }
}
