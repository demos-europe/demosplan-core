/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for document_admin_new.html.twig
 */

import { DpInput } from 'demosplan-ui/components'
import DpRadio from '@DpJs/components/core/form/DpRadio'
import DpTextArea from '@DpJs/components/core/form/DpTextArea'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import dpValidate from 'demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpInput,
  DpRadio,
  DpTextArea,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
})
