/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { initBarChart } from '@DpJs/lib/procedure/charts/helpers/init'

export default class StatisticsCharts {
  constructor () {
    return {
      procedurePhasesPublicAgenciesChart: initBarChart('#procedurePhasesPublicAgencies', '#procedurePhasesPublicAgenciesLegend'),
      procedurePhasesPublicChart: initBarChart('#procedurePhasesPublic', '#procedurePhasesPublicLegend'),
      StatementsAmountChart: initBarChart('#statementsAmountChart', '#statementsAmountChartLegend'),
      StatementsAverageChart: initBarChart('#statementsAverageChart', '#statementsAverageChartLegend')
    }
  }
}
