<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component is used as a wrapper for VersionHistoryDay.vue, which contains the table header for one day and is
   a wrapper for VersionHistoryItem.vue, which contains the changes for a certain time on that day
   -->
</documentation>

<template>
  <div v-if="entityId">
    <h2 class="u-mb-0_75">
      {{ versionHistoryHeading }}
    </h2>

    <dp-loading
      v-if="isLoading"
      data-cy="loadingSpinner"
      class="u-mt-1_5 u-ml"
    />

    <div
      v-else-if="!isLoading"
      class="c-slidebar__content overflow-y-auto"
      :class="{'u-mr': days.length === 0}"
      style="height: 88vh;"
    >
      <table class="u-mb">
        <thead class="sr-only">
          <tr>
            <th>
              {{ Translator.trans('history') }}
            </th>
          </tr>
        </thead>

        <tbody>
          <!-- if history is empty -->
          <tr v-if="!days || days.length === 0">
            <td
              data-cy="noEntries"
              colspan="4"
              class="u-mr"
            >
              <dp-inline-notification
                class="mt-3 mb-2"
                :message="Translator.trans('explanation.noentries')"
                type="info"
              />
            </td>
          </tr>
          <!-- if there are history items -->
          <!-- for each day -->
          <template v-if="days.length">
            <version-history-day
              v-for="(day, idx) in days"
              :key="idx"
              :procedure-id="procedureId"
              :date="day.attributes.day"
              :day="day"
              :all-times="times"
              :entity="entityType"
            />
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { dpApi, DpLoading } from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'
import VersionHistoryDay from './VersionHistoryDay.vue'

export default {
  name: 'VersionHistory',

  components: {
    DpInlineNotification: defineAsyncComponent(async () => {
      const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')

      return DpInlineNotification
    }),
    DpLoading,
    VersionHistoryDay,
  },

  props: {
    entityId: {
      type: String,
      default: '',
    },

    entityType: {
      type: String,
      default: '',
      validator: (value) => ['', 'fragment', 'segment', 'statement'].includes(value),
    },

    externId: {
      type: String,
      default: '',
    },

    procedureId: {
      type: String,
      required: true,
    },

    // Bumped by the parent to reload the history of the same entity, e.g. after it was edited
    reloadKey: {
      type: Number,
      default: 0,
    },
  },

  data () {
    return {
      days: [],
      isLoading: true,
      times: [],
    }
  },

  computed: {
    loadKey () {
      return `${this.entityType}:${this.entityId}:${this.reloadKey}`
    },

    versionHistoryHeading () {
      if (this.entityType === '' || this.externId === '') {
        return Translator.trans('history')
      }

      let entityKey

      switch (this.entityType) {
        case 'statement':
          if (this.externId.includes('GM')) {
            entityKey = Translator.trans('statement.cluster')
          } else {
            entityKey = Translator.trans('statement')
          }

          break
        case 'fragment':
          entityKey = Translator.trans('fragment')
          break
        case 'segment':
          entityKey = Translator.trans('segment')
          break
      }

      return `${entityKey} ${this.externId} - ${Translator.trans('history')}`
    },
  },

  watch: {
    loadKey: {
      handler () {
        if (this.entityId) {
          this.loadItems(this.entityId, this.entityType)
        }
      },
      immediate: true,
    },
  },

  methods: {
    loadItems (id, type) {
      this.isLoading = true
      const requests = {
        fragment: ['dplan_api_statement_fragment_history', { statementFragmentId: id, procedureId: this.procedureId }],
        segment: ['dplan_api_segment_history_get', { segmentId: id }],
        statement: ['dplan_api_statement_history_get', { statementId: id }],
      }
      const [route, params] = requests[type]

      return dpApi({
        method: 'GET',
        url: Routing.generate(route, params),
      })
        .then(({ data }) => {
          this.days = data.data
          this.times = data.included
          this.isLoading = false
        })
    },
  },
}
</script>
