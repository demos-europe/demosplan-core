/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_original_statements.html.twig
 */

import { initialize } from '@DpJs/InitVue'
import ListOriginalStatements from '@DpJs/components/statement/listOriginalStatements/ListOriginalStatements'

const components = {
  ListOriginalStatements
}
const apiStores = ['OriginalStatement']

initialize(components, {}, apiStores)
