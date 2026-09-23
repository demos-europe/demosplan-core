/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export interface ScheduledExport {
  id: string
  type: 'ScheduledExport'
  attributes: {
    frequency: string
    weekday: number | null  // ISO-8601 format: 1-7 (Monday=1, Sunday=7)
    dayOfMonth: number | null
    parameters: string
    nextRunAt?: string
    lastRunAt?: string | null
  }
}

export interface ScheduledExportFormData {
  frequency: string
  day: number | null
}

export interface SelectOption {
  label: string
  value: string | number
}

export type DaySelectName = 'weekday' | 'monthDay'

export interface DaySelect {
  name: DaySelectName
  label: string
  options: SelectOption[]
  selected: string | number
}
