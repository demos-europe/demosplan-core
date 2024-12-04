/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

module.exports = {
  extends: 'stylelint-config-standard-scss',
  ignoreFiles: [
    'demosplan/DemosPlanCoreBundle/Resources/client/scss/inuit-5/*.scss'
  ],
  rules: {
    'at-rule-empty-line-before': null,
    'block-no-empty': true,
    'declaration-empty-line-before': null,
    'indentation': 4,
    'max-empty-lines': 2,
    'no-descending-specificity': null,
    'number-leading-zero': 'never',
    'scss/at-extend-no-missing-placeholder': null, // Fontawesome does not provide placeholders for individual icons
    'scss/at-import-no-partial-leading-underscore': null,
    'scss/at-rule-conditional-no-parentheses': null,
    'scss/at-rule-no-unknown': [
      true, // Allow @tailwind expression while keeping the rest of the rule
      {
        'ignoreAtRules': [
          'extends',
          'tailwind'
        ]
      }
    ],
    'scss/comment-no-empty': null,
    'scss/dollar-variable-colon-space-after': null,
    'scss/dollar-variable-empty-line-before': null,
    'scss/dollar-variable-pattern': null,
    'scss/double-slash-comment-empty-line-before': null,
    'scss/percent-placeholder-pattern': null,
    'selector-class-pattern': null,
    'selector-id-pattern': null,
    'string-quotes': 'single',
    'value-keyword-case': [
      'lower',
      {
        ignoreKeywords: [
          'Roboto',
          'Arial'
        ]
      }
    ]
  }
}
