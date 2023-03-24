/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_edit_master.html.twig
 */

import AdministrationMaster from '@DpJs/lib/procedure/AdministrationMaster'
import DpMasterBasicSettings from '@DpJs/components/procedure/basicSettings/DpMasterBasicSettings'
import { dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const components = { DpMasterBasicSettings }

initialize(components).then(() => {
  AdministrationMaster()
  dpValidate()
})
