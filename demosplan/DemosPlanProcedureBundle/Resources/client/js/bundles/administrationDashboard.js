/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for administration_dashboard.html.twig
 */

import DpContextualHelp from '@DemosPlanCoreBundle/components/DpContextualHelp'
import DpDashboardTaskCard from '@DpJs/components/core/DpDashboardTaskCard'
import DpStatementSegmentsStatusCharts from '@DemosPlanProcedureBundle/components/charts/DpStatementSegmentsStatusCharts'
import DpSurveyChart from '@DemosPlanProcedureBundle/components/survey/DpSurveyChart'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import ProcedureAnalyticsChart from '@DemosPlanProcedureBundle/components/charts/ProcedureAnalyticsChart'
import ProcedureCharts from '@DemosPlanProcedureBundle/components/charts/ProcedureCharts'

const components = {
  DpContextualHelp,
  DpDashboardTaskCard,
  DpStatementSegmentsStatusCharts,
  DpSurveyChart,
  ProcedureAnalyticsChart
}

initialize(components).then(() => {
  // If permission is enabled, ProcedureCharts are initialized in DpStatementSegmentsStatusCharts
  if (hasPermission('area_statement_segmentation') === false) {
    return new ProcedureCharts()
  }
})
