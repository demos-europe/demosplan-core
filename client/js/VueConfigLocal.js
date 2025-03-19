/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Vue configuration for core instance
 *
 * Used to run Jest-Tests
 */

import { DpMultiselect, DpObscure } from '@demos-europe/demosplan-ui'
import { createApp } from 'vue'
import lscache from 'lscache'
import { shallowMount } from '@vue/test-utils'
import { VTooltip } from 'v-tooltip'

/*
 * This is copied from DpVueCore.js
 * Since there is an issue with the hasOwnProp and it has to be loaded via
 * async/await which causes problems in the jest context, this looked like a way to
 * make the tests run without refactoring everything
 */
const DPVueCorePlugin = {
  install: function (app) {
    app.config.globalProperties.dplan = window.dplan
    app.config.globalProperties.hasPermission = window.hasPermission
  }
}

// Mocking global stuff
const hasPermission = jest.fn(() => true)

const Translator = {
  trans: jest.fn(key => key)
}
const Routing = {
  generate: jest.fn(key => key)
}
const dplan = {
  settings: {
    debug: false,
    publicCSSClassPrefix: 'dp-'
  },
  debug: false
}

const dpApi = jest.fn(() => Promise.resolve())
const checkResponse = jest.fn(data => Promise.resolve(data))

const globalMocks = {
  hasPermission,
  Routing,
  Translator,
  dplan,
  lscache,
  dpApi,
  checkResponse
}

// Create a local Vue instance
const localVue = createApp({})

/*
 * Necessary for Bus, Vue.nextTick etc.
 * shouldn't it work when it's a globalMock ?
 * in my tests it doesn't :-(
 */
global.Vue = localVue
global.Translator = Translator
global.hasPermission = hasPermission
global.lscache = lscache
global.dplan = dplan

// Add plugins to Vue instance
localVue.use(DPVueCorePlugin)

localVue.directive('tooltip', VTooltip)

// Register components that are used globally
localVue.component('DpObscure', DpObscure)
localVue.component('DpMultiselect', DpMultiselect)

const shallowMountWithGlobalMocks = (component, options) => {
  return shallowMount(
    component,
    {
      global: {
        plugins: [
          DPVueCorePlugin,
          ...(options.global?.plugins || [])
        ],
        directives: {
          tooltip: VTooltip
        },
        components: {
          DpObscure,
          DpMultiselect
        },
        config: {
          globalProperties: {
            ...globalMocks,
            ...options.global?.config?.globalProperties
          }
        },
        ...options.global
      },
      ...options
    })
}

export default shallowMountWithGlobalMocks
