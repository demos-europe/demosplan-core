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

    deleteButton.addEventListener('click', () => {
      if (checkboxes.some(checkbox => checkbox.checked) &&
        confirm("{{ 'check.entries.marked.delete'|trans }}")) {
        deleteButton.closest('form').submit()
      }
    })
  })
})
