/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import type { CustomFieldDefinition } from '@DpJs/types/customFields'

/**
 * Turns the stored value of a custom field into the labels to display.
 * Select fields store option IDs (a string or a list of strings), text fields store the text itself.
 * Option IDs that no longer exist in the definition are dropped.
 */
export function resolveCustomFieldLabels (
  definition: Pick<CustomFieldDefinition, 'attributes'>,
  value: string | string[] | null | undefined,
): string[] {
  if (value === null || value === undefined || value === '') {
    return []
  }

  if (definition.attributes.fieldType === 'text') {
    return [String(value)]
  }

  const optionIds = Array.isArray(value) ? value : [value]

  return optionIds
    .map(optionId => definition.attributes.options.find(option => option.id === optionId)?.label)
    .filter((label): label is string => Boolean(label))
}
