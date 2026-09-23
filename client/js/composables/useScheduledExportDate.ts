/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Calculate the next export date based on frequency and selected day
 */
const getNextExportDate = ({
  frequency,
  weekday,
  dayOfMonth,
  from = new Date(),
}: {
  frequency: string
  weekday?: number  // ISO-8601 format: 1-7 (Monday=1, Sunday=7)
  dayOfMonth?: number
  from?: Date
}): Date => {
  const date = new Date(from)
  date.setHours(0, 0, 0, 0)

  switch (frequency) {
    case 'daily':
      date.setDate(date.getDate() + 1)
      return date

    case 'weekly': {
      if (weekday === undefined) {
        return date
      }

      // Note: weekday parameter uses ISO-8601 format (1-7, Monday=1, Sunday=7)
      // Convert to JavaScript Date format (0-6, Sunday=0) for Date.getDay() comparison
      const jsWeekday = weekday === 7 ? 0 : weekday
      const currentDay = date.getDay()

      // Calculate number of days until the selected weekday
      let daysUntil = (jsWeekday - currentDay + 7) % 7

      // If today is the selected day, the next export is next week
      if (daysUntil === 0) {
        daysUntil = 7
      }

      date.setDate(date.getDate() + daysUntil)

      return date
    }

    case 'monthly': {
      if (dayOfMonth === undefined) {
        return date
      }

      const currentDay = date.getDate()

      // If the selected day hasn't happened yet this month, use the current month
      if (currentDay < dayOfMonth) {
        date.setDate(dayOfMonth)

        return date
      }

      // Otherwise, use the selected day in the next month
      date.setMonth(date.getMonth() + 1)
      date.setDate(dayOfMonth)
      return date
    }

    default:
      return date
  }
}

/**
 * Check if a date is tomorrow
 */
const isTomorrow = (date: Date): boolean => {
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  tomorrow.setHours(0, 0, 0, 0)

  const checkDate = new Date(date)
  checkDate.setHours(0, 0, 0, 0)

  return checkDate.getTime() === tomorrow.getTime()
}

/**
 * Get the weekday name for a date
 * Converts JavaScript Date format (0-6) to ISO-8601 format (1-7) for display
 */
const getWeekdayName = (date: Date): string => {
  const jsWeekday = date.getDay()
  const isoWeekday = jsWeekday === 0 ? 7 : jsWeekday

  // ISO-8601 weekday format (1-7, Monday=1, Sunday=7)
  const weekdayMap: Record<number, string> = {
    1: Translator.trans('weekday.monday'),
    2: Translator.trans('weekday.tuesday'),
    3: Translator.trans('weekday.wednesday'),
    4: Translator.trans('weekday.thursday'),
    5: Translator.trans('weekday.friday'),
    6: Translator.trans('weekday.saturday'),
    7: Translator.trans('weekday.sunday'),
  }
  return weekdayMap[isoWeekday] || ''
}

/**
 * Format date for display in "Day, DD.MM.YYYY" format
 * If the date is tomorrow, uses "Tomorrow" instead of the weekday name
 */
const formatExportDate = (date: Date): string => {
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  const formattedDate = `${day}.${month}.${year}`

  if (isTomorrow(date)) {
    return `${Translator.trans('tomorrow')}, ${formattedDate}`
  }

  const weekdayName = getWeekdayName(date)

  return `${weekdayName}, ${formattedDate}`
}

/**
 * Composable for calculating scheduled export dates
 *
 * @returns {Object} Functions for working with scheduled export dates
 */
export function useScheduledExportDate() {
  return {
    getNextExportDate,
    formatExportDate,
    isTomorrow,
  }
}
