/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Vue configuration for core instance.
 * This is imported via infrastructure/frontend/config.webpack.babel.js:75.
 */
import * as Sentry from '@sentry/browser'
import { BrowserTracing } from '@sentry/tracing'
import DpObscure from '../../../../../demosplan-ui/src/components/core/DpObscure'
import dpValidateMultiselectDirective from '@demos-europe/demosplan-utils/lib/validation/dpValidateMultiselectDirective'
import DPVueCorePlugin from './plugins/DPVueCore'
import PortalVue from 'portal-vue'
import Tooltip from '../../../../../demosplan-ui/src/directives/Tooltip/Tooltip'
import Vue from 'vue'
import Vuex from 'vuex'

// Add plugins to Vue instance
Vue.use(PortalVue)
Vue.use(Vuex)
Vue.use(DPVueCorePlugin)

// Register components that are used globally
Vue.component('DpObscure', DpObscure)

// Register custom directives

// Exposing Translator to the Vue prototype allows us to pass us Translator.trans() to v-tooltip
Vue.prototype.Translator = window.Translator

Vue.directive('tooltip', Tooltip)

Vue.directive('dp-validate-multiselect', dpValidateMultiselectDirective)

if (window.dplan.sentryDsn !== '') {
  Sentry.init({
    dsn: window.dplan.sentryDsn,
    integrations: [new BrowserTracing({
      attachProps: true,
      tracing: true,
      tracingOptions: {
        trackComponents: true
      }
    })]
  })
}

export default Vue
