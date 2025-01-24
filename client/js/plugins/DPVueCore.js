/**
 * (c) 2010-present DEMOS plan GmbH.
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
  install: function (app, options) {
    if (dplan?.settings?.debug) {
      app.config.performance = false
    }

    app.config.globalProperties.dplan = window.dplan
    app.config.globalProperties.hasPermission = window.hasPermission
  }
}

export default DPVueCorePlugin
