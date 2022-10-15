/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for news_admin_edit.html.twig
 */

import DpChangeStateAtDate from '@DpJs/components/core/DpChangeStateAtDate'
import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import newsAdminInit from '@DemosPlanNewsBundle/lib/newsAdmin'

const components = {
  DpChangeStateAtDate,
  DpEditor,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
  newsAdminInit()
})
