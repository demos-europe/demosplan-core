/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

module.exports = {
  content: ['./components/**/*.{js,vue}', './directives/**/*.js'],
  corePlugins: {
    preflight: false // Enable later when removing demosplan base styles. See https://tailwindcss.com/docs/preflight
  },
  plugins: [],
  safelist: [
    {
      pattern: /./// Disable purging https://github.com/tailwindlabs/tailwindcss/discussions/6557#discussioncomment-1838214
    }
  ],
  theme: {
    extend: {}
  }
}
