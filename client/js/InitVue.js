/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { DpAccordion, dpValidateMultiselectDirective, Tooltip } from '@demos-europe/demosplan-ui'
import { initGlobalEventListener, ToggleSideMenu, touchFriendlyUserbox } from '@DpJs/lib/core/libs'
import NotifyContainer from '@DpJs/components/shared/NotifyContainer'
import { bootstrap } from '@DpJs/bootstrap'
import { initStore } from '@DpJs/store/core/initStore'
import PortalVue from 'portal-vue'
import { loadLibs } from '@DpJs/lib/core/loadLibs'
import NotificationStoreAdapter from '@DpJs/store/core/NotificationStoreAdapter'
import Vue from 'vue'
import Vuex from 'vuex'

Vue.configureCompat({
  RENDER_FUNCTION: false,
  TRANSITION_GROUP_ROOT: false,
  ATTR_FALSE_VALUE: false
})


function initialize (components = {}, storeModules = {}, apiStoreModules = [], presetStoreModules = {}) {
  bootstrap()

  Vue.prototype.dplan = window.dplan
  Vue.prototype.Routing = window.Routing
  Vue.prototype.Translator = window.Translator
  Vue.prototype.hasPermission = window.hasPermission
  Vue.config.productionTip = false

  if (dplan?.settings?.debug) {
    Vue.config.performance = false
  }

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
    components: { ...components, DpAccordion, NotifyContainer },
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
    }
  })

  // vm.config.compilerOptions.whitespace = 'condense'

  // vm.use(Vuex)

  // vm.use(PortalVue)

  // vm.config.devtools = true
  // vm.config.globalProperties.dplan = window.dplan
  // vm.config.globalProperties.Routing = window.Routing
  // vm.config.globalProperties.Translator = window.Translator
  // vm.config.globalProperties.hasPermission = window.hasPermission
  //
  // vm.directive('tooltip', Tooltip)
  // vm.directive('dp-validate-multiselect', dpValidateMultiselectDirective)
  //
  //
  // vm.mount('#app')

  // return Promise.resolve(vm)
  // return initStore(storeModules, apiStoreModules, presetStoreModules).then((store, vm) => {
    /* eslint-disable no-new */

    Promise.resolve(vm)
  })
}

export { initialize }
