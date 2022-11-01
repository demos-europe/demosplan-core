/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for elements_admin_import.html.twig
 */
import { initialize } from '@DemosPlanCoreBundle/InitVue'

initialize({}).then(() => {
  $('form').submit(function (e) {
    setInterval(function () { getImportStatus() }, 3000)
    $(this).find('input[type="submit"]').prev('p').removeClass('hide-visually')
    $(this).find('input[type="submit"]').next('a').addClass('hide-visually')
    $(this).find('input[type="submit"]').addClass('hide-visually')
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
