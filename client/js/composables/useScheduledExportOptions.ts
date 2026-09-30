/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import type { SelectOption } from '@DpJs/types/scheduledExport'

export function useScheduledExportOptions () {
  const frequencyOptions = [
    {
      label: Translator.trans('export.xlsx.scheduled.frequency.daily'),
      value: 'daily',
    },
    {
      label: Translator.trans('export.xlsx.scheduled.frequency.weekly'),
      value: 'weekly',
    },
    {
      label: Translator.trans('export.xlsx.scheduled.frequency.monthly'),
      value: 'monthly',
    },
  ]

  const weekdayOptions: SelectOption[] = [
    {
      label: Translator.trans('weekday.monday'),
      value: 1,
    },
    {
      label: Translator.trans('weekday.tuesday'),
      value: 2,
    },
    {
      label: Translator.trans('weekday.wednesday'),
      value: 3,
    },
    {
      label: Translator.trans('weekday.thursday'),
      value: 4,
    },
    {
      label: Translator.trans('weekday.friday'),
      value: 5,
    },
  ]

  const dayOfMonthOptions: SelectOption[] = [5, 10, 15, 20, 25, 30].map(value => ({
    label: `${value}.`,
    value,
  }))

  const getFrequencyLabel = (value: string): string => {
    return frequencyOptions.find(option => option.value === value)?.label ?? value
  }

  const getWeekdayLabel = (dayNumber: number): string => {
    return weekdayOptions.find(option => option.value === dayNumber)?.label ?? String(dayNumber)
  }

  const getDayOfMonthLabel = (day: number): string => {
    return `${day}.`
  }

  return {
    frequencyOptions,
    weekdayOptions,
    dayOfMonthOptions,
    getFrequencyLabel,
    getWeekdayLabel,
    getDayOfMonthLabel,
  }
}
