

/**
 * This is the entrypoint for statistics.html.twig
 */

import DpDataTableExtended from '@DpJs/components/core/DpDataTable/DpDataTableExtended'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import StatisticsCharts from '../components/StatisticsCharts'

initialize({ DpDataTableExtended }).then(() => {
  // eslint-disable-next-line no-new
  new StatisticsCharts()
})
