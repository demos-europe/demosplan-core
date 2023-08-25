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

import DpAddonWrapper from "@demos-europe/demosplan-ui"
import { initialize } from '@DpJs/InitVue'
import SplitStatementStore from '@DpJs/store/procedure/SplitStatementStore'

const components = {
  DpAddonWrapper
}
const stores = {
  splitstatement: SplitStatementStore
}

initialize(components, stores)
