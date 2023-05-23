/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

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
const DPVueCorePlugin = {
  install: function (VueCore) {
    if (dplan?.settings?.debug) {
      VueCore.config.performance = false
    }

    VueCore.prototype.dplan = window.dplan
    VueCore.prototype.hasPermission = window.hasPermission
  }
}

export default DPVueCorePlugin
