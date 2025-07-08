/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
process.env.NODE_ENV = 'development'

module.exports = {
  root: true,
  parserOptions: {
    parser: '@babel/eslint-parser'
  },
  env: {
    browser: true
  },
  extends: [
    /*
     * https://github.com/vuejs/eslint-plugin-vue#priority-a-essential-error-prevention
     * Consider switching to `plugin:vue/recommended` for stricter rules.
     */
    'plugin:vue/strongly-recommended',
    'plugin:jest/recommended',
    'plugin:jquery/deprecated',
    // https://github.com/standard/standard/blob/master/docs/RULES-en.md
    'standard'
  ],
  globals: {
    dplan: true,
    hasPermission: true,
    Routing: true,
    Translator: true
  },
  overrides: [
    {
      files: ['*.js', '*.vue'],
      excludedFiles: 'client/js/legacy/**/*.js'
    }
  ],
  // Required to lint *.vue files
  plugins: [
    'vue',
    'jest',
    'jquery'
  ],
  // Add your custom rules here
  rules: {
    'capitalized-comments': 'error',
    // Allow async-await
    'generator-star-spacing': 'off',
    /*
     * Do not allow file extensions when importing .js and .vue files,
     * enforce extension on json files.
     */
    'import/extensions': ['error', {
      js: 'never',
      vue: 'never',
      json: 'always'
    }],
    'multiline-comment-style': 'error',
    // Allow debugger during development
    'no-debugger': process.env.NODE_ENV === 'production' ? 'error' : 'off',
    'sort-imports': ['error', { 'ignoreCase': true }],
    'vue/html-closing-bracket-newline': ['error', {
      'singleline': 'never',
      'multiline': 'never'
    }],
    'vue/order-in-components': ['error', {
      'order': [
        'el',
        'name',
        'parent',
        'functional',
        ['delimiters', 'comments'],
        ['components', 'directives', 'filters'],
        'extends',
        'mixins',
        'inheritAttrs',
        'model',
        ['props', 'propsData'],
        'data',
        'computed',
        'watch',
        'methods',
        'LIFECYCLE_HOOKS',
        ['template', 'render'],
        'renderError'
      ]
    }]
  },
  settings: {
    'import/resolver': {
      webpack: {
        config: './config.webpack.js'
      }
    }
  }
}
