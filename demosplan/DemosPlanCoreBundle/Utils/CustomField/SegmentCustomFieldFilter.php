<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Finds the segments whose SEGMENT custom field values match a selection of options.
 * Within one custom field any selected option matches, across custom fields every field must match.
 *
 * Matching happens in PHP on purpose: the values are stored in a JSON column (a scalar for
 * single select, a list for multi select) and the test database (SQLite) has no JSON_CONTAINS.
 * Callers restrict their query to the returned ids.
 */
class SegmentCustomFieldFilter
{
    /**
     * Query parameter key, e.g. `customField[<fieldId>][]=<optionId>`.
     */
    public const FILTER_KEY = 'customField';

    private const PERMISSION = 'field_segments_custom_fields';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionsInterface $permissions,
    ) {
    }

    /**
     * The query parameter filter ignores selections of users who may not see the custom fields,
     * rather than rejecting the request. Bulk edit and exports are gated by their own permissions.
     */
    public function isFilteringAllowed(): bool
    {
        return $this->permissions->hasPermission(self::PERMISSION);
    }

    /**
     * Validates the raw query parameter value.
     *
     * @return array<string, list<string>> fieldId => selected option ids; empty when nothing is selected
     *
     * @throws BadRequestHttpException if the value does not have the shape `[<fieldId> => [<optionId>, ...]]`
     */
    public function parseQuery(mixed $raw): array
    {
        if (null === $raw || [] === $raw) {
            return [];
        }

        if (!is_array($raw)) {
            throw new BadRequestHttpException('Invalid customField filter.');
        }

        $selections = [];
        foreach ($raw as $fieldId => $optionIds) {
            if (!is_string($fieldId) || !Uuid::isValid($fieldId)) {
                throw new BadRequestHttpException('Invalid customField filter key.');
            }

            $optionIds = array_values(array_filter(
                (array) $optionIds,
                static fn (mixed $optionId): bool => is_string($optionId) && '' !== $optionId
            ));

            if ([] !== $optionIds) {
                $selections[$fieldId] = $optionIds;
            }
        }

        return $selections;
    }

    /**
     * Splits the custom field conditions (see {@see CustomFieldFilterPathCodec}) out of a
     * Drupal-style filter, as sent by the segment list for bulk edit and exports.
     *
     * @param array<string, mixed> $filter
     *
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>} the filter without those conditions, the selections
     */
    public function extractFromDrupalFilter(array $filter): array
    {
        $selections = [];
        foreach ($filter as $key => $entry) {
            $path = is_array($entry) ? ($entry['condition']['path'] ?? null) : null;
            $value = is_array($entry) ? ($entry['condition']['value'] ?? null) : null;
            $fieldId = is_string($path) ? CustomFieldFilterPathCodec::extractId($path) : null;

            if (null !== $fieldId && is_string($value)) {
                $selections[$fieldId][] = $value;
                unset($filter[$key]);
            }
        }

        return [$filter, $this->parseQuery($selections)];
    }

    /**
     * @param array<string, list<string>> $selections {@see self::parseQuery()}
     *
     * @return list<string> ids of the segments of the procedure that match the selections
     */
    public function findMatchingSegmentIds(string $procedureId, array $selections): array
    {
        $rows = $this->entityManager->createQuery(
            'SELECT s.id AS id, s.customFields AS customFields FROM '.Segment::class.' s
             WHERE s.procedure = :procedureId AND s.customFields IS NOT NULL'
        )->setParameter('procedureId', $procedureId)->toIterable();

        $ids = [];
        foreach ($rows as $row) {
            if ($this->holdsAllSelections($row['customFields'], $selections)) {
                $ids[] = $row['id'];
            }
        }

        return $ids;
    }

    /**
     * @param array<string, list<string>> $selections
     */
    private function holdsAllSelections(?CustomFieldValuesList $values, array $selections): bool
    {
        foreach ($selections as $fieldId => $selectedOptionIds) {
            $heldOptionIds = $values?->getOptionIds($fieldId) ?? [];

            if ([] === array_intersect($selectedOptionIds, $heldOptionIds)) {
                return false;
            }
        }

        return true;
    }
}
