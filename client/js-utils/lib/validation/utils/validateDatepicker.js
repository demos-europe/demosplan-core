/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { toggleErrorClass } from './helpers'

export default function validateDatepicker (input) {
  let isValid = true
  if (input.value === '') {
    isValid = false
  } else {
    // Regex to check date from https://stackoverflow.com/a/15504877
    const regex = /^(?:(?:31(.)(?:0?[13578]|1[02]))\1|(?:(?:29|30)(.)(?:0?[13-9]|1[0-2])\2))(?:(?:1[6-9]|[2-9]\d)?\d{2})$|^(?:29(.)0?2\3(?:(?:(?:1[6-9]|[2-9]\d)?(?:0[48]|[2468][048]|[13579][26])|(?:(?:16|[2468][048]|[3579][26])00))))$|^(?:0?[1-9]|1\d|2[0-8])(.)(?:(?:0?[1-9])|(?:1[0-2]))\4(?:(?:1[6-9]|[2-9]\d)?\d{2})$/
    if (regex.test(input.value) === false) {
      isValid = false
    }
  }

  toggleErrorClass(input, !isValid)
  return isValid
}
