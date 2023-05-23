/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for faq_admin_edit.html.twig
 */

import { DpEditor, dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpEditor
}

initialize(components).then(() => {
  dpValidate()
})
