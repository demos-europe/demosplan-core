/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Return a translated hint on exact matching characters.
 * @param {Number}    currentLength Currently used character count
 * @param {*|String}  requiredLength Expected amount of characters
 * @return {*|string}
 */
const exactlengthHint = (currentLength, requiredLength) => {
  const reqLength = Number(requiredLength)

  return reqLength
    ? Translator.trans('input.text.exactlength.short', {
      requiredlength: requiredLength,
      count: currentLength,
      class: (currentLength === reqLength) ? 'color-ui-info' : 'color-ui-error'
    })
    : ''
}

/**
 * Return a translated hint on maximum available characters.
 * @param {Number}    currentLength Currently used character count
 * @param {*|String}  maxlength     Max available characters
 * @return {*|string}
 */
const maxlengthHint = (currentLength, maxlength) => {
  /*
   * In cases with more than 50 characters available, the available characters should be highlighted with a different
   * color when dropping below 15. For less than 50 characters, only the remaining 3 chars are highlighted that way.
   */
  const errorThreshold = maxlength > 50 ? 15 : 3
  const max = Number(maxlength)

  return max
    ? Translator.trans('input.text.maxlength.short', {
      max,
      count: max - currentLength,
      class: (max - currentLength > errorThreshold) ? 'color-ui-info' : 'color-ui-error'
    })
    : ''
}

/**
 * Return a translated hint on minimum available characters.
 * @param {Number}    currentLength Currently used character count
 * @param {*|String}  minlength     Min available characters
 * @return {*|string}
 */
const minlengthHint = (currentLength, minlength) => {
  const min = Number(minlength)

  return min
    ? Translator.trans('input.text.minlength.short', {
      min: min,
      count: min - currentLength,
      class: (min <= currentLength) ? 'color-ui-info' : 'color-ui-error'
    })
    : ''
}

export { exactlengthHint, maxlengthHint, minlengthHint }
