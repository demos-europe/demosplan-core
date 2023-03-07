/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_split_statement.html.twig
 */

import AddonWrapper from "@DpJs/components/addon/AddonWrapper"
import { initialize } from '@DpJs/InitVue'
import SplitStatementStore from '@DpJs/store/procedure/SplitStatementStore'
import SplitStatementView from '@DpJs/components/procedure/splitStatement/SplitStatementView'

const components = {
  AddonWrapper,
  SplitStatementView
}
const stores = {
  splitstatement: SplitStatementStore
}

initialize(components, stores)
