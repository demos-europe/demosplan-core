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
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Narrows segments by their custom field values: `customField[<fieldId>][]=<optionId>`.
 * Any of a field's selected options matches, every field that has a selection must match.
 */
final class CustomFieldFilter implements FilterInterface
{
    private const PARAMETER_NAME = 'customField';
    private const PERMISSION = 'field_segments_custom_fields';

    public function __construct(private readonly PermissionsInterface $permissions)
    {
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        // Users who may not see the custom fields get no filtering instead of an error.
        if (!$this->permissions->hasPermission(self::PERMISSION)) {
            return;
        }

        $selectedCustomFields = $context['parameter']?->getValue();

        // The parameter may not be present, so there is nothing to filter by.
        if ([] === $selectedCustomFields
            || null === $selectedCustomFields
            || $selectedCustomFields instanceof ParameterNotFound) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        // Add one condition to the query per custom field, which matches if the segment has any of its selected options.
        foreach ($selectedCustomFields as $optionIds) {
            $anyOption = $queryBuilder->expr()->orX();
            foreach ($optionIds as $optionId) {
                $parameterName = $queryNameGenerator->generateParameterName('customFieldOption');
                $anyOption->add("$rootAlias.customFields LIKE :$parameterName");
                // The values are stored as JSON text, so a segment holds the option if its text contains the quoted id.
                $queryBuilder->setParameter($parameterName, '%"'.$optionId.'"%');
            }
            $queryBuilder->andWhere($anyOption);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PARAMETER_NAME.'[<customFieldId>][]' => [
                'property'      => 'customFields',
                'type'          => 'string',
                'required'      => false,
                'is_collection' => true,
                'description'   => 'Option ids of one custom field. Any selected option of a field matches; all fields with a selection must match.',
            ],
        ];
    }
}
