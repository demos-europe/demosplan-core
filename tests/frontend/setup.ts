/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Global vitest setup — registers global vue test utils plugins,
 * components, directives, and globalProperties
 */
import { config } from '@vue/test-utils'
import lscache from 'lscache'
import { vi } from 'vitest'

/*
 * Polyfill `matchMedia` on `window` before any package that calls it at
 * module-init time is imported (e.g. demosplan-ui). Static ES `import`s
 * are hoisted to the top of the file, so a static import of demosplan-ui
 * here would evaluate before this polyfill runs. The UI imports below
 * are therefore loaded dynamically (await import) so they execute AFTER
 * the polyfill.
 */
if (typeof window !== 'undefined' && !window.matchMedia) {
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }))
}

const { DpMultiselect, DpObscure } = await import('@demos-europe/demosplan-ui')
const { VTooltip } = await import('v-tooltip')

const Translator = { trans: vi.fn((key: string) => key) }
const Routing = { generate: vi.fn((key: string) => key) }
const dplan = {
  settings: {
    debug: false,
    publicCSSClassPrefix: 'dp-',
  },
  debug: false,
}
const hasPermission = vi.fn(() => true)
const dpApi = vi.fn(() => Promise.resolve())

// Expose on globalThis
Object.assign(globalThis, {
  Translator,
  Routing,
  dplan,
  hasPermission,
  lscache,
})

// DpVueCore-equivalent plugin: expose dplan and hasPermission on app globals
const DPVueCorePlugin = {
  install (app: { config: { globalProperties: Record<string, unknown> } }) {
    app.config.globalProperties.dplan = dplan
    app.config.globalProperties.hasPermission = hasPermission
  },
}

// Apply defaults to every mount via @vue/test-utils config.global
config.global.plugins = [DPVueCorePlugin]
config.global.components = {
  DpObscure,
  DpMultiselect,
}
config.global.directives = {
  tooltip: VTooltip,
}
config.global.config = {
  globalProperties: {
    hasPermission,
    Routing,
    Translator,
    dplan,
    lscache,
    dpApi,
  },
}
config.global.renderStubDefaultSlot = false
