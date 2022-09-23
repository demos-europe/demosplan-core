/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { Tooltip } from '../directives/Tooltip/Tooltip'
import Vue from 'vue'

Vue.directive('tooltip', Tooltip)

const beautifyHtml = require('js-beautify').html

/**
 *
 * For some reason it doesn't work setting the Project dynamically
 * Even using a const with a fixed defined string breaks "everything"
 */
// let project = process.env.STORYBOOK_PROJECT ? process.env.STORYBOOK_PROJECT : 'blp'
// console.log(project)
// const rootPath = `../../../projects/blp/web`
// const manifest = require(`${rootPath}/dplan.manifest.json`)
const manifest = require(`../../../projects/blp/web/dplan.manifest.json`)

const fileName = manifest['css.css'].split('/')[6]

const head  = document.getElementsByTagName('head')[0]
const link  = document.createElement('link')
link.id   = fileName
link.rel  = 'stylesheet'
link.type = 'text/css'
// link.text = require(`${rootPath}/css/${fileName}`)
link.text = require(`../../../projects/blp/web/css/${fileName}`)
link.media = 'all';
head.appendChild(link);

export const parameters = {
  actions: { argTypesRegex: "^on[A-Z].*" },
  controls: {
    expanded: true,
    matchers: {
      color: /(background|color)$/i,
      date: /Date$/,
    },
  },
  docs: {
    transformSource: (src) => {
      /*
       * This strips the `<template>` tags from the source code shown in the "show code" view.
       * 'js-beautify' is responsible for fixing code indentation afterwards.
       */
      const strippedTemplate = src.substr(0, src.length - 12).substr(10)
      return beautifyHtml(strippedTemplate)
    }
  }
}
