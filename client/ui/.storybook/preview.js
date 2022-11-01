/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { Tooltip } from '../directives'
import Vue from 'vue'

Vue.directive('tooltip', Tooltip)

const beautifyHtml = require('js-beautify').html

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
