/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for elements_admin_list.html.twig
 */

import DpMapSettingsPreview from '@DpJs/components/document/DpMapSettingsPreview'
import { DpUploadFiles } from 'demosplan-ui/components/core'
import { dpValidate } from 'demosplan-utils/lib/validation'
import ElementsAdminList from '@DpJs/components/document/ElementsAdminList'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { ElementsAdminList, DpMapSettingsPreview, DpUploadFiles }

const apiStores = ['elements']

initialize(components, {}, apiStores).then(() => {
  dpValidate()
})
