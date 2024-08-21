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
  DpContextualHelp,
  DpFlyout,
  DpObscure,
  dpValidateMultiselectDirective,
  Tooltip
} from '@demos-europe/demosplan-ui'
import {
  initGlobalEventListener,
  ToggleSideMenu,
  touchFriendlyUserbox
} from '@DpJs/lib/core/libs'
import BackToTopButton from '@DpJs/components/button/BackToTopButton'
import { bootstrap } from '@DpJs/bootstrap'
import { createApp } from 'vue'
import DPVueCorePlugin from '@DpJs/plugins/DPVueCore'
import HamburgerMenuButton from '@DpJs/components/button/HamburgerMenuButton'
import { initStore } from '@DpJs/store/core/initStore'
import { loadLibs } from '@DpJs/lib/core/loadLibs'
import loadSentry from './loadSentry'
import NotificationStoreAdapter from '@DpJs/store/core/NotificationStoreAdapter'
import NotifyContainer from '@DpJs/components/shared/NotifyContainer'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'

function initialize (components = {}, storeModules = {}, apiStoreModules = [], presetStoreModules = {}) {
  bootstrap()

  return initStore(storeModules, apiStoreModules, presetStoreModules).then(store => {
    const app = createApp({
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

    app.config.globalProperties.dplan = window.dplan
    app.config.globalProperties.Routing = window.Routing
    app.config.globalProperties.Translator = window.Translator
    app.config.globalProperties.hasPermission = window.hasPermission
    app.config.globalProperties.h = window.h

    // This is explicitly set despite being the default value to suppress console warnings
    app.config.compilerOptions.whitespace = 'condense'

    if (dplan?.settings?.debug) {
      app.config.performance = false
    }

    loadSentry()

    app.directive('dp-validate-multiselect', dpValidateMultiselectDirective)
    app.directive('tooltip', Tooltip)

    app.use(store)

    // Add plugins to Vue instance
    app.use(DPVueCorePlugin)

    // Register components that are used globally
    app.component('BackToTopButton', BackToTopButton)
    app.component('DpObscure', DpObscure)
    app.component('NotifyContainer', NotifyContainer)
    app.component('DpAccordion', DpAccordion)
    app.component('DpFlyout', DpFlyout)
    app.component('HamburgerMenuButton', HamburgerMenuButton)
    app.component('RegisterFlyout', RegisterFlyout)
    app.component('DpContextualHelp', DpContextualHelp)

    Object.keys(components).forEach(comp => {
      if (components[comp]) {
        app.component(components[comp].name, components[comp])
      } else {
        console.log(`${components[comp]} is undefined}`, components)
      }
    })

    app.mount('#app')

    Promise.resolve(app)
  })
}

export { initialize }
