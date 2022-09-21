/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { VPopover, VTooltip } from 'v-tooltip'

/*
 * Merge custom config into default options for tooltip config.
 * The non-vue tooltip lib also uses tooltip.js, both have to be in sync.
 */
const tooltipConfig = {
  defaultHtml: true,
  defaultBoundariesElement: 'scrollParent',
  defaultDelay: {
    show: 300,
    hide: 100
  },
  defaultTemplate: '<div class="tooltip" role="tooltip"><div class="tooltip__arrow"></div><div class="tooltip__inner"></div></div>',
  popover: {
    defaultPlacement: 'top',
    defaultBaseClass: 'tooltip',
    defaultInnerClass: 'tooltip__inner',
    defaultArrowClass: 'tooltip__arrow',
    defaultDelay: {
      show: 300,
      hide: 100
    },
    defaultTrigger: 'hover'
  }
}
VTooltip.options = { ...VTooltip.options, ...tooltipConfig }
VPopover.options = { ...VPopover.options, ...tooltipConfig }
const Tooltip = VTooltip

export { VPopover, Tooltip }
