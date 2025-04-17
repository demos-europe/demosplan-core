/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for customer_settings_update_mail.html.twig
 */
import { DpEditor, DpInput, DpLabel, dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpLabel,
  DpInput,
  DpEditor
}

const stores = {}

initialize(components, stores).then(() => {
  dpValidate()
})
