<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li class="flex flex-items-start space-inline-xs u-pr-0_5">
    <div class="min-width-m">
      <i
        v-if="fromOtherProcedure"
        v-tooltip="{
          boundariesElement: body,
          content: Translator.trans('segment.recommendation.other.procedure') + ': ' + procedureName,
          classes: 'u-z-super'
        }"
        :aria-label="Translator.trans('more.information')"
        class="fa fa-info-circle" />
    </div>
    <div
      class="flex-grow"
      v-cleanhtml="recommendationText" />
    <div class="flex flex-nowrap space-inline-s">
      <button
        class="btn--blank o-link--default"
        :aria-label="Translator.trans(isExpanded ? 'dropdown.close' : 'dropdown.open')"
        v-tooltip="{ boundariesElement: body, content: Translator.trans(isExpanded ? 'dropdown.close' : 'dropdown.open'), classes: 'u-z-super' }"
        v-if="canExpand"
        @click="toggleExpanded">
        <i
          aria-hidden="true"
          class="fa"
          :class="isExpanded ? 'fa-angle-up' : 'fa-angle-down'" />
      </button>
      <button
        :aria-label="Translator.trans('segment.recommendation.paste')"
        v-tooltip="{ boundariesElement: body, content: Translator.trans('segment.recommendation.paste'), classes: 'u-z-super' }"
        class="btn--blank o-link--default"
        @click="$emit('insert-recommendation')">
        <i
          class="fa fa-files-o"
          aria-hidden="true" />
      </button>
    </div>
  </li>
</template>

<script>
import { CleanHtml } from 'demosplan-ui/directives'

// This number is used to shorten long texts.
const SHORT_TEXT_CHAR_LENGTH = 300

export default {
  name: 'DpInsertableRecommendation',

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    fromOtherProcedure: {
      type: Boolean,
      required: true
    },

    recommendation: {
      type: String,
      required: true
    },

    searchTerm: {
      type: String,
      required: false,
      default: ''
    },

    procedureName: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      isExpanded: false,
      shortText: this.shortenHtmlText(this.recommendation)
    }
  },

  computed: {
    canExpand () {
      return this.recommendation.length > SHORT_TEXT_CHAR_LENGTH
    },

    body () {
      return document.body
    },

    recommendationText () {
      const shouldTruncate = !this.isExpanded && this.canExpand

      const shortDisplayText = this.searchTerm !== ''
        ? this.shortText.replace(this.searchRegex, '<span style="background-color: yellow;">$&</span>') + '...'
        : this.shortText

      const fullText = this.searchTerm !== ''
        ? this.recommendation.replace(this.searchRegex, '<span style="background-color: yellow;">$&</span>')
        : this.recommendation

      return shouldTruncate ? shortDisplayText : fullText
    },

    searchRegex () {
      // Match the search term except when the term occurs within an html-tag
      return new RegExp(this.searchTerm + '(?![^<]*>)', 'ig')
    }
  },

  methods: {
    shortenHtmlText (text) {
      let textOnly = document.createElement('div')
      textOnly.innerHTML = text
      textOnly = textOnly.textContent.substring(0, SHORT_TEXT_CHAR_LENGTH)
      return textOnly
    },

    toggleExpanded () {
      this.isExpanded = !this.isExpanded
    }
  }
}
</script>
