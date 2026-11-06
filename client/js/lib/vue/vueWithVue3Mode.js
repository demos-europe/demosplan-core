/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * Vue entry for Vue 3 libraries (karten, oruga): same @vue/compat runtime,
 * but every component runs in Vue 3 mode instead of compat's Vue 2 mode
 */
import { defineComponent as compatDefineComponent } from '@vue/compat'

export * from '@vue/compat'

const withVue3Mode = (options = {}) => ({
  ...options,
  compatConfig: { ...options.compatConfig, MODE: 3 },
})

export function defineComponent (options, extraOptions) {
  if (typeof options === 'function') {
    return compatDefineComponent(options, withVue3Mode(extraOptions))
  }

  return compatDefineComponent(withVue3Mode(options), extraOptions)
}
