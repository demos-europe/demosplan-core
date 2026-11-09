/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for administration_dashboard.html.twig
 */

import AddonWrapper from '@DpJs/components/addon/AddonWrapper.vue'
import { DpContextualHelp } from '@demos-europe/demosplan-ui'
import DpDashboardTaskCard from '@DpJs/components/procedure/DpDashboardTaskCard.vue'
import DpStatementSegmentsStatusCharts from '@DpJs/components/procedure/charts/DpStatementSegmentsStatusCharts.vue'
import { initialize } from '@DpJs/InitVue'
import ProcedureAnalyticsChart from '@DpJs/components/procedure/charts/ProcedureAnalyticsChart.vue'
import ProcedureCharts from '@DpJs/components/procedure/charts/ProcedureCharts'

const components = {
  AddonWrapper,
  DpContextualHelp,
  DpDashboardTaskCard,
  DpStatementSegmentsStatusCharts,
  ProcedureAnalyticsChart,
}

initialize(components).then(() => {
  // If permission is enabled, ProcedureCharts are initialized in DpStatementSegmentsStatusCharts
  if (hasPermission('area_statement_segmentation') === false) {
    return new ProcedureCharts()
  }
})
