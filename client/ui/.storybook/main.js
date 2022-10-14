/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

module.exports = {
  stories: [
    "../components/**/*.stories.mdx",
    "../components/**/*.stories.@(js|jsx|ts|tsx)",
    "../directives/**/*.stories.mdx",
    "../tokens/**/*.stories.mdx"
  ],
  addons: [
    "@storybook/addon-links",
    "@storybook/addon-essentials"
  ],
  webpackFinal: async config => {
    /**
     * This rule is executed first. It ensures that the <license> blocks at the top
     * of Vue components do break vue-docgen-loader. The loader returns an empty string.
     */
    config.module.rules.push({
      resourceQuery: /blockType=license/,
      loader: require.resolve('./eraseLicenseLoader.js')
    })
    return config
  }
}
