/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Vue configuration for bus instance.
 * This is imported via infrastructure/frontend/config.webpack.babel.js:75.
 *
 * Other than the core instance equivalent VueConfigCore.js which returns a Vue() constructor, a Vue instance
 * is returned here, because Vue.emit is available only after new Vue().
 */
import DPVueBusPlugin from './plugins/DPVueBus'
import { hasOwnProp } from '@demos-europe/demosplan-ui'
import Vue from 'vue'

Vue.config.productionTip = false

Vue.use(DPVueBusPlugin)

const Bus = new Vue()

Bus.emitOnLoad = (...args) => {
  if (hasOwnProp(window, 'vueEntrypointBundles') === false) {
    window.vueEntrypointBundles = []
  }
  window.vueEntrypointBundles.push(args)
  window.addEventListener('load', () => Bus.emit.apply(Bus, args))
}

export default Bus
