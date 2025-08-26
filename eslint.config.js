const pluginVue = require('eslint-plugin-vue')
const pluginVueA11y = require('eslint-plugin-vuejs-accessibility')
const pluginJest = require('eslint-plugin-jest')
const js = require('@eslint/js')
const pluginImportExtensions = require('eslint-plugin-import')

module.exports = [
  {
    name: 'app/files-to-lint',
    files: ['**/*.{js,ts,vue}'],
  },
  {
    name: 'app/files-to-ignore',
    ignores: [
      '**/.cache/**/*',
      '**/addonDev/**/*',
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
    name: 'app/import-resolver',
    plugins: {
      import: pluginImportExtensions
    },
    settings: {
      'import/resolver': {
        alias: {
          map: [
            ['@DpJs', './client/js']
          ],
          extensions: ['.js', '.ts', '.vue', '.json']
        },
        node: {
          extensions: ['.js', '.ts', '.vue', '.json'],
          paths: ['./client/js', './node_modules']
        }
      }
    }
  },
  {
    name: 'app/js-recommended-rules',
    files: ['**/*.js'],
    languageOptions: {
      globals: {
        ...require('globals').node,
        ...require('globals').browser,
        ...require('globals').jest,
        $: 'readonly',
        jQuery: 'readonly',
        // Webpack DefinePlugin globals
        URL_PATH_PREFIX: 'readonly',
        PROJECT: 'readonly',
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'readonly',
        __VUE_OPTIONS_API__: 'readonly',
        __VUE_PROD_DEVTOOLS__: 'readonly',
        // DemosPlan specific globals
        Translator: 'readonly',
        Routing: 'readonly',
        hasPermission: 'readonly',
        dpconfirm: 'readonly',
        dplan: 'readonly',
      },
    },
    ...js.configs.recommended,
  },
  ...pluginVue.configs['flat/recommended'],
  ...pluginVueA11y.configs['flat/recommended'],
  {
    name: 'app/jest-rules',
    files: ['**/*.test.js', '**/*.spec.js', '**/tests/**/*.js'],
    plugins: {
      jest: pluginJest
    },
    languageOptions: {
      globals: {
        ...require('globals').jest
      }
    },
    rules: {
      ...pluginJest.configs.recommended.rules
    }
  },
  {
    name: 'app/import-rules',
    plugins: {
      import: pluginImportExtensions
    },
    rules: {
      // Prevent imports of files that don't exist or can't be resolved
      'import/no-unresolved': 'error',
      // Ensure named imports actually exist in the target module
      'import/named': 'error',
      // Validate default imports from modules that have default exports
      'import/default': 'error',
      // Validate namespace imports (import * as name) have valid exports
      'import/namespace': 'error'
    }
  },
  {
    name: 'app/custom/rules',
    rules: {
      // Do not allow file extensions when importing .js and .vue files, enforce extension on json files.
      'import/extensions': ['error', 'never', {
        json: 'always', js: 'never', vue: 'never'
      }],

      'capitalized-comments': ['error', 'always', {
        'ignoreConsecutiveComments': true,
        'ignoreInlineComments': true
      }],
      // Allow async-await
      'generator-star-spacing': 'off',

      'multiline-comment-style': 'error',
      // Allow debugger during development
      'no-debugger': process.env.NODE_ENV === 'production' ? 'error' : 'off',
      // Allow unused function parameters in vuex actions/mutations
      'no-unused-vars': ['error', {
        'args': 'none',
        'varsIgnorePattern': '^_',
        'argsIgnorePattern': '^_|^(state|commit|dispatch|getters|rootState|rootGetters)$',
        'caughtErrors': 'none' // Allow unused parameters in catch blocks
      }],
      'sort-imports': ['error', { 'ignoreCase': true }],
      'no-useless-escape': 'error',
      /**
       * Allow lowerCamelCase string, or multiple lowerCamelCase strings separated by colon
       * Remove this rule and v-on-event-hyphenation after renaming all custom events to match the recommended format
       */
      'vue/custom-event-name-casing': ['warn', 'camelCase', {
        'ignores': ['/^[a-z][a-zA-Z]*:?[[a-z][a-zA-Z]*]?$/']
      }],
      'vue/v-on-event-hyphenation': 'off',
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
      }]
    },
  },
  {
    name: 'app/custom/ally/rules',
    rules: {
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
]
