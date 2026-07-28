/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for elements_admin_import.html.twig
 */
import { DpCheckbox } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const components = { DpCheckbox }
initialize(components, {}).then(() => {
  /*
   * Submitting only enqueues the import now, so this page redirects to the element list
   * straight away and progress is reported there. All that is left to do here is stop the
   * user from submitting the same import twice while the redirect is in flight.
   */
  $('form').submit(function () {
    $(this).find('input[type="submit"]').prop('disabled', true)
  })
})
