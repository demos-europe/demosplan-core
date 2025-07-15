/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for customer_settings.html.twig
 */

import CustomerSettings from '@DpJs/components/user/CustomerSettings/CustomerSettings'
import { initialize } from '@DpJs/InitVue'

const components = {
  CustomerSettings
}

const apiStores = [
  'Branding',
  'Customer',
  'CustomerContact',
  'CustomerLoginSupportContact',
  'File'
]

initialize(components, {}, apiStores)
