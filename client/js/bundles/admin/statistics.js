/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for statistics.html.twig
 */

import { DpDataTableExtended } from 'demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import StatisticsCharts from '@DpJs/components/admin/StatisticsCharts'

initialize({ DpDataTableExtended }).then(() => {
  // eslint-disable-next-line no-new
  new StatisticsCharts()
})
