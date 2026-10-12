<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint\CustomFieldOptionSelection;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldExportColumnKeyCodec;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\RequestHandling\UrlParameter;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Lets the segment export understand custom field filters.
 *
 * The FE sends one condition per selected option in the `filter` parameter, like for tags:
 * path `customField_<fieldId>`, value `<optionId>`. Any selected option of a field matches,
 * every field with a selection must match.
 */
class SegmentExportCustomFieldFilter
{
    private const PERMISSION = 'field_segments_custom_fields';

    public function __construct(
        private readonly DqlConditionFactory $conditionFactory,
        private readonly PermissionsInterface $permissions,
        private readonly StatementSegmentResourceType $segmentResourceType,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $filter the `filter` parameter of the request
     */
    public function hasCustomFieldConditions(array $filter): bool
    {
        foreach ($filter as $entry) {
            if (null !== $this->getCustomFieldId($entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Takes the custom field conditions out of the `filter` parameter of the query, because the
     * resource type does not know them, and returns them as conditions instead.
     * Users without the permission for custom fields get no filtering instead of an error.
     *
     * @return list<ClauseFunctionInterface<bool>>
     *
     * @throws BadRequestHttpException if a condition is invalid or an option does not belong to its field
     */
    public function extractConditions(ParameterBag $query): array
    {
        if (!$query->has(UrlParameter::FILTER)) {
            return [];
        }

        $remainingFilter = [];
        $selections = [];
        foreach ($query->all(UrlParameter::FILTER) as $key => $entry) {
            $fieldId = $this->getCustomFieldId($entry);
            if (null === $fieldId) {
                $remainingFilter[$key] = $entry;
                continue;
            }
            $selections[$fieldId][] = $this->getOptionId($entry['condition']);
        }

        if ([] === $selections) {
            return [];
        }

        if ([] === $remainingFilter) {
            $query->remove(UrlParameter::FILTER);
        } else {
            $query->set(UrlParameter::FILTER, $remainingFilter);
        }

        if (!$this->permissions->hasPermission(self::PERMISSION)) {
            return [];
        }

        $violations = $this->validator->validate($selections, new CustomFieldOptionSelection());
        if ($violations->count() > 0) {
            throw new BadRequestHttpException($violations->get(0)->getMessage());
        }

        return $this->createConditions($selections);
    }

    /**
     * @param array<array-key, list<string>> $selections option ids by custom field id
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    private function createConditions(array $selections): array
    {
        $conditions = [];
        foreach ($selections as $optionIds) {
            $conditions[] = $this->conditionFactory->anyConditionApplies(...array_map(
                fn (string $optionId) => $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue(
                    CustomFieldValuesList::getOptionSearchText($optionId),
                    $this->segmentResourceType->customFields
                ),
                $optionIds
            ));
        }

        return $conditions;
    }

    private function getCustomFieldId(mixed $entry): ?string
    {
        $path = $entry['condition']['path'] ?? null;

        return is_string($path) ? CustomFieldExportColumnKeyCodec::extractId($path) : null;
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function getOptionId(array $condition): string
    {
        if ('=' !== ($condition['operator'] ?? '=')) {
            throw new BadRequestHttpException('Custom field conditions only support the operator "=".');
        }

        $optionId = $condition['value'] ?? null;
        if (!is_string($optionId) || '' === $optionId) {
            throw new BadRequestHttpException('Custom field conditions need an option id as value.');
        }

        return $optionId;
    }
}
