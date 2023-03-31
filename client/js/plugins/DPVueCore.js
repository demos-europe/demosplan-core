/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * DPVueCorePlugin adds dplan object, globally used functions and filters to the main Vue instance
 */

import { extendedEmit, extendedOn } from '../lib/core/ExtendedVueEvents'

const hasOwnProp = async (obj, prop) => {
  const { hasOwnProp } = await import('@demos-europe/demosplan-ui')
  return hasOwnProp(obj, prop)
}

const DPVueCorePlugin = {
  install: function (VueCore) {
    if (typeof dplan !== 'undefined' && hasOwnProp(dplan, 'settings') && dplan.settings.debug) {
      VueCore.config.performance = false
    }

    VueCore.prototype.dplan = window.dplan
    VueCore.prototype.hasPermission = window.hasPermission

    VueCore.prototype.emit = extendedEmit
    VueCore.prototype.on = extendedOn
  }
}

export default DPVueCorePlugin
