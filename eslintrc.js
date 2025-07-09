/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

// For better readability, all props are quoted in this file.
/* eslint-disable quote-props */

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
    // https://github.com/standard/standard/blob/master/RULES.md
    'standard'
  ],
  globals: {
    '$': true,
    dpconfirm: true,
    dplan: true,
    hasPermission: true,
    Routing: true,
    PROJECT: true,
    Translator: true,
    Vue: true
  },
  overrides: [
    {
      files: ['*.js', '*.vue']
    }
  ],
  ignorePatterns: [
    '**/addons/**/*',
    '**/local_modules/**/*',
    '**/node_modules/**/*',
    '**/vendor/**/*',
    'client/js/legacy/**/*.js',
    'client/js/generated/*.js',
    'projects/*/web/**/*'
  ],
  // Required to lint *.vue files
  plugins: [
    '@babel',
    'vue',
    'jest',
    'jquery',
    'vuejs-accessibility'
  ],
  // Add your custom rules here
  rules: {
    'capitalized-comments': ['error', 'always', {
      'ignoreConsecutiveComments': true,
      'ignoreInlineComments': true
    }],
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
    'no-useless-escape': 'error',
    // Allow lowerCamelCase string, or multiple lowerCamelCase strings separated by colon
    'vue/custom-event-name-casing': ['warn', 'camelCase', {
      'ignores': ['/^[a-z][a-zA-Z]*:?[[a-z][a-zA-Z]*]?$/']
    }],
    'vue/v-on-event-hyphenation': ['off'],
    'vue/html-closing-bracket-newline': ['off'],
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
        'emits',
        'data',
        'computed',
        'watch',
        'methods',
        'LIFECYCLE_HOOKS',
        ['template', 'render'],
        'renderError'
      ]
    }],
    'vue/v-slot-style': ['error', {
      'atComponent': 'longform',
      'default': 'longform',
      'named': 'longform'
    }],
    'vuejs-accessibility/alt-text': 'warn',
    'vuejs-accessibility/anchor-has-content': 'warn',
    'vuejs-accessibility/aria-props': 'warn',
    'vuejs-accessibility/aria-role': 'warn',
    'vuejs-accessibility/aria-unsupported-elements': 'warn',
    'vuejs-accessibility/click-events-have-key-events': 'warn',
    'vuejs-accessibility/form-control-has-label': 'warn',
    'vuejs-accessibility/heading-has-content': 'warn',
    'vuejs-accessibility/iframe-has-title': 'warn',
    'vuejs-accessibility/interactive-supports-focus': 'warn',
    'vuejs-accessibility/label-has-for': 'warn',
    'vuejs-accessibility/media-has-caption': 'warn',
    'vuejs-accessibility/mouse-events-have-key-events': 'warn',
    'vuejs-accessibility/no-access-key': 'warn',
    'vuejs-accessibility/no-autofocus': 'warn',
    'vuejs-accessibility/no-distracting-elements': 'warn',
    'vuejs-accessibility/no-onchange': 'warn',
    'vuejs-accessibility/no-redundant-roles': 'warn',
    'vuejs-accessibility/role-has-required-aria-props': 'warn',
    'vuejs-accessibility/tabindex-no-positive': 'warn'
  },
  settings: {
    'import/resolver': {
      webpack: {
        config: './config.webpack.js'
      }
    }
  }
}
