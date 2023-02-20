/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
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

initialize(components)
