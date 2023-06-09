/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import {
  DpAccordion,
  DpFlyout,
  DpObscure,
  dpValidateMultiselectDirective,
} from '@demos-europe/demosplan-ui/src'
import {
  initGlobalEventListener,
  ToggleSideMenu,
  touchFriendlyUserbox
} from '@DpJs/lib/core/libs'
import { bootstrap } from '@DpJs/bootstrap'
import DPVueCorePlugin from './plugins/DPVueCore'
import { initStore } from '@DpJs/store/core/initStore'
import { loadLibs } from '@DpJs/lib/core/loadLibs'
import loadSentry from './loadSentry'
import NotificationStoreAdapter from '@DpJs/store/core/NotificationStoreAdapter'
import HamburgerMenuButton from './components/button/HamburgerMenuButton'
import NotifyContainer from '@DpJs/components/shared/NotifyContainer'
import PortalVue from 'portal-vue'
import Vue from 'vue'
import Vuex from 'vuex'

loadSentry()
// Add plugins to Vue instance
Vue.use(PortalVue)
Vue.use(Vuex)
Vue.use(DPVueCorePlugin)

// Register components that are used globally
Vue.component('DpObscure', DpObscure)

function initialize (components = {}, storeModules = {}, apiStoreModules = [], presetStoreModules = {}) {
  bootstrap()
  Vue.prototype.Routing = window.Routing
  Vue.prototype.Translator = window.Translator
  Vue.prototype.hasPermission = window.hasPermission
  Vue.config.productionTip = false

  Vue.directive('tooltip', Tooltip)
  Vue.directive('dp-validate-multiselect', dpValidateMultiselectDirective)

  return initStore(storeModules, apiStoreModules, presetStoreModules).then(store => {
    /* eslint-disable no-new */
    const vm = new Vue({
      el: '#app',
      /*
       * DpAccordion is registered globally here, because we need it for the sidemenu in sidemenu.html.twig and can't
       * register it locally there (special knp menu renderer, see https://github.com/KnpLabs/KnpMenu).
       */
      components: { ...components, DpAccordion, DpFlyout, HamburgerMenuButton ,NotifyContainer },
      store: store,
      mounted () {
        window.dplan.notify = new NotificationStoreAdapter(this.$store)
        loadLibs()
        initGlobalEventListener()
        ToggleSideMenu()
        touchFriendlyUserbox()

        // This is a quickfix until https://yaits.demos-deutschland.de/T25443 arrives
        const flyoutMenuElement = document.querySelector('#jumpNavigation [data-actionmenu]')
        if (flyoutMenuElement) {
          const flyoutMenuWidth = flyoutMenuElement.offsetWidth + 20
          document.querySelector('#jumpNavigation').setAttribute('style', 'padding-right: ' + flyoutMenuWidth + 'px')
        }

        const mountedEvent = new Event('vue-mounted')
        document.dispatchEvent(mountedEvent)
        setTimeout(() => {
          window.mounted = true
        }, 5)
      }
    })
    Promise.resolve(vm)
  })
}

export { initialize }
