<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-card :heading="Translator.trans('statements.grouped.status', { count: 0 })">
    <dp-loading
      v-if="isLoading"
      class="u-mt" />
    <div
      v-else
      class="mt-2">
      <div
        v-if="statementsTotal > 0"
        class="layout--flush">
        <p>
          {{ statementsTotal }} {{ Translator.trans('statements.total') }}
        </p>
        <div
          class="layout__item text-center u-1-of-3 u-1-of-1-lap-down mt-2"
          v-for="(element, idx) in procedureStatistics"
          :key="`statementCharts_${idx}`">
          <div
            :id="element.id"
            :data-items="JSON.stringify([{ label: element.label, count: element.count, percentage: element.percentage }])"
            :data-color="JSON.stringify(element.color)"
            :data-texts="JSON.stringify({
              'no-data-fallback' : Translator.trans('statements.none'),
              'data-names' : Translator.trans('statements'),
              'data-name' : Translator.trans('statement')
            })" />
          <div :id="element.legendId" />
        </div>
      </div>
      <p
        v-else
        class="u-mt u-mb-0">
        {{ Translator.trans('statements.none') }}
      </p>
    </div>
  </dp-card>
</template>

<script>
import { checkResponse, dpApi, DpCard, DpLoading } from '@demos-europe/demosplan-ui'
import ProcedureCharts from '@DpJs/components/procedure/charts/ProcedureCharts'

export default {
  name: 'DpStatementSegmentsStatusCharts',

  components: {
    DpCard,
    DpLoading
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      isLoading: true,
      procedureStatistics: [],
      statementsTotal: 0
    }
  },

  methods: {
    fetchStatisticsData () {
      const url = Routing.generate('dplan_rpc_procedure_segmentation_statistics_segmentations_get', { procedureId: this.procedureId })
      dpApi.get(url)
        .then(response => checkResponse(response))
        .then(response => {
          const { absolutes, percentages, total } = response.data.attributes
          this.statementsTotal = total
          this.procedureStatistics = [
            {
              label: Translator.trans('new'),
              count: absolutes.statementNewCount,
              percentage: percentages.statementNewCount,
              id: 'statementNewCount',
              legendId: 'statementNewCountLegend',
              color: 'text-status-progress-icon'
            },
            {
              label: Translator.trans('processing'),
              count: absolutes.statementProcessingCount,
              percentage: percentages.statementProcessingCount,
              id: 'statementProcessingCount',
              legendId: 'statementProcessingCountLegend',
              color: 'text-status-changed-icon'
            },
            {
              label: Translator.trans('completed'),
              count: absolutes.statementCompletedCount,
              percentage: percentages.statementCompletedCount,
              id: 'statementCompletedCount',
              legendId: 'statementCompletedCountLegend',
              color: 'text-status-complete-icon'
            }
          ]
          this.isLoading = false
          this.$nextTick(() => new ProcedureCharts())
        })
        .catch(err => {
          console.log(err)
          this.isLoading = false
        })
    }
  },

  created () {
    this.fetchStatisticsData()
  }
}
</script>
