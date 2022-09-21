/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Prefix css classes with a parameter given from the project settings
 *
 * @param {String}  classList or querySelector
 *
 * @return {String} prefixed classList
 */
export default function prefixClass (classList = '') {
  let prefix = ''
  if (typeof dplan !== 'undefined' && dplan.settings && dplan.settings.publicCSSClassPrefix) {
    prefix = dplan.settings.publicCSSClassPrefix
  }

  let prefixed = ''

  // Don't try to do something if the result should not change
  if (prefix === '') {
    return classList
  }

  // Throw error if the type is wrong
  if (typeof classList !== 'string') {
    throw new Error('classList is an' + typeof classList + '. should be String.', classList)
  }

  /*
   * Assuming that a querySelector is passed when classList contains a dot, only the class selector parts are prefixed.
   * In the unlikely case that classes contain dots as part of their names, they will not be prefixed.
   */
  const checkClassList = /[.#[]/gi
  if (checkClassList.test(classList)) {
    prefixed = classList.replace(/(\.)(\S+)/gi, (cl, m1, m2) => `.${prefix}${m2}`)
  } else {
    prefixed = classList.replace(/(\S+)/gi, cl => `${prefix}${cl}`)
  }

  return prefixed
}
