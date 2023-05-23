/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

// babel.config.js
let config = {
  presets: [
    ['@babel/preset-env', {
      modules: false,
      debug: false,
      corejs: '3.9',
      useBuiltIns: 'usage'
    }]
  ],
  plugins: [
    '@babel/transform-runtime',
    '@babel/proposal-object-rest-spread',
    '@babel/syntax-dynamic-import'
  ],
  overrides: [{
    plugins: [
      '@babel/transform-runtime',
      '@babel/proposal-object-rest-spread',
      '@babel/syntax-dynamic-import',
      '@babel/transform-object-assign',
      '@babel/transform-modules-commonjs'
    ]
  }]
}

if (process.NODE_ENV === 'testing') {
  // This only enables dynamic imports for testing in js files, vue is configured in jest config
  config.plugins.push(['dynamic-import-node', { noInterop: true }])
}

module.exports = config
