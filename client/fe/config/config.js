/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const path = require('path')

class Config {
  constructor (mode, project) {
    if (!Config.instance) {
      Config.instance = this
      this.init(mode, project)
    }

    return Config.instance
  }

  init (mode, project) {
    this.defaults(mode, project)

    if (mode !== 'testing' && project) {
      Object.assign(this, require('./projectConfig').projectConfig(mode, project))
    }
  }

  defaults (mode, project) {
    this.relativeRoot = '../../../'
    this.isProduction = (mode === 'production')
    this.mode = (mode === 'production') ? 'production' : mode

    this.absoluteRoot = path.resolve(__dirname, this.relativeRoot) + '/'
    this.oldBundlesPath = path.resolve(__dirname, this.relativeRoot + 'demosplan/') + '/'

    // Yes, technically this is not needed, but it's here to document the possible use in `resolveAliases`.
    const clientBundlesPath = path.resolve(__dirname, this.relativeRoot) + '/client/js/bundles'
    this.clientBundleGlob = clientBundlesPath + '/**/*.js'

    this.purgeCss = {
      // These are all places where purgeCss will look for code which contains css selectors.
      content: [
        'addons/vendor/demos-europe/demosplan-addon-*/client/**/*.{js,vue}',
        'client/**/*.{js,vue}',
        '{demosplan,templates}/**/*.html.twig',
        'node_modules/@demos-europe/demosplan-ui/dist/**/*.js',
        `projects/${project}/**/*.{vue,html.twig}`
      ],
      // These are css selectors that may be generated dynamically, or otherwise go unnoticed.
      safelist: {
        standard: [
          /-(leave|enter|appear)(-(to|from|active)|)$/,
          /^(?!(.*?:|)cursor-move).+-move$/,
          /^router-link(-exact|)-active$/,
          /data-v-.+/,
          /c-notify.+/,
          /menu_level_/,
          /knp-*/,
          /current_ancestor/,
          /multiselect.*/,
          /c-sliding-pagination.*/,
          /a1-.+/,
          /data-enhance-url-field/,
          /ol-.+/,
          /plyr.+/,
          /uppy-.+/,
          /^color-.+/
        ],
        greedy: [
          /tooltip/,
          /swagger-ui/
        ]
      }
    }

    this.cssPrefixExcludes = {
      externalClassPrefixes: [
        'ad-', // Classes for the a11y-datepicker
        'cc-', // Classes for the cookie consent banner (cc-banner etc)
        'ol-', // Classes for open layers (to prevent our overrides)
        'plupload_', // Classes for plUpload (to prevent our overrides)
        'v-tooltip', // V-tooltip
        'tooltip' // V-tooltip
      ],
      defaultExcludePatterns: [
        'has-tooltip'
      ]
    }
  }
}

const config = Object.freeze(new Config(process.env.NODE_ENV, process.env.project))

module.exports = { config }
