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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldOption;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Helpers to filter segments by their SEGMENT custom field values (select fields, by option id).
 * Within one custom field any selected option matches, across custom fields every field must match.
 *
 * The values are stored as JSON text like `[{"id":"<fieldId>","value":"<optionId>"|["<optionId>"]}]`.
 * Option ids are UUIDs, so a segment holds an option if its text contains `"<optionId>"`.
 */
class SegmentCustomFieldFilter
{
    /**
     * Query parameter key, e.g. `customField[<fieldId>][]=<optionId>`.
     */
    public const FILTER_KEY = 'customField';

    private const PERMISSION = 'field_segments_custom_fields';

    public function __construct(
        private readonly CustomFieldProvider $customFieldProvider,
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
     * Checks that every selected option belongs to the custom field it is selected for, which also
     * rejects unknown fields and fields without options (text). The matching itself only looks for
     * the option id, so without this a field id would be accepted that has nothing to do with the option.
     * The rest of the shape is checked by the `constraints` of the `customField` parameter in the resource.
     *
     * @param array<mixed> $selections
     *
     * @phpstan-assert array<string, list<string>> $selections
     *
     * @throws BadRequestHttpException if an option does not belong to its field
     */
    public function assertValidSelections(array $selections): void
    {
        $customFields = $this->customFieldProvider->getCustomFieldsByIds(array_map('strval', array_keys($selections)));

        foreach ($selections as $fieldId => $selectedOptionIds) {
            $customField = $customFields[$fieldId] ?? null;

            foreach ($selectedOptionIds as $optionId) {
                if (!$customField?->getCustomOptionValueById($optionId) instanceof CustomFieldOption) {
                    throw new BadRequestHttpException('Invalid customField filter selection.');
                }
            }
        }
    }

    /**
     * LIKE pattern that matches the stored JSON of segments holding the option.
     * The option id must be a UUID (checked by the resource constraints), so it holds no wildcards.
     */
    public function getOptionLikePattern(string $optionId): string
    {
        return '%"'.$optionId.'"%';
    }
}
