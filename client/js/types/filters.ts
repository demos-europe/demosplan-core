/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export interface SelectOption {
  label: string
  value: string
  count?: number
}

export interface FilterDefinition {
  id: string
  fieldType: 'singleSelect' | 'multiSelect'
  name: string
}
