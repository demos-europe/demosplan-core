/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import type { configureCompat } from 'vue'

type CompatConfig = Parameters<typeof configureCompat>[0]

/*
 * @vue/compat flags switched to Vue 3 behaviour, shared by the app (InitVue) and vitest (tests/frontend/setup.ts)
 * so both run against the same runtime
 */
export const compatConfig: CompatConfig = {
  COMPONENT_ASYNC: false,
  CONFIG_DEVTOOLS: false,
  CONFIG_IGNORED_ELEMENTS: false,
  CONFIG_KEY_CODES: false,
  CONFIG_OPTION_MERGE_STRATS: false,
  CONFIG_PRODUCTION_TIP: false,
  CONFIG_SILENT: false,
  CONFIG_WHITESPACE: false,
  CUSTOM_DIR: false,
  FILTERS: false,
  GLOBAL_DELETE: false,
  GLOBAL_EXTEND: false,
  GLOBAL_MOUNT: false,
  GLOBAL_MOUNT_CONTAINER: false,
  GLOBAL_OBSERVABLE: false,
  GLOBAL_PRIVATE_UTIL: false,
  GLOBAL_PROTOTYPE: false,
  GLOBAL_SET: false,
  INSTANCE_ATTRS_CLASS_STYLE: false,
  INSTANCE_CHILDREN: false,
  INSTANCE_DESTROY: false,
  OPTIONS_DATA_FN: false,
  OPTIONS_DESTROYED: false,
  PROPS_DEFAULT_THIS: false,
  RENDER_FUNCTION: false,
  TRANSITION_CLASSES: false,
  TRANSITION_GROUP_ROOT: false,
}
