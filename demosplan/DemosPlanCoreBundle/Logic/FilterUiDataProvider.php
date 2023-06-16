<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilter;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

class FilterUiDataProvider
{
    /**
     * @var string
     */
    private $relativeFilterNamesPath;

    public function __construct()
    {
        $this->relativeFilterNamesPath = 'segmentsFilterNames.yaml';
    }

    /**
     * @return array<string,mixed> The format specified by {@link FilterNamesConfiguration}
     */
    public function getFilterNames(): array
    {
        $filterNames = Yaml::parseFile(DemosPlanPath::getConfigPath($this->relativeFilterNamesPath));
        $processor = new Processor();
        $filterNamesConfiguration = new FilterNamesConfiguration();

        return $processor->processConfiguration($filterNamesConfiguration, $filterNames);
    }

    /**
     * Adds the information if the `rootPath` in the given filter names configuration is in any way
     * in use in the given filter array. The information is added using a `selected` field that is
     * added to each item in the given array.
     *
     * @param array<string,mixed>                             $filterNames The format specified by
     *                                                                     {@link FilterNamesConfiguration}
     * @param array<string,array<string,array<string,mixed>>> $rawFilter
     *
     * @return array<string,mixed> the input array with the `selected` property added to each item
     *
     * @throws DrupalFilterException
     */
    public function addSelectedField(array $filterNames, array $rawFilter): array
    {
        $filter = new DrupalFilter($rawFilter);

        return array_map(function (array $filterName) use ($filter) {
            $filterName['selected'] = $this->isPathInUse($filterName['rootPath'], $filter);

            return $filterName;
        }, $filterNames);
    }

    /**
     * Checks if the given path is in any way used in any condition in the given
     * filter.
     */
    private function isPathInUse(string $path, DrupalFilter $filter): bool
    {
        return collect($filter->getGroupedConditions())
            ->flatMap(
                static fn(array $conditionGroup): array => $conditionGroup
            )->filter(
                static fn(array $condition) => array_key_exists('path', $condition)
            )->pluck('path')->containsStrict($path);
    }
}
