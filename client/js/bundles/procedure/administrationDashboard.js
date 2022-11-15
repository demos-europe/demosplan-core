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

import { DpContextualHelp, DpDashboardTaskCard } from 'demosplan-ui/components/core'
import DpStatementSegmentsStatusCharts from '@DpJs/components/procedure/charts/DpStatementSegmentsStatusCharts'
import DpSurveyChart from '@DpJs/components/procedure/survey/DpSurveyChart'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import ProcedureAnalyticsChart from '@DpJs/components/procedure/charts/ProcedureAnalyticsChart'
import ProcedureCharts from '@DpJs/components/procedure/charts/ProcedureCharts'

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
