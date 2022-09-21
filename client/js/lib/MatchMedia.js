/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This script acts as a bridge between scss defined media queries
 * and scripts that should only fire with certain breakpoints.
 *
 * Usage:
 *  let currentBreakpoint = new MatchMedia();
 *  HtmlElement.on('resize', function() {
 *      if (currentBreakpoint.greater('palm')) { do stuff }
 *  });
 *
 * See:
 *  https://www.lullabot.com/articles/importing-css-breakpoints-into-javascript
 *  _base.dplan.scss
 *  _settings.breakpoints.dplan.scss:44
 */

class MatchMedia {
  constructor () {
    this.breakpointToNumber = {
      palm: 0,
      'lap-up': 1,
      'desk-up': 2,
      wide: 3
    }
  }

  getCurrentBreakpoint () {
    const computedStyle = window.getComputedStyle(document.querySelector('body'), ':before')
    return computedStyle.getPropertyValue('content').replace(/'/g, '').replace(/"/g, '')
  }

  is (breakpoint) {
    return this.getCurrentBreakpoint() === breakpoint
  }

  greater (breakpoint) {
    return this.breakpointToNumber[this.getCurrentBreakpoint()] > this.breakpointToNumber[breakpoint]
  }

  less (breakpoint) {
    return this.breakpointToNumber[this.getCurrentBreakpoint()] < this.breakpointToNumber[breakpoint]
  }
}

export default MatchMedia
