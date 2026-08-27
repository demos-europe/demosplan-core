/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

interface SelectOption {
  label: string
  value: number
}

export function useScheduledExportOptions () {
  const frequencyOptions = [
    {
      label: Translator.trans('export.xlsx.scheduled.interval.daily'),
      value: 'daily',
    },
    {
      label: Translator.trans('export.xlsx.scheduled.interval.weekly'),
      value: 'weekly',
    },
    {
      label: Translator.trans('export.xlsx.scheduled.interval.monthly'),
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
    {
      label: Translator.trans('weekday.saturday'),
      value: 6,
    },
    {
      label: Translator.trans('weekday.sunday'),
      value: 0,
    },
  ]

  const monthDayOptions: SelectOption[] = [5, 10, 15, 20, 25, 30].map(value => ({
    label: `${value}.`,
    value,
  }))

  const getFrequencyLabel = (value: string): string => {
    return frequencyOptions.find(option => option.value === value)?.label ?? value
  }

  const getWeekdayLabel = (dayNumber: number): string => {
    return weekdayOptions.find(option => option.value === dayNumber)?.label ?? String(dayNumber)
  }

  const getMonthDayLabel = (day: number): string => {
    return `${day}.`
  }

  return {
    frequencyOptions,
    weekdayOptions,
    monthDayOptions,
    getFrequencyLabel,
    getWeekdayLabel,
    getMonthDayLabel,
  }
}
