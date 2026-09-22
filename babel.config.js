/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

// babel.config.js
const config = {
  presets: [
    ['@babel/preset-env', {
      modules: false,
      debug: false,
    }],
  ],
  plugins: [
    '@babel/transform-runtime',
    ['polyfill-corejs3', { method: 'usage-global', version: '3.9' }],
  ],
  overrides: [
    {
      test: /\.vue$/,
      presets: [['@babel/preset-typescript', { ignoreExtensions: true }]],
    },
    {
      test: /\.[mc]?tsx?$/,
      presets: ['@babel/preset-typescript'],
    },
    {
      plugins: [
        '@babel/transform-runtime',
        '@babel/transform-modules-commonjs',
      ],
    },
  ],
}

module.exports = config
