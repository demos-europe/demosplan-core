/**
 * (c) 2010-present DEMOS plan GmbH.
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
import { initialize } from '@DpJs/InitVue'

const components = {
  DpNewsAdminList
}

initialize(components)
