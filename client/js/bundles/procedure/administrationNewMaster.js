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

import AdministrationMaster from '@DemosPlanProcedureBundle/lib/AdministrationMaster'
import DpEmailList from '@DemosPlanProcedureBundle/components/basicSettings/DpEmailList'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import NewBlueprintForm from '@DemosPlanProcedureBundle/components/admin/NewBlueprintForm'

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
