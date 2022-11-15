/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for gateway_newUser.html.twig
 */
import { dpValidate } from 'demosplan-utils/lib/validation'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {}

initialize(components).then(() => {
  dpValidate()
})
