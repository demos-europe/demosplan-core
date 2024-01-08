/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_member_list.html.twig
 */
import { addFormHiddenField, removeFormHiddenField } from '../../lib/core/libs/FormActions'
import { DpContextualHelp } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpContextualHelp
}

initialize(components).then(() => {
  const pdfExport = () => {
    const action = document.procedureForm.action
    const target = document.procedureForm.target

    document.procedureForm.target = '_blank'
    document.procedureForm.action = Routing.generate('DemosPlan_procedure_member_index_pdf', { procedure: dplan.procedureId })
    document.procedureForm.submit()
    document.procedureForm.action = action
    document.procedureForm.target = target
  }

  const pdfExportButton = document.getElementById('pdfExportButton')
  if (pdfExportButton) {
    pdfExportButton.addEventListener('click', pdfExport)
  }

  const writeEmail = (e) => {
    e.preventDefault()
    const oldAction = document.procedureForm.action
    document.procedureForm.action = Routing.generate('DemosPlan_admin_member_email', { procedureId: dplan.procedureId })

    // Add hidden field to tell BE it is a 'writeEmail' action
    addFormHiddenField(document.procedureForm, 'email_orga_action')

    // After submit reset form action
    document.procedureForm.submit()
    document.procedureForm.action = oldAction

    // Remove hidden field
    removeFormHiddenField(document.procedureForm)
  }

  const writeEmailButton = document.getElementById('writeEmailButton')
  if (writeEmailButton) {
    writeEmailButton.addEventListener('click', writeEmail)
  }
})
