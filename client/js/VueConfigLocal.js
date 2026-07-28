/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Thin wrapper around @vue/test-utils' shallowMount.
 * Global plugins, components, directives, and properties are configured
 * once in tests/frontend/setup.ts via config.global.*.
 * Per-mount options passed here are merged on top by vue-test-utils.
 */
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
  },
}

// Mocking global stuff
const hasPermission = jest.fn(() => true)

const Translator = {
  trans: jest.fn(key => key),
}
const Routing = {
  generate: jest.fn(key => key),
}
const dplan = {
  settings: {
    debug: false,
    publicCSSClassPrefix: 'dp-',
  },
  debug: false,
  paths: {
    tusEndpoint: 'tus-endpoint',
  },
}

const dpApi = jest.fn(() => Promise.resolve())

const shallowMountWithGlobalMocks = (component, options = {}) => shallowMount(component, options)

export default shallowMountWithGlobalMocks
