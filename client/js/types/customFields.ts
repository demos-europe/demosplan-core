/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export interface CustomFieldOption {
  id: string
  label: string
}

export interface CustomFieldDefinition {
  id: string
  attributes: {
    name: string
    fieldType: string
    isSeparateColumn?: boolean
    options: CustomFieldOption[]
  }
}

export interface CustomFieldValue {
  id: string
  value: string | string[] | null
}
