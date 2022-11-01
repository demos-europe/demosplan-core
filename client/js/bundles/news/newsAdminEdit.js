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
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DpJs/InitVue'
import newsAdminInit from '@DpJs/lib/news/newsAdmin'

const components = {
  DpChangeStateAtDate,
  DpEditor,
  DpUploadFiles
}

initialize(components).then(() => {
  dpValidate()
  newsAdminInit()
})
