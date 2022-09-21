/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Get the value of a css custom property, if supported by the browser.
 */
export default function getCssVariable (variableName) {
  return getComputedStyle(document.documentElement).getPropertyValue(variableName)
}
