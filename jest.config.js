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
    'tests/frontend/'
  ],
  moduleDirectories: [
    'node_modules'
  ],
  moduleFileExtensions: [
    'js',
    'json',
    'vue'
  ],
  moduleNameMapper: {
    ...roots,
    '^@vue/test-utils': '<rootDir>/node_modules/@vue/test-utils/dist/vue-test-utils.cjs.js'
  },
  modulePaths: [
    '<rootDir>'
  ],
  transform: {
    '^.+\\.js$': '<rootDir>/node_modules/babel-jest',
    '.*\\.(vue)$': '<rootDir>/node_modules/@vue/vue3-jest'
  },
  transformIgnorePatterns: [
    '/node_modules/demosplan-ui'
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
        outputDirectory: '.build/'
      }
    ]
  ],
  globals: {
    '@vue/vue3-jest': {
      babelConfig: {
        plugins: ['dynamic-import-node']
      }
    }
  }
}
