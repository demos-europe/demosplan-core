/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_new_master.html.twig
 */

import AdministrationMaster from '@DpJs/lib/procedure/AdministrationMaster'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import { dpValidate } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import NewBlueprintForm from '@DpJs/components/procedure/admin/NewBlueprintForm'

const components = { DpEmailList, NewBlueprintForm }

initialize(components).then(() => {
  AdministrationMaster()

  // To Disable Save Button after Form Validate
  dpValidate()
  const saveButton = document.getElementById('saveButton')
  document.addEventListener('customValidationPassed', function (e) {
    saveButton.disabled = true
  })
})
