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

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

// @improve T21985
/**
 * It may be possible to bring this closer to the Symfony framework using
 * {@link https://symfony.com/doc/4.4/components/options_resolver.html}.
 *
 * @see documentation/development/application-architecture/json-api/search.md
 */
class SearchParams
{
    /**
     * @var string|null
     */
    private $searchPhrase = null;

    /**
     * @var array<int,string>|null
     */
    private $fieldsToSearch = null;

    /**
     * The identifier for the facet is used as both key and value.
     *
     * @var array<string, string>
     */
    private $facetKeys = [];

    /**
     * @param array<string,mixed> $searchParams
     */
    protected function __construct(array $searchParams)
    {
        foreach ($searchParams as $key => $value) {
            switch ($key) {
                case 'value':
                    $this->searchPhrase = $value;
                    break;
                case 'fieldsToSearch':
                    $this->fieldsToSearch = $value;
                    break;
                case 'facetKeys':
                    $this->facetKeys = $value;
                    break;
                default:
                    throw new InvalidArgumentException("Unsupported search parameter '$key'");
            }
        }
    }

    public static function createOptional(array $searchParams): ?self
    {
        if ([] === $searchParams || (array_key_exists('value', $searchParams) && '' === $searchParams['value'])) {
            return null;
        }

        return new self($searchParams);
    }

    public function getSearchPhrase(): ?string
    {
        return $this->searchPhrase;
    }

    /**
     * @return array<int,string>|null
     */
    public function getFieldsToSearch(): ?array
    {
        return $this->fieldsToSearch;
    }

    /**
     * @return array<string, string>
     */
    public function getFacetKeys(): array
    {
        return $this->facetKeys;
    }
}
