/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for news_admin_list.html.twig
 */

import DpNewsAdminList from '@DpJs/components/news/DpNewsAdminList'
import { DpTooltipIcon } from '@demos-europe/demosplan-ui'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpNewsAdminList,
  DpTooltipIcon
}

initialize(components)
