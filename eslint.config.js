const pluginVue = require('eslint-plugin-vue')
// const pluginVueA11y = require('eslint-plugin-vuejs-accessibility')
// const pluginImportExtensions = require('eslint-plugin-import')

module.exports = [
  {
    name: 'app/files-to-lint',
    files: ['**/*.{js,ts,vue}'],
  },
  {
    name: 'app/files-to-ignore',
    ignores: [
      '**/.cache/**/*',
      '**/addons/**/*',
      '**/client/js/legacy/**/*.js',
      '**/client/js/generated/*.js',
      '**/local_modules/**/*',
      '**/documentation/**/*',
      '**/node_modules/**/*',
      '**/projects/*/web/**/*',
      '**/vendor/**/*'
    ],
  },
  {
    name: 'app/overrides',
    settings: {
      'import/resolver': {
        alias: {
          map: [
            ['@DpJs', './client/js']
          ],
          extensions: ['.js', '.ts', '.vue']
        }
      }
    }
  },
  // ...pluginVue.configs['flat/vue2-essential'],
  // ...pluginVueA11y.configs['flat/recommended'],
  /*
   * Do not allow file extensions when importing .js and .vue files,
   * enforce extension on json files.
   */
  // Not working by now https://github.com/Kenneth-Sills/eslint-config-airbnb-typescript/issues/15
  // {
  //   'import/extensions': ['error', 'never', { json: 'always' }]
  // },
  {
    rules: {
      'capitalized-comments': ['error', 'always', {
        'ignoreConsecutiveComments': true,
        'ignoreInlineComments': true
      }],
      // Allow async-await
      'generator-star-spacing': 'off',

      'multiline-comment-style': 'error',
      // Allow debugger during development
      'no-debugger': process.env.NODE_ENV === 'production' ? 'error' : 'off',
      'sort-imports': ['error', { 'ignoreCase': true }],
      'no-useless-escape': 'error',
      // Allow lowerCamelCase string, or multiple lowerCamelCase strings separated by colon
      'vue/custom-event-name-casing': ['warn', 'camelCase', {
        'ignores': ['/^[a-z][a-zA-Z]*:?[[a-z][a-zA-Z]*]?$/']
      }],
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
  }
];
