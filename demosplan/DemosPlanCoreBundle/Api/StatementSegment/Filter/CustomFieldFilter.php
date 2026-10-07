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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Narrows segments by their custom field values: `customField[<fieldId>][]=<optionId>`.
 * Any of a field's selected options matches, every field that has a selection must match.
 *
 * Needs the procedure to look the values up in, so the operation has to declare
 * `parentStatementOfSegment.procedure.id` as a parameter too, because API Platform only passes
 * this filter its own parameter value.
 */
final class CustomFieldFilter implements FilterInterface
{
    private const PROCEDURE_PARAMETER = 'parentStatementOfSegment.procedure.id';

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

        $procedureId = $operation?->getParameters()?->get(self::PROCEDURE_PARAMETER)?->getValue();
        if (!is_string($procedureId) || '' === $procedureId) {
            throw new BadRequestHttpException(sprintf('The customField filter requires "%s".', self::PROCEDURE_PARAMETER));
        }

        $segmentIds = $this->customFieldFilter->findMatchingSegmentIds($procedureId, $selectedCustomFields);
        if ([] === $segmentIds) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $parameterName = $queryNameGenerator->generateParameterName('customFieldMatchedIds');
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere($queryBuilder->expr()->in("$rootAlias.id", ":$parameterName"))
            ->setParameter($parameterName, $segmentIds);
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
