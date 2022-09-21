<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use function array_key_exists;
use function array_merge;
use function data_get;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use EDT\JsonApi\Schema\ContentField;

class Normalizer
{
    /**
     * @param string $json
     */
    public function normalize($json): TopLevel
    {
        $requestJson = Json::decodeToArray($json);

        $topLevel = new TopLevel();

        $normalizedData = [];
        if (array_key_exists(ContentField::DATA, $requestJson)) {
            $dataToNormalize = $requestJson[ContentField::DATA];
            if (array_key_exists(ContentField::DATA, $dataToNormalize)) {
                $dataToNormalize = $dataToNormalize[ContentField::DATA];
            }
            $normalizedData = array_merge($normalizedData, $this->extractItems($dataToNormalize, $topLevel));
        }

        if (array_key_exists(ContentField::META, $requestJson)) {
            $normalizedData[ContentField::META] = $requestJson[ContentField::META];
        }

        if (array_key_exists(ContentField::INCLUDED, $requestJson)) {
            $normalizedData = array_merge(
                $normalizedData,
                $this->extractItems($requestJson[ContentField::INCLUDED], $topLevel)
            );
        }

        $topLevel->setData($normalizedData);

        return $topLevel;
    }

    protected function extractItems(array $data, TopLevel $topLevel): array
    {
        $result = [];

        // first make sure that we're always working with an array of items
        if (array_key_exists(ContentField::TYPE, $data)) {
            $data = [$data];
        }

        foreach ($data as $item) {
            if (!array_key_exists(ContentField::TYPE, $item)) {
                throw new InvalidArgumentException('Try to normalize json in unexpected structure');
            }
            $type = $item[ContentField::TYPE];

            if (!array_key_exists($type, $result)) {
                $result[$type] = [];
            }

            if (array_key_exists(ContentField::RELATIONSHIPS, $item)) {
                /** @var array $relationship */
                foreach ($item[ContentField::RELATIONSHIPS] as $relationship) {
                    $relationshipType = data_get($relationship, [ContentField::DATA, ContentField::TYPE]);

                    if (null !== $relationshipType) {
                        $id = data_get($relationship, [ContentField::DATA, ContentField::ID]);
                        $result[$relationshipType][$id] = null;
                        continue;
                    }

                    if (null !== $relationship[ContentField::DATA]) {
                        // data may be null in case of an empty to-one relationship
                        /** @var array $relationshipIdentifier */
                        foreach ($relationship[ContentField::DATA] as $relationshipIdentifier) {
                            $result[$relationshipIdentifier[ContentField::TYPE]][$relationshipIdentifier[ContentField::ID]] = null;
                        }
                    }
                }
            }

            if (!array_key_exists(ContentField::ID, $item)) {
                // this is a create object
                // @improve T18070
                $topLevel->setObjectToCreate(new ResourceObject($item, $topLevel));

                continue;
            }

            $result[$type][$item[ContentField::ID]] = new ResourceObject($item, $topLevel);
        }

        return $result;
    }
}
