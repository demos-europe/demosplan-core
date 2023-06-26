<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use EDT\JsonApi\Schema\ContentField;
use EDT\JsonApi\Schema\ToManyResourceLinkage;
use Exception;

class ResourceLinkageFactory
{
    /**
     * Creates an instance from a JSON string directly taken from a request
     * manipulating a specific relationship. Eg.
     *
     * ```
     * '{
     *   "data": [
     *     { "type": "comments", "id": "123" }
     *   ]
     * }'
     * ```
     *
     * @throws Exception
     */
    public function createFromJsonRequestString(string $jsonString): ToManyResourceLinkage
    {
        $requestJson = Json::decodeToArray($jsonString);
        if (!is_array($requestJson)
            || !array_key_exists(ContentField::DATA, $requestJson)
            || 1 !== count($requestJson)
            || !is_array($requestJson[ContentField::DATA])) {
            throw new InvalidArgumentException('expected JSON object with \'data\' as only field containing an array');
        }

        return ToManyResourceLinkage::createFromArray($requestJson);
    }
}
