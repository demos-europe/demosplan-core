/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { defineConfig } from 'vitest/config'
import path from 'node:path'
import vue from '@vitejs/plugin-vue'

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
      vue: '@vue/compat',
    },
    conditions: ['import', 'module', 'browser', 'default'],
    extensions: ['.js', '.ts', '.vue', '.json'],
  },
  /*
   * Vitest runs in SSR mode and by default externalizes node_modules — the
   * vue → @vue/compat alias would then NOT apply to dependencies that
   * `import default from 'vue'` (e.g. demosplan-ui's prebuilt bundle).
   * Force these packages through Vite's transform pipeline so the alias
   * takes effect.
   */
  ssr: {
    noExternal: ['@demos-europe/demosplan-ui', 'v-tooltip', 'vue-multiselect'],
  },
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['tests/frontend/**/*.{spec,test}.{js,ts}'],
    setupFiles: ['./tests/frontend/setup.ts'],
    reporters: ['default'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
    },
  },
})
