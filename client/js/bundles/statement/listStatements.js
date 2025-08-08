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
import ListOriginalStatements from '@DpJs/components/statement/listOriginalStatements/ListOriginalStatements'
import ListStatements from '@DpJs/components/statement/listStatements/ListStatements'
import CvStatementList from '@DpJs/components/statement/CvStatementList.vue'

const components = {
  ListStatements,
  ListOriginalStatements,
  CvStatementList
}
const apiStores = ['AssignableUser', 'Statement', 'OriginalStatement']

initialize(components, {}, apiStores)
