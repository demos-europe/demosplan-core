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
    '@babel/preset-typescript',
  ],
  plugins: [
    '@babel/transform-runtime',
    '@babel/proposal-object-rest-spread',
    '@babel/syntax-dynamic-import',
  ],
}

module.exports = config
