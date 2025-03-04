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
import Vue from 'vue'
import { shallowMount } from '@vue/test-utils'

// Globally used
import { DpMultiselect, DpObscure } from '@demos-europe/demosplan-ui'
import lscache from 'lscache'
import { VTooltip } from 'v-tooltip'

/*
 * This is copied from DpVueCore.js
 * Since there is an issue with the hasOwnProp and it has to be loaded via
 * async/await which causes problems in the jest context, this looked like a way to
 * make the tests run without refactoring everything
 */
const DPVueCorePlugin = {
  install: function (VueCore) {
    VueCore.prototype.dplan = window.dplan
    VueCore.prototype.hasPermission = window.hasPermission
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

/*
 * Necessary for Bus, Vue.nextTick etc.
 * shouldn't it work when it's a globalMock ?
 * in my tests it doesn't :-(
 */
global.Translator = Translator
global.hasPermission = hasPermission
global.lscache = lscache
global.dplan = dplan


// Vue.directive('tooltip', VTooltip)

// Register components that are used globally
const components = {
  DpObscure,
  DpMultiselect
}

const globalPlugins = {
  DPVueCorePlugin
}

const shallowMountWithGlobalMocks = (component, options) => {
  return shallowMount(
    component,
    {
      components: Object.assign(components, options.components),
      directives: Object.assign({ tooltip: VTooltip }, options.directives),
      props: Object.assign({}, options.props),
      slots: Object.assign({}, options.slots),
      global: {
        computed: Object.assign({}, options.computed),
        plugins: Object.assign(globalPlugins, options.plugins),
        mocks: Object.assign(globalMocks, options.mocks),
        stubs: Object.assign({}, options.stubs)
      }
    })
}

export default shallowMountWithGlobalMocks
