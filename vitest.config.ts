/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { createRequire } from 'node:module'
import { defineConfig } from 'vitest/config'
import { loadEnv } from 'vite'
import path from 'node:path'
import vue from '@vitejs/plugin-vue'

const require = createRequire(import.meta.url)

/*
 * TEAMCITY_VERSION is checked as well as CI because the build steps run in a container, and it is not guaranteed
 * that CI is passed through to it
 */
const isCi = Boolean(process.env.CI || process.env.TEAMCITY_VERSION)

const isUi = process.argv.includes('--ui')
const uiHost = loadEnv('test', process.cwd(), 'VITEST_').VITEST_UI_HOST || '0.0.0.0'

/*
 * Resolve @vue/test-utils' ESM entry from its exports map
 * Needed to ensure @vue/compat is applied
 */
const vueTestUtilsEsm = () => {
  const packageJsonPath = require.resolve('@vue/test-utils/package.json')
  const esmEntry = require(packageJsonPath).exports?.['.']?.import

  if (!esmEntry) {
    throw new Error('Can\'t resolve the @vue/test-utils ESM entry from its exports map')
  }

  return path.resolve(path.dirname(packageJsonPath), esmEntry)
}

/*
 * @vitejs/plugin-vue emits unknown custom blocks as virtual modules that vite then tries to parse as JS;
 * to prevent vue files with <license>/<documentation> blocks from failing, those virtual ids are resolved
 * to an empty module. Allowlisting the real block types also covers typo'd blocks (<documentaion>) and any
 * custom block added later.
 */
const isVueCustomBlock = (id: string) => /[?&]vue&/.test(id) &&
  /[?&]type=([^&]+)/.test(id) &&
  !/[?&]type=(script|template|style)(&|$)/.test(id)

const ignoreVueCustomBlocks = {
  name: 'ignore-vue-custom-blocks',
  resolveId (id: string) {
    return isVueCustomBlock(id) ? id : null
  },
  load (id: string) {
    return isVueCustomBlock(id) ? 'export default {}' : null
  },
}

export default defineConfig({
  plugins: [
    ignoreVueCustomBlocks,
    vue({
      template: {
        compilerOptions: {
          compatConfig: {
            MODE: 2,
          },
        },
      },
    }),
  ],
  resolve: {
    alias: {
      '@DpJs': path.resolve(import.meta.dirname, 'client/js'),
      /*
       * Force ESM build of @vue/test-utils (otherwise, it would resolve to the CJS build which vite always externalizes,
       * which means @vue/test-utils would be resolved by node instead of by vite, and node wouldn't apply the @vue/compat
       * alias)
       */
      '@vue/test-utils': vueTestUtilsEsm(),
      vue: '@vue/compat',
    },
    conditions: ['import', 'module', 'browser', 'default'],
    extensions: ['.js', '.ts', '.vue', '.json'],
  },
  /*
   * Vitest runs in SSR mode and by default externalizes node_modules — the vue → @vue/compat alias would then NOT apply
   * to dependencies that `import default from 'vue'` (e.g. demosplan-ui's prebuilt bundle).
   * Force these packages through vite's transform pipeline so the alias takes effect. vue and @vue/compat are listed
   * too: without them the externalized copies resolve independently and tests run against a second, non-compat runtime
   */
  ssr: {
    noExternal: ['@vue/compat', 'vue', '@vue/test-utils', '@demos-europe/demosplan-ui', 'v-tooltip', 'vue-multiselect'],
  },
  test: {
    globals: true,
    environment: 'jsdom',
    ...(isUi && { api: { host: uiHost, port: 51204 } }),
    include: ['tests/frontend/**/*.{spec,test}.{js,ts}'],
    setupFiles: ['./tests/frontend/setup.ts'],
    reporters: isCi ? ['default', ['junit', { suiteName: 'Vitest Tests' }]] : ['default'],
    // The file name still says jest because the CI job collects the report from this exact path
    outputFile: { junit: '.build/jenkins-build-jest.junit.xml' },
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
    },
  },
})
