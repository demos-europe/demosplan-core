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

import { initialize } from '@DemosPlanCoreBundle/InitVue'
import SplitStatementStore from '@DpJs/store/procedure/SplitStatementStore'
import SplitStatementView from '@DemosPlanProcedureBundle/components/splitStatement/SplitStatementView'

const components = { SplitStatementView }
const stores = {
  splitstatement: SplitStatementStore
}

initialize(components, stores)
