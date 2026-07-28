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
      corejs: '3.9',
      useBuiltIns: 'usage',
    }],
  ],
  plugins: [
    '@babel/transform-runtime',
    '@babel/proposal-object-rest-spread',
    '@babel/syntax-dynamic-import',
  ],
  overrides: [
    {
      test: /\.vue$/,
      presets: [['@babel/preset-typescript', { allExtensions: true }]],
    },
    {
      test: /\.[mc]?tsx?$/,
      presets: ['@babel/preset-typescript'],
    },
    {
      plugins: [
        '@babel/transform-runtime',
        '@babel/proposal-object-rest-spread',
        '@babel/syntax-dynamic-import',
        '@babel/transform-object-assign',
        '@babel/transform-modules-commonjs',
      ],
    },
  ],
}

module.exports = config
