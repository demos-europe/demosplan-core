/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { shouldValidate, toggleErrorClass } from './helpers'

export default function validateMultiselect (field) {
  if (shouldValidate(field) === false) {
    return true
  }

  const isValid = field.getAttribute('data-dp-validate-is-valid') === 'true'
  toggleErrorClass(field, !isValid)

  return isValid
}
