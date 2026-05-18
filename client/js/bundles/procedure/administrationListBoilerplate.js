/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_list_boilerplate.html.twig
 */

import { DpButton, DpFlyout } from '@demos-europe/demosplan-ui'
import AnimateById from '@DpJs/lib/shared/AnimateById'
import { initialize } from '@DpJs/InitVue'

const components = { DpFlyout, DpButton }

initialize(components).then(() => {
  AnimateById()

  document.addEventListener('DOMContentLoaded', () => {
    const deleteButton = document.querySelector('[data-cy="deleteSelectedBoilerplate"]')
    const checkboxes = Array.from(document.querySelectorAll('input[data-checkable-item]'))

    deleteButton.addEventListener('click', (event) => {
      if (!checkboxes.some(checkbox => checkbox.checked)) {
        event.preventDefault()
        dplan.notify.error(Translator.trans('warning.select.one.entry'))
      } else if (checkboxes.some(checkbox => checkbox.checked) &&
        confirm(Translator.trans('check.entries.marked.delete'))
      ) {
        deleteButton.closest('form').submit()
      }
    })
  })
})
