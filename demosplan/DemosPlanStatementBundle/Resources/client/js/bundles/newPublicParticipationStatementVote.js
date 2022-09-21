/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for new_public_participation_statement_vote.html.twig
 */

import DpMapModal from '@DemosPlanStatementBundle/components/assessmentTable/DpMapModal'
import DpModal from '@DemosPlanCoreBundle/components/DpModal'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpMapModal,
  DpModal
}

initialize(components)
