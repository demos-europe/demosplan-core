<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <!-- Toggle -->
    <a
      class="block cursor-pointer border--top u-pt-0_25"
      @click="toggle"
      :class="{'is-active-toggle': isActive}">
      <i
        class="o-toggle__icon o-toggle__icon--caret u-pr-0_25"
        aria-hidden="true" />
      {{ Translator.trans('consideration.versions') }}
    </a>

    <!-- Content -->
    <div
      v-show="isActive"
      class="max-h-12 overflow-x-auto u-mv-0_25">
      <dp-loading v-if="items === null" />

      <div v-if="items && items.length === 0">
        {{ Translator.trans('consideration.versions.none') }}
      </div>

      <div v-else-if="items === false">
        {{ Translator.trans('consideration.versions.error') }}
      </div>

      <div
        v-else
        v-for="item in items"
        :key="item.id"
        class="layout__item u-pl-0 u-pr-0_5 u-mv-0_25">
        <div class="border--bottom u-mb-0_25">
          <div class="inline-block u-1-of-4">
            <div
              class="u-mr inline-block weight--bold cursor-help"
              :title="Translator.trans('date')">
              {{ itemCreatedDate(item) }}
            </div>
          </div><!--

       --><div class="inline-block text-right u-3-of-4">
            <dp-fragment-status
              v-if="hasPermission('feature_statements_fragment_advice')"
              :status="item.voteAdvice === null ? '' : fixCompoundVotes(item.voteAdvice)"
              :tooltip="false"
              :badge="true"
              class="inline-block u-mv-0_25 u-mh-0_5"
              v-once>
              <template v-slot:title>
                  {{ Translator.trans('fragment.voteAdvice.short') }}
              </template>
            </dp-fragment-status>
            <dp-fragment-status
              v-if="hasPermission('feature_statements_fragment_vote')"
              :status="item.vote === null ? '' : item.vote"
              :tooltip="false"
              class="inline-block u-mv-0_25 u-mh-0_5"
              :badge="true">
              <template v-slot:title>
                  {{ Translator.trans('fragment.vote.short') }}
              </template>
            </dp-fragment-status>
          </div>
        </div>

        <div
          class="cursor-help"
          v-cleanhtml="item.considerationAdvice ? item.considerationAdvice : item.consideration"
          :title="Translator.trans('fragment.consideration')">
          {{ item.consideration }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { checkResponse, CleanHtml, dpApi, DpLoading, formatDate } from '@demos-europe/demosplan-ui'
import FragmentStatus from './Status'

export default {
  // eslint-disable-next-line vue/multi-word-component-names
  name: 'Version',

  components: {
    'dp-fragment-status': FragmentStatus,
    DpLoading
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    fragmentId: {
      required: true,
      type: String
    },

    statementId: {
      required: true,
      type: String
    },

    procedureId: {
      required: false,
      type: String,
      default: ''
    },

    isReviewer: {
      required: false,
      type: Boolean,
      default: true
    }
  },

  data: function () {
    return {
      isActive: false,
      items: null
    }
  },

  methods: {
    /**
     * This method is a temporary fix for fragment votes that follow the pattern of fragment.vote.(full || acknowledge || partial || ...)
     * dp-fragment-vote needs a status that uses only the last part of the compound string (e.g. 'fragment.vote.full' becomes 'full')
     */
    fixCompoundVotes (vote) {
      let decompoundVote = vote
      if (vote.match(/fragment\.vote\./)) {
        decompoundVote = vote.replace(/fragment\.vote\./, '')
      }
      return decompoundVote
    },

    itemCreatedDate (item) {
      return formatDate(item.created)
    },

    toggle () {
      this.isActive = !this.isActive

      if (!this.items) {
        this.load()
      }
    },

    load () {
      let url

      //  Setting this to null to force ui into loading state
      this.items = null

      if (this.isReviewer) {
        url = Routing.generate('dplan_assessment_fragment_get_consideration_versions_reviewer', {
          fragmentId: this.fragmentId
        })
      } else {
        url = Routing.generate('dplan_assessment_fragment_get_consideration_versions', {
          fragmentId: this.fragmentId,
          ident: this.procedureId
        })
      }

      dpApi.get(url)
        .then(checkResponse)
        .then(responseData => {
          this.items = responseData.data
          this.items.pop()
        })
        .catch(() => {
          //  An error message is displayed in the component
          this.items = false
        })
    }
  }
}
</script>
