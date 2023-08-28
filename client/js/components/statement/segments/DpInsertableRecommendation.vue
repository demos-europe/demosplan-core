<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <li class="flex flex-items-start space-inline-xs u-pr-0_5">
    <div class="flex flex-nowrap">
      <button
          :aria-label="Translator.trans('segment.recommendation.paste')"
          v-tooltip="{ boundariesElement: body, content: Translator.trans('segment.recommendation.paste'), classes: 'u-z-super' }"
          class="btn--blank color--grey"
          @click="$emit('insert-recommendation')">
        <i
          class="fa fa-files-o color--grey"
          aria-hidden="true" />
      </button>
    </div>
    <div
        v-if="fromOtherProcedure"
        class="min-width-m">
      <i
          v-tooltip="{
          boundariesElement: body,
          content: Translator.trans('segment.recommendation.other.procedure') + ': ' + procedureName ?? '',
          classes: 'u-z-super'
        }"
          :aria-label="Translator.trans('more.information')"
          class="fa fa-info-circle" />
    </div>
    <div
        v-if="isContentRec"
        class="flex flex-nowrap">
      <dp-badge
          class="color--white border-radius-extra-large whitespace--nowrap bg-color--grey u-mt-0_125"
          size="smaller"
          :text="Translator.trans('segment.oracle.score', { score: recommendationScore })" />
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
    </div>
  </li>
</template>

<script>
import { DpBadge, CleanHtml, Tooltip } from '@demos-europe/demosplan-ui'

// This number is used to shorten long texts.
const SHORT_TEXT_CHAR_LENGTH = 300

export default {
  name: 'DpInsertableRecommendation',

  components: {
    DpBadge
  },

  directives: {
    cleanhtml: CleanHtml,
    tooltip: Tooltip
  },

  props: {
    fromOtherProcedure: {
      type: Boolean,
      required: true
    },

    isContentRec: {
      type: Boolean,
      required: false,
      default: false
    },

    procedureName: {
      type: String,
      required: false,
      default: ''
    },

    recommendation: {
      type: String,
      required: true
    },

    recommendationScore: {
      type: Number,
      required: true
    },

    searchTerm: {
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
