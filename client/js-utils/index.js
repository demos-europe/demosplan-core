/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { toggleFullscreen, bindFullScreenChange, unbindFullScreenChange, isActiveFullScreen } from './utils/fullscreen'
import changeUrlforPager from './utils/changeUrlforPager'
import debounce from './utils/debounce'
import deepMerge from './utils/deepMerge'
import formatBytes from './utils/formatBytes'
import getAnimationEventName from './utils/getAnimationEventName'
import getScrollTop from './utils/getScrollTop'
import { makeFormPost } from './utils/makeFormPost'
import sortAlphabetically from './utils/sortAlphabetically'
import throttle from './utils/throttle'
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
  makeFormPost,
  sortAlphabetically,
  throttle,
  toggleFullscreen,
  unbindFullScreenChange,
  uniqueArrayByObjectKey,
}
