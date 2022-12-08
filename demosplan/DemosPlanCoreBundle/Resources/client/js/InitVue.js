/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { DpAccordion, DpNotifyContainer, Tooltip } from '@demos-europe/demosplan-ui'
import { initGlobalEventListener, NotificationStoreAdapter, ToggleSideMenu, touchFriendlyUserbox } from '@demos-europe/demosplan-utils'
import { bootstrap } from '@DpJs/bootstrap'
import dpValidateMultiselectDirective from '@demos-europe/demosplan-utils/lib/validation/dpValidateMultiselectDirective'
import { initStore } from '@DpJs/store/core/initStore'
import { loadLibs } from '@DpJs/lib/core/loadLibs'

function initialize (components = {}, storeModules = {}, apiStoreModules = [], presetStoreModules = {}) {
  bootstrap()
  window.Bus = Bus
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
      components: { ...components, DpAccordion, DpNotifyContainer },
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
    Promise.resolve(vm)
  })
}

export { initialize }
