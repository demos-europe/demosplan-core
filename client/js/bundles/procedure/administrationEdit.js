/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for administration_edit.html.twig
 */

import {
  DpDateRangePicker,
  DpDatetimePicker,
  DpInput,
  DpTextArea,
  dpValidate,
} from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import { defineAsyncComponent } from 'vue'
import DpBasicSettings from '@DpJs/components/procedure/basicSettings/DpBasicSettings'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import DPWizard from '@DpJs/lib/procedure/DPWizard'
import { initialize } from '@DpJs/InitVue'
import UrlPreview from '@DpJs/lib/shared/UrlPreview'

const AutoSwitchProcedurePhaseForm = defineAsyncComponent(() =>
  import('@DpJs/components/procedure/basicSettings/AutoSwitchProcedurePhaseForm'),
)

const DpCheckbox = defineAsyncComponent(async () => {
  const { DpCheckbox } = await import('@demos-europe/demosplan-ui')
  return DpCheckbox
})

const DpEditor = defineAsyncComponent(async () => {
  const { DpEditor } = await import('@demos-europe/demosplan-ui')
  return DpEditor
})

const DpMultiselect = defineAsyncComponent(async () => {
  const { DpMultiselect } = await import('@demos-europe/demosplan-ui')
  return DpMultiselect
})

const ExportSettings = defineAsyncComponent(() =>
  import('@DpJs/components/procedure/basicSettings/ExportSettings'),
)

const DpInlineNotification = defineAsyncComponent(async () => {
  const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')
  return DpInlineNotification
})

const DpProcedureCoordinate = defineAsyncComponent(() =>
  import('@DpJs/components/procedure/basicSettings/DpProcedureCoordinate'),
)

const DpUploadFiles = defineAsyncComponent(async () => {
  const { DpUploadFiles } = await import('@demos-europe/demosplan-ui')
  return DpUploadFiles
})

const ParticipationPhases = defineAsyncComponent(() =>
  import('@DpJs/components/procedure/basicSettings/ParticipationPhases'),
)

const components = {
  AddonWrapper,
  AutoSwitchProcedurePhaseForm,
  DpBasicSettings,
  DpCheckbox,
  DpDateRangePicker,
  DpDatetimePicker,
  DpEditor,
  DpEmailList,
  DpInlineNotification,
  DpInput,
  DpMultiselect,
  DpProcedureCoordinate,
  DpTextArea,
  DpUploadFiles,
  ExportSettings,
  ParticipationPhases,
}

initialize(components).then(() => {
  UrlPreview()
  DPWizard()
  dpValidate()

  document.addEventListener('customValidationPassed', (event) => {
    const form = event.detail.form

    if (form.dataset.dpValidate === 'configForm') {
      const submitButton = form.querySelector('[type="submit"]')

      if (submitButton) {
        submitButton.setAttribute('disabled', 'disabled')
      }
    }
  })
})
