

/**
 * This is the entrypoint for statistics.html.twig
 */

import { DpDataTableExtended } from '@demos-europe/demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import StatisticsCharts from '@DpJs/components/admin/StatisticsCharts'

initialize({ DpDataTableExtended }).then(() => {
  // eslint-disable-next-line no-new
  new StatisticsCharts()
})
