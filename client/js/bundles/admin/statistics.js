/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for statistics.html.twig
 */

import { DpDataTableExtended } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import StatisticsCharts from '@DpJs/components/admin/StatisticsCharts'

initialize({ DpDataTableExtended }).then(() => {
  new StatisticsCharts()
})
