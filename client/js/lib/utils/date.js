/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import dayjs from 'dayjs'

const DATE_FORMAT_SHORT = 'DD.MM.YYYY'
const DATE_FORMAT_LONG = 'DD.MM.YYYY, HH:mm [Uhr]'

const formatDate = function (date, format = DATE_FORMAT_SHORT) {
  let d

  /*
   * This assumes that values of type number are always unix seconds or milliseconds.
   * Stack overflow says unix timestamp in seconds will have 10 digits until 20.11.2286
   * so it is safe to check for > 10 to sort out milliseconds (which must be directly passed to `dayjs()`).
   */
  if (typeof date === 'number') {
    d = date.toString().length > 10 ? dayjs(date) : dayjs.unix(date)
  }

  if (typeof date === 'string' || date instanceof Date) {
    d = dayjs(date)
  }

  if (typeof d === 'undefined' || d === null) {
    return dayjs().format(format)
  }

  return d.format(format)
}

const toDate = function (date, format = 'DD.MM.YYYY') {
  return dayjs(date, format).toDate()
}

export {
  DATE_FORMAT_LONG,
  formatDate,
  toDate
}
