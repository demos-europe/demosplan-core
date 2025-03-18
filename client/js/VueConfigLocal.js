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

// Use Local Vue for testing
import { createLocalVue, shallowMount } from '@vue/test-utils'

// Globally used
import { DpMultiselect, DpObscure } from '@demos-europe/demosplan-ui'
import lscache from 'lscache'
import PortalVue from 'portal-vue'
import { VTooltip } from 'v-tooltip'
import Vuex from 'vuex'

/*
 * This is copied from DpVueCore.js
 * Since there is an issue with the hasOwnProp and it has to be loaded via
 * async/await which causes problems in the jest context, this looked like a way to
 * make the tests run without refactoring everything
 */
const DPVueCorePlugin = {
  install: function (VueCore) {
    VueCore.prototype.dplan = <window className="dplan"></window>
    VueCore.prototype.hasPermission = window.hasPermission
  }
}

// Mocking global stuff
const Vue = createLocalVue()
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
  Vue,
  lscache,
  dpApi,
  checkResponse
}

/*
 * Necessary for Bus, Vue.nextTick etc.
 * shouldn't it work when it's a globalMock ?
 * in my tests it doesn't :-(
 */
global.Vue = Vue
global.Translator = Translator
global.hasPermission = hasPermission
global.lscache = lscache
global.dplan = dplan

// Add plugins to Vue instance
Vue.use(PortalVue)

Vue.use(Vuex)
Vue.use(DPVueCorePlugin)

Vue.directive('tooltip', VTooltip)

// Register components that are used globally
Vue.component('DpObscure', DpObscure)
Vue.component('DpMultiselect', DpMultiselect)

const shallowMountWithGlobalMocks = (component, options) => {
  return shallowMount(
    component,
    {
      localVue: Vue,
      mocks: Object.assign(globalMocks, options.mocks),
      propsData: Object.assign({}, options.propsData),
      computed: Object.assign({}, options.computed),
      store: options.store,
      methods: Object.assign({}, options.methods),
      stubs: Object.assign({}, options.stubs),
      slots: Object.assign({}, options.slots)
    })
}

export default shallowMountWithGlobalMocks
