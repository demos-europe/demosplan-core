/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for gateway_newUser.html.twig
 */
import { dpValidate } from '@demos-europe/demosplan-ui/src'
import { initialize } from '@DpJs/InitVue'

const components = {}

initialize(components).then(() => {
  dpValidate()
})
