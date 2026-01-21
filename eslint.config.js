const pluginVue = require('eslint-plugin-vue')
const pluginVueA11y = require('eslint-plugin-vuejs-accessibility')
const pluginJest = require('eslint-plugin-jest')
const pluginJquery = require('eslint-plugin-jquery')
const js = require('@eslint/js')
const pluginImportExtensions = require('eslint-plugin-import')

module.exports = [
  {
    name: 'app/files-to-lint',
    files: ['**/*.{js,ts,vue}'],
  },
  {
    name: 'app/global-variables',
    languageOptions: {
      globals: {
        Translator: 'readonly',
        Routing: 'readonly',
        hasPermission: 'readonly',
        dpconfirm: 'readonly',
        dplan: 'readonly',
        $: 'readonly',
        jQuery: 'readonly',
      },
    },
  },
  {
    name: 'app/files-to-ignore',
    ignores: [
      '**/.cache/**/*',
      '**/addonDev/**/*',
      '**/addons/**/*',
      '**/client/js/legacy/**/*.js',
      '**/client/js/generated/*.js',
      '**/client/js/routing.js',
      '**/local_modules/**/*',
      '**/documentation/**/*',
      '**/node_modules/**/*',
      '**/projects/*/web/**/*',
      '**/vendor/**/*',
    ],
  },
  {
    name: 'app/import-resolver',
    plugins: {
      import: pluginImportExtensions,
    },
    settings: {
      'import/resolver': {
        alias: {
          map: [
            ['@DpJs', './client/js'],
          ],
          extensions: ['.js', '.ts', '.vue', '.json'],
        },
        node: {
          extensions: ['.js', '.ts', '.vue', '.json'],
          paths: ['./client/js', './node_modules'],
        },
      },
    },
  },
  {
    name: 'app/js-recommended-rules',
    files: ['**/*.js'],
    languageOptions: {
      globals: {
        ...require('globals').node,
        ...require('globals').browser,
        ...require('globals').jest,
        // Webpack DefinePlugin globals
        URL_PATH_PREFIX: 'readonly',
        PROJECT: 'readonly',
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'readonly',
        __VUE_OPTIONS_API__: 'readonly',
        __VUE_PROD_DEVTOOLS__: 'readonly',
      },
    },
    ...js.configs.recommended,
  },
  ...pluginVue.configs['flat/recommended'],
  ...pluginVueA11y.configs['flat/recommended'],
  {
    name: 'app/jquery-rules',
    plugins: {
      jquery: pluginJquery,
    },
    rules: {
      // Warn about deprecated jQuery methods
      ...Object.fromEntries(
        Object.entries(pluginJquery.configs.deprecated.rules)
          .map(([rule, config]) => [rule, 'warn']),
      ),
    },
  },
  {
    name: 'app/jest-rules',
    files: ['**/*.test.js', '**/*.spec.js', '**/tests/**/*.js'],
    plugins: {
      jest: pluginJest,
    },
    languageOptions: {
      globals: {
        ...require('globals').jest,
      },
    },
    rules: {
      ...pluginJest.configs.recommended.rules,
    },
  },
  {
    name: 'app/import-rules',
    plugins: {
      import: pluginImportExtensions,
    },
    rules: {
      // Prevent imports of files that don't exist or can't be resolved
      'import/no-unresolved': 'error',
      // Ensure named imports actually exist in the target module
      'import/named': 'error',
      // Validate default imports from modules that have default exports
      'import/default': 'error',
      // Validate namespace imports (import * as name) have valid exports
      'import/namespace': 'error',
    },
  },
  {
    name: 'app/standard-formatting-rules',
    rules: {
      // Standard.js style formatting rules
      'semi': ['error', 'never'],                         // No semicolons
      'quotes': ['error', 'single', { 'avoidEscape': true }], // Single quotes, but allow double quotes to avoid escaping
      'indent': ['error', 2, { 'SwitchCase': 1 }],        // 2 spaces indentation, switch cases indented
      'space-before-function-paren': ['error', 'always'], // E.g. function ()
      'comma-dangle': ['error', 'always-multiline'],      // Trailing commas on multiline
      'space-infix-ops': 'error',                         // Space around operators, e.g. a + b, not a+b
      'space-before-blocks': 'error',                     // Space before blocks, e.g. function () {
      'keyword-spacing': 'error',                         // Space after keywords, e.g. if (condition) {, return , else {
      'object-curly-spacing': ['error', 'always'],        // Space inside objects, e.g. { key: value }
      'array-bracket-spacing': ['error', 'never'],        // No space in arrays: [1, 2, 3], not [ 1, 2, 3 ]
      'brace-style': ['error', '1tbs', { 'allowSingleLine': true }], // One true brace style, allow single line
      'eol-last': 'error',                                // Files must end with newline
      'no-trailing-spaces': 'error',                      // No trailing whitespace at end of lines
      'comma-spacing': ['error', { 'before': false, 'after': true }], // Space after commas
      'key-spacing': ['error', { 'beforeColon': false, 'afterColon': true }], // Space after colons in objects, e.g. key: value
      'space-in-parens': ['error', 'never'],              // No space in parentheses, e.g. func(arg), not func( arg )
      'block-spacing': 'error',                           // Space in single-line blocks, e.g. { return true }
      'computed-property-spacing': ['error', 'never'],    // No space in computed properties e.g. obj[key], not obj[ key ]
      'func-call-spacing': ['error', 'never'],            // No space between function and parentheses, e.g. func()
      'no-multiple-empty-lines': ['error', { 'max': 2, 'maxEOF': 1 }], // Limit empty lines
      'padded-blocks': ['error', 'never'],                // No empty line at start or end of block
      'space-unary-ops': 'error',                         // Space with unary operators, e.g. typeof x, not typeofx
      'operator-linebreak': ['error', 'after'],           // When breaking lines, operator goas at end, e.g. a +\n b, not a\n + b
      'spaced-comment': ['error', 'always'],              // Space after comment markers, e.g. // Comment
    },
  },
  {
    name: 'app/custom/rules',
    rules: {
      // Do not allow file extensions when importing .js and .vue files, enforce extension on json files.
      'import/extensions': ['error', 'never', {
        json: 'always', js: 'never', vue: 'never',
      }],

      'capitalized-comments': ['error', 'always', {
        'ignoreConsecutiveComments': true,
        'ignoreInlineComments': true,
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
        'caughtErrors': 'none', // Allow unused parameters in catch blocks
      }],
      'sort-imports': ['error', { 'ignoreCase': true }],
      'no-useless-escape': 'error',
      /**
       * Allow lowerCamelCase string, or multiple lowerCamelCase strings separated by colon
       * Remove this rule and v-on-event-hyphenation after renaming all custom events to match the recommended format
       */
      'vue/custom-event-name-casing': ['warn', 'camelCase', {
        'ignores': ['/^[a-z][a-zA-Z]*(?::[a-z][a-zA-Z]*)?$/'],
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
          'renderError',
        ],
      }],
      'vue/v-slot-style': ['error', {
        'atComponent': 'longform',
        'default': 'longform',
        'named': 'longform',
      }],
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
      'vuejs-accessibility/tabindex-no-positive': 'warn',
    },
  },
]
