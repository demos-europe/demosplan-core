/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for edit_tag.html.twig
 */

import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import { prefixClass } from 'demosplan-ui/lib'

const components = { DpEditor }

initialize(components).then(() => {
  dpValidate()
  const radios = Array.from(document.getElementsByName('r_attachmode'))
  const newBoilerplateForm = document.getElementById('newBoilerplateForm')
  radios.forEach(radio => {
    radio.addEventListener('change', (e) => {
      if (radio.value === 'new') {
        newBoilerplateForm.classList.remove(prefixClass('display--none'))
      } else {
        newBoilerplateForm.classList.add(prefixClass('display--none'))
      }
    })
  })
})
