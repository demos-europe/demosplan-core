<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component is used as a wrapper for DpVersionHistoryDay.vue, which contains the table header for one day and is
   a wrapper for DpVersionHistoryItem.vue, which contains the changes for a certain time on that day
   -->
</documentation>

<template>
  <div>
    <template>
      <h2 class="u-mb-0_75">
        {{ versionHistoryHeading }}
      </h2>

      <dp-loading
        v-if="isLoading"
        data-cy="loadingSpinner"
        class="u-mt-1_5 u-ml" />

      <div
        v-else-if="false === isLoading"
        class="c-slidebar__content overflow-y-auto"
        :class="{'u-mr': days.length === 0}"
        style="height: 88vh;">
        <table class="u-mb">
          <tr class="sr-only">
            <th>
              {{ Translator.trans('history') }}
            </th>
          </tr>
          <tr>
            <!-- if history is empty -->
            <td
              v-if="days === null || typeof days === 'undefined' || days.length === 0"
              data-cy="noEntries"
              colspan="4"
              class="u-mr">
              <dp-inline-notification
                type="info"
                :message="Translator.trans('explanation.noentries')"
              />
            </td>
          </tr>
          <!-- if there are history items -->
          <!-- for each day -->
          <template v-if="days.length">
            <dp-version-history-day
              v-for="(day, idx) in days"
              :procedure-id="procedureId"
              :key="idx"
              :date="day.attributes.day"
              :day="day"
              :all-times="times"
              :entity="entity" />
          </template>
        </table>
      </div>
    </template>
  </div>
</template>

<script>
import { checkResponse, dpApi, DpLoading } from '@demos-europe/demosplan-ui'
import DpVersionHistoryDay from './DpVersionHistoryDay'

export default {
  name: 'DpVersionHistory',

  components: {
    DpInlineNotification: async () => {
      const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')
      return DpInlineNotification
    },
    DpLoading,
    DpVersionHistoryDay
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      days: [],
      entity: '',
      entityId: null,
      externId: '',
      isLoading: true,
      times: []
    }
  },

  computed: {
    versionHistoryHeading () {
      let entityKey
      switch (this.entity) {
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
    }
  },

  methods: {
    loadItems (id, type) {
      this.isLoading = true
      const route = type === 'statement'
        ? 'dplan_api_statement_history_get'
        : type === 'segment'
          ? 'dplan_api_segment_history_get'
          : 'dplan_api_statement_fragment_history'

      const params = type === 'statement'
        ? { statementId: id }
        : type === 'segment'
          ? { segmentId: id }
          : { statementFragmentId: id, procedureId: this.procedureId }

      this.entityId = id
      return dpApi({
        method: 'GET',
        url: Routing.generate(route, params)
      })
        .then(response => checkResponse(response))
        .then(response => response)
        .then(response => {
          this.days = response.data
          this.times = response.included
          this.isLoading = false
        })
        .catch(error => checkResponse(error.response))
    },

    updateVersionHistory (entityId, entityType) {
      if (entityId === this.entityId) {
        this.loadItems(entityId, entityType)
      }
    }
  },

  mounted () {
    // Emitted by TableCardFlyoutMenu
    this.$root.$on('version:history', (entityId, entityType, externId) => {
      this.externId = externId
      this.loadItems(entityId, entityType)
      this.entity = entityType
    })

    this.$root.$on('entity:updated', (entityId, entityType) => {
      this.updateVersionHistory(entityId, entityType)
    })
  }
}
</script>
