/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * DPVueBusPlugin adds extended event-emitter and -listener-methods to a Vue instance used as an event bus
 */

import { extendedEmit, extendedOn } from '../lib/ExtendedVueEvents'

const DPVueBusPlugin = {
  install: function (Vue, options) {
    Vue.prototype.emit = extendedEmit
    Vue.prototype.on = extendedOn
  }
}

export default DPVueBusPlugin
