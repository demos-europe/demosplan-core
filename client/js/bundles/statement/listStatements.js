/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_statements.html.twig
 */

import FilterFlyoutStore from '@DpJs/store/procedure/FilterFlyout'
import { initialize } from '@DpJs/InitVue'
import ListOriginalStatements from '@DpJs/components/statement/listOriginalStatements/ListOriginalStatements'
import ListStatements from '@DpJs/components/statement/listStatements/ListStatements'

const components = {
  ListStatements,
  ListOriginalStatements,
}
const apiStores = ['AssignableUser', 'Statement', 'OriginalStatement']
const stores = {
  FilterFlyout: FilterFlyoutStore,
}

initialize(components, stores, apiStores)
