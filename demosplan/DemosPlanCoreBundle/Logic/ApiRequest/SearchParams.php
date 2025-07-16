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

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\JsonApiEsServiceInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

use function array_key_exists;

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
    private $searchPhrase;

    /**
     * @var array<int,non-empty-string>|null
     */
    private $fieldsToSearch;

    /**
     * The identifier for the facet is used as both key and value.
     *
     * @var array<string, string>
     */
    private $facetKeys = [];

    /**
     * @param array{value?: string, fieldsToSearch?: array<int,non-empty-string>, facetKeys?: array<string, string>} $searchParams
     */
    public function __construct(array $searchParams)
    {
        foreach ($searchParams as $key => $value) {
            switch ($key) {
                case JsonApiEsServiceInterface::VALUE:
                    $this->searchPhrase = $value;
                    break;
                case JsonApiEsServiceInterface::FIELDS_TO_SEARCH:
                    $this->fieldsToSearch = $value;
                    break;
                case JsonApiEsServiceInterface::FACET_KEYS:
                    $this->facetKeys = $value;
                    break;
                default:
                    throw new InvalidArgumentException("Unsupported search parameter '$key'");
            }
        }
    }

    /**
     * @param array{value?: string, fieldsToSearch?: array<int,string>, facetKeys?: array<string, string>} $searchParams
     */
    public static function createOptional(array $searchParams): ?self
    {
        if ([] === $searchParams || (array_key_exists(JsonApiEsServiceInterface::VALUE, $searchParams) && '' === $searchParams[JsonApiEsServiceInterface::VALUE])) {
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
