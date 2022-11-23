/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for news_admin_new.html.twig
 */

import { DpChangeStateAtDate, DpEditor, DpUploadFiles } from '@demos-europe/demosplan-ui/components/core'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import { dpValidate } from '@demos-europe/demosplan-utils/lib/validation'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import newsAdminInit from '@DpJs/lib/news/newsAdmin'

const components = {
  DpChangeStateAtDate,
  DpEditor,
  DpUploadFiles
}

const stores = {
  boilerplates: BoilerplatesStore
}

initialize(components, stores).then(() => {
  dpValidate()
  newsAdminInit()
})
