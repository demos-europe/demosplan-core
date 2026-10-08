<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\SegmentCustomFieldFilter;
use Doctrine\ORM\QueryBuilder;

/**
 * Narrows segments by their custom field values: `customField[<fieldId>][]=<optionId>`.
 * Any of a field's selected options matches, every field that has a selection must match.
 */
final class CustomFieldFilter implements FilterInterface
{
    public function __construct(private readonly SegmentCustomFieldFilter $customFieldFilter)
    {
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!$this->customFieldFilter->isFilteringAllowed()) {
            return;
        }

        $selectedCustomFields = $context['parameter']?->getValue();

        // The parameter may not be present, so there is nothing to filter by.
        if ([] === $selectedCustomFields
            || null === $selectedCustomFields
            || $selectedCustomFields instanceof ParameterNotFound) {
            return;
        }

        $this->customFieldFilter->assertValidSelections($selectedCustomFields);

        $rootAlias = $queryBuilder->getRootAliases()[0];
        foreach ($selectedCustomFields as $optionIds) {
            $anyOption = $queryBuilder->expr()->orX();
            foreach ($optionIds as $optionId) {
                $parameterName = $queryNameGenerator->generateParameterName('customFieldOption');
                $anyOption->add("$rootAlias.customFields LIKE :$parameterName");
                $queryBuilder->setParameter($parameterName, $this->customFieldFilter->getOptionLikePattern($optionId));
            }
            $queryBuilder->andWhere($anyOption);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            SegmentCustomFieldFilter::FILTER_KEY.'[<customFieldId>][]' => [
                'property'      => 'customFields',
                'type'          => 'string',
                'required'      => false,
                'is_collection' => true,
                'description'   => 'Option ids of one custom field. Any selected option of a field matches; all fields with a selection must match.',
            ],
        ];
    }
}
