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

import { initialize } from '@DpJs/InitVue'
import ListStatements from '@DpJs/components/statement/listStatements/ListStatements'

const components = {
  ListStatements
}
const apiStores = ['AssignableUser', 'Statement']

initialize(components, {}, apiStores)
