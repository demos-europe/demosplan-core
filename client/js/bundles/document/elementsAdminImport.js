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
  $('form').submit(function (e) {
    setInterval(function () { getImportStatus() }, 3000)
    $(this).find('input[type="submit"]').prev('p').removeClass('sr-only')
    $(this).find('input[type="submit"]').next('a').addClass('sr-only')
    $(this).find('input[type="submit"]').addClass('sr-only')
  })

  /**
   * Read fileuploadstatus from file
   */
  function getImportStatus () {
    $.get('{{ templateVars.basePath|default(' / ') }}uploads/files/importStatus_{{ templateVars.statusHash|default(0) }}.json', function (response) {
      const data = JSON.parse(response)
      $('#js_uploadProgressProcessed').text(data.bulkImportFilesProcessed)
      $('#js_uploadProgressTotal').text(data.bulkImportFilesTotal)
    }, 'text')
  }
})
