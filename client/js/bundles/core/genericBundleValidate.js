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
 * This is a generic entrypoint used in all templates were no additional entrypoint specific javascript is needed.
 * Use this whenever you need form validation. Use genericBundle.js when no form validation is needed.
 *
 * Never put any additional javascript in here that is not needed in all places where genericBundleValidate is used. If you
 * need additional javascript, the entrypoint should have it's own bundle.
 */

import { dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

initialize().then(() => {
  dpValidate()
})
