/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for edit_tag.html.twig
 */

import { DpEditor, dpValidate, prefixClass } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const components = { DpEditor }

initialize(components).then(() => {
  dpValidate()
  const radios = Array.from(document.getElementsByName('r_attachmode'))
  const newBoilerplateForm = document.getElementById('newBoilerplateForm')
  radios.forEach(radio => {
    radio.addEventListener('change', (e) => {
      if (radio.value === 'new') {
        newBoilerplateForm.classList.remove(prefixClass('hidden'))
      } else {
        newBoilerplateForm.classList.add(prefixClass('hidden'))
      }
    })
  })
})
