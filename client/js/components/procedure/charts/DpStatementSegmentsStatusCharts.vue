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
      class="u-mt">
      <div
        v-if="statementsTotal > 0"
        class="layout--flush">
        <p class="u-mb">
          {{ statementsTotal }} {{ Translator.trans('statements.total') }}
        </p>
        <div
          class="layout__item text-center u-1-of-3 u-1-of-1-lap-down"
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
              count: absolutes.unsegmented,
              percentage: percentages.unsegmented,
              id: 'statementsNew',
              legendId: 'statementsNewLegend',
              color: 'c-chart__color-3-1'
            },
            {
              label: Translator.trans('segmented'),
              count: absolutes.segmented,
              percentage: percentages.segmented,
              id: 'statementsSegmented',
              legendId: 'statementsSegmentedLegend',
              color: 'c-chart__color-3-2'
            },
            {
              label: Translator.trans('replied.to'),
              count: absolutes.recommendationsFinished,
              percentage: percentages.recommendationsFinished,
              id: 'statementsRecommendationsFinished',
              legendId: 'statementsRecommendationsFinishedLegend',
              color: 'c-chart__color-3-3'
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
