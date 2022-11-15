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

import { DpChangeStateAtDate, DpEditor, DpUploadFiles } from '@demos-europe/demosplan-ui/components/core'
import { dpValidate } from '@demos-europe/demosplan-utils/lib/validation'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
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
