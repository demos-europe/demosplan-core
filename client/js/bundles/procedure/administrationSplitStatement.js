/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_split_statement.html.twig
 */

import { initialize } from '@DpJs/InitVue'
import SplitStatementStore from '@DpJs/store/statement/SplitStatementStore'
import SplitStatementView from '@DpJs/components/statement/splitStatement/SplitStatementView'

const components = {
  SplitStatementView
}
const stores = {
  SplitStatement: SplitStatementStore
}

initialize(components, stores)
