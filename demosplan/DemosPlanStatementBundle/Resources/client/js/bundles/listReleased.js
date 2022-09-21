/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_released.html.twig
 */

import DpMapModal from '@DemosPlanStatementBundle/components/assessmentTable/DpMapModal'
import DpModal from '@DemosPlanCoreBundle/components/DpModal'
import DpPublicStatementList from '@DemosPlanStatementBundle/components/publicStatementLists/DpPublicStatementList'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import publicStatement from '@DemosPlanStatementBundle/store/PublicStatement'

const components = {
  DpMapModal,
  DpModal,
  DpPublicStatementList
}

const stores = {
  publicStatement
}

initialize(components, stores)
