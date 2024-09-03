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
use EDT\Wrapping\Contracts\ContentField;
use Exception;

use function array_key_exists;
use function is_array;
use function is_string;

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
     * @return array{data: list<array{type: non-empty-string, id: non-empty-string}>}
     *
     * @throws Exception
     */
    public function createFromJsonRequestString(string $jsonString): array
    {
        $requestJson = Json::decodeToArray($jsonString);
        if (!array_key_exists(ContentField::DATA, $requestJson)
            || 1 !== count($requestJson)
            || !is_array($requestJson[ContentField::DATA])) {
            throw new InvalidArgumentException('expected JSON object with \'data\' as only field containing an array');
        }

        array_map($this->validateItem(...), $requestJson[ContentField::DATA]);

        return $requestJson;
    }

    protected function validateItem(array $content): void
    {
        if (!array_key_exists(ContentField::ID, $content) || !array_key_exists(ContentField::TYPE, $content)) {
            $providedKeys = implode(',', array_keys($content));
            throw new InvalidArgumentException("\$content MUST provide 'type' and 'id', found the following keys: {$providedKeys}");
        }
        if (array_key_exists(ContentField::META, $content)) {
            throw new InvalidArgumentException('meta can not be validated yet hence it is not accepted at all');
        }
        $id = $content[ContentField::ID];
        if (!is_string($id)) {
            throw new InvalidArgumentException('id is not given as string');
        }
        $type = $content[ContentField::TYPE];
        if (!is_string($type)) {
            throw new InvalidArgumentException('type is not given as string');
        }
    }
}
