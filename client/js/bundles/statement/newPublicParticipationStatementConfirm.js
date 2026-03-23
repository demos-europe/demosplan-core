/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for new_public_participation_statement_confirm.html
 */

import { DpDetails, DpModal } from '@demos-europe/demosplan-ui'
import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpDetails,
  DpMapModal,
  DpModal,
}

initialize(components)
