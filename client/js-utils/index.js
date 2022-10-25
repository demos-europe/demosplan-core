/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { toggleFullscreen, bindFullScreenChange, unbindFullScreenChange, isActiveFullScreen } from './fullscreen'
import changeUrlforPager from './changeUrlforPager'
import debounce from './debounce'
import deepMerge from './deepMerge'
import formatBytes from './formatBytes'
import getAnimationEventName from './getAnimationEventName'
import getScrollTop from './getScrollTop'
import sortAlphabetically from './sortAlphabetically'
import throttle from './throttle'
import uniqueArrayByObjectKey from './uniqueArrayByObjectKey'

export {
  bindFullScreenChange,
  changeUrlforPager,
  debounce,
  deepMerge,
  formatBytes,
  isActiveFullScreen,
  getAnimationEventName,
  getScrollTop,
  sortAlphabetically,
  throttle,
  toggleFullscreen,
  unbindFullScreenChange,
  uniqueArrayByObjectKey,
}
