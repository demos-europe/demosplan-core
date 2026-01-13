/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const config = require('./client/fe/config/config').config
const webpackConfig = require('./config.webpack')

const aliases = webpackConfig[0].resolve.alias

delete aliases.vue

const roots = {}
for (const alias in aliases) {
  const from = alias + '/(.*)$'
  const to = aliases[alias] + '/$1'

  roots[from] = to
}

module.exports = {
  // Verbose: true, // enable to see result of each test case
  testEnvironment: 'jsdom',
  testRegex: '/tests/.*(test|spec)\\.js?$',
  rootDir: config.absoluteRoot,
  roots: [
    'tests/frontend/',
  ],
  moduleDirectories: [
    'node_modules',
  ],
  moduleFileExtensions: [
    'js',
    'json',
    'vue',
  ],
  moduleNameMapper: {
    ...roots,
    '^@vue/test-utils': '<rootDir>/node_modules/@vue/test-utils/dist/vue-test-utils.cjs.js',
  },
  modulePaths: [
    '<rootDir>',
  ],
  transform: {
    '^.+\\.(js|mjs)$': '<rootDir>/node_modules/babel-jest',
    '.*\\.(vue)$': '<rootDir>/node_modules/@vue/vue3-jest',
  },
  transformIgnorePatterns: [
    /*
     * Transform ESM packages (demosplan-ui and its ESM dependencies).
     * Jest requires CommonJS, so we must transform:
     * - @demos-europe/demosplan-ui (ESM-only since v0.7.0)
     * - External dependencies: Packages that are externalized in demosplan-ui's
     *   vite.config.mjs but imported as ESM by consuming code
     */
    'node_modules/(?!(@demos-europe/demosplan-ui|demosplan-ui|uuid|@uppy|nanoid|preact|dayjs|tippy\\.js|v-tooltip|vue-multiselect|vuedraggable|dompurify|@braintree))',
  ],
  // Send a notification when tests fail or once when they pass
  notifyMode: 'failure-success',
  reporters: [
    'default',
    [
      'jest-junit',
      {
        suiteName: 'Jest Tests',
        outputName: 'jenkins-build-jest.junit.xml',
        outputDirectory: '.build/',
      },
    ],
  ],
}
