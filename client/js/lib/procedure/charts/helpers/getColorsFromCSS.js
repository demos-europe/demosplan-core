/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 *
 * @param cssColorClasses (as Array without dot. to detect css-color-value)
 * @returns {HEX-colors as Array}
 */
function getColorsFromCSS (cssColorClasses) {
  let colors = cssColorClasses || []

  if (colors.constructor === Array) {
    colors = colors.map(color => getColorFromCSS(color))
    return colors
  }
}

/**
 *
 * @param className (without dot)
 * @returns string (HEX-Color)
 */
function getColorFromCSS (className) {
  const body = document.getElementsByTagName('body')[0]
  const div = document.createElement('div')
  div.className = className
  div.id = 'tmpIdToGetColor'
  body.appendChild(div)
  const tmpDiv = document.getElementById('tmpIdToGetColor')
  const color = window.getComputedStyle(tmpDiv).getPropertyValue('color')

  body.removeChild(tmpDiv)
  return color
}

export { getColorsFromCSS, getColorFromCSS }
