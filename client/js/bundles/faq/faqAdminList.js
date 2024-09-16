/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for faq_admin_list.html.twig
 */

import DpFaqList from '@DpJs/components/faq/DpFaqList'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpFaqList
}

const stores = {}

const apiStores = ['Faq', 'FaqCategory']

initialize(components, stores, apiStores)
