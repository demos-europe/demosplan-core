/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const glob = require('glob')
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

    this.cssPurge = {
      /**
       * Especially when larger changes to either this config, or the set of available css classes
       * are made, it may be advisable to set PurgeCSS to always run. Under normal circumstances,
       * this enabled flag should typically just be `enabled: this.isProduction`.
       */
      enabled: true,
      paths: [
        `projects/${project}/**/*.vue`,
        `projects/${project}/**/*.html.twig`,
        'templates/**/*.html.twig',
        'demosplan/**/*.vue',
        'demosplan/**/*.js',
        'demosplan/**/*.js.twig',
        'client/**/*.js',
        'client/**/*.vue',
        ...glob.sync('node_modules/@demos-europe/demosplan-ui/dist/**/*.js', { nodir: true })
      ],
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
          /plyr-.+/,
          /uppy-.+/,
          /color-.+/,
          /tabs-component.*/
        ],
        deep: [
          /split-statement/
        ],
        greedy: [
          /tooltip/
        ]
      }
    }

    this.cssPrefixExcludes = {
      externalClassPrefixes: [
        'ad-', // Classes for the a11y-datepicker
        'cc-', // Classes for the cookieconsent banner (cc-banner etc)
        'ol-', // Classes for open layers (to prevent our overrides)
        'plupload_', // Classes for plUpload (to prevent our overrides)
        'v-tooltip', // V-tooltip
        'tooltip' // V-tooltip
      ],
      defaultExcludePatterns: [
        'has-tooltip'
      ]
    }

    /*
     * This.staticScripts = [
     *   'node_modules/jquery/dist/jquery.min.js'
     * ]
     */
  }
}

const config = Object.freeze(new Config(process.env.NODE_ENV, process.env.project))

module.exports = { config }
