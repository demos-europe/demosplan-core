<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use InvalidArgumentException;

class ElasticSearchDefinitionProvider
{
    public function __construct(private readonly CurrentUserInterface $currentUser, private readonly GlobalConfigInterface $globalConfig)
    {
    }

    /**
     * @param string $entity      i.e. 'statementSegment'
     * @param string $function    allowed values: filter, sort, sort_default, search
     * @param string $accessGroup allowed values: all, intern, extern, planner
     *
     * @throws UserNotFoundException
     */
    public function getAvailableFields(string $entity, string $function, string $accessGroup): array
    {
        $availableFields = $this->getFields($entity, $function, $accessGroup);

        return $this->filterAvailableFields($availableFields);
    }

    /**
     * @return array<string, array>
     *
     * @throws InvalidArgumentException
     */
    private function getFields(string $entity, string $function, string $accessGroup): array
    {
        $esDefinitions = $this->globalConfig->getElasticsearchQueryDefinition();
        if (!isset($esDefinitions[$entity][$function][$accessGroup])) {
            $eMessage = "No definition found for entity $entity, function $function and accessGroup $accessGroup.";
            throw new InvalidArgumentException($eMessage);
        }

        return $esDefinitions[$entity][$function][$accessGroup];
    }

    /**
     * @param array<string, array> $fieldsList
     *
     * @return array<string, string>
     *
     * @throws UserNotFoundException
     */
    private function filterAvailableFields(array $fieldsList, string $keyPrefix = ''): array
    {
        $availableFields = [];
        foreach ($fieldsList as $key => $field) {
            if (!isset($field['permission']) || $this->currentUser->hasPermission($field['permission'])) {
                if (is_array($field)) {
                    $newPrefix = $keyPrefix.$key.'.';
                    $fields = $this->filterAvailableFields($field, $newPrefix);
                    foreach ($fields as $fieldKey => $fieldValue) {
                        $availableFields[$fieldKey] = $fieldValue;
                    }
                }
                if ('titleKey' === $key) {
                    $correctKey = rtrim($keyPrefix, '.');
                    $availableFields[$correctKey] = $field;
                }
            }
        }

        return $availableFields;
    }
}
