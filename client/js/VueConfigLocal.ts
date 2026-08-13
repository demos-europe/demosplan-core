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

/*
 * Typed as `typeof shallowMount` so callers keep the overloads that infer the component instance —
 * without it, `wrapper.vm.<prop>` is unresolvable in .ts specs.
 */
const shallowMountWithGlobalMocks: typeof shallowMount = (component: any, options: any = {}) => shallowMount(component, options)

export default shallowMountWithGlobalMocks
