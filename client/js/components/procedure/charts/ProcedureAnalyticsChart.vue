<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-card
    :heading="Translator.trans('analytics.visitorMetrics')"
    :heading-tooltip="Translator.trans('analytics.visitorMetrics.contextualHelp')">
    <dp-loading
      v-if="isLoading"
      class="u-mt-0_5" />
    <div
      v-else
      class="u-mt-0_5">
      <div v-if="analyticsData.length">
        <template v-if="totalViews > 0">
          <div
            id="procedureAnalyticsChart"
            :data-color="JSON.stringify('c-chart__color-3-1')"
            :data-items="JSON.stringify(analyticsData)"
            :data-legend="JSON.stringify([{ key: Translator.trans('analytics.pageViews.all'), value: totalViews }])"
            :data-texts="JSON.stringify({
              'legend-headline': Translator.trans('analytics.visitorMetrics'),
              'no-data-fallback' : Translator.trans('analytics.visitorMetrics.none'),
              'data-names' : Translator.trans('analytics.visitorMetrics'),
              'data-name' : Translator.trans('analytics.metric')
            })" />
          <div id="procedureAnalyticsChartLegend" />
        </template>
        <p v-else>
          {{ Translator.trans('analytics.visitorMetrics.pageViews.none') }}
        </p>
      </div>
      <p v-else>
        {{ Translator.trans('analytics.visitorMetrics.none') }}
      </p>
    </div>
  </dp-card>
</template>

<script>
import { DpCard, DpLoading, dpRpc } from '@demos-europe/demosplan-ui'
import { initLineChart } from '@DpJs/lib/procedure/charts/helpers/init'

export default {
  name: 'ProcedureAnalyticsChart',

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
      statistics: {}
    }
  },

  computed: {
    analyticsData () {
      return Object.values(this.statistics).length
        ? Object.entries(this.statistics).map(([date, stats]) => {
          const index = Object.keys(this.statistics).findIndex(stat => stat === date)
          return {
            index,
            x: date,
            y: stats.views
          }
        })
        : []
    },

    totalViews () {
      return Object.values(this.statistics)
        .reduce((acc, curr) => {
          return acc + curr.views
        }, 0)
    }
  },

  methods: {
    fetchProcedureAnalyticsData () {
      dpRpc('procedure.analytics.retrieve', { procedureId: this.procedureId })
        .then(response => {
          this.statistics = response.data[0]?.result || {}
          this.isLoading = false
          this.$nextTick(() => initLineChart('#procedureAnalyticsChart', '#procedureAnalyticsChartLegend'))
        })
        .catch(err => {
          console.error(err)
          this.isLoading = false
        })
    }
  },

  mounted () {
    this.fetchProcedureAnalyticsData()
  }
}
</script>
