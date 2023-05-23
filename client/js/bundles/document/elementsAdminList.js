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

import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import DpMapSettingsPreview from '@DpJs/components/document/DpMapSettingsPreview'
import ElementsAdminList from '@DpJs/components/document/ElementsAdminList'
import { initialize } from '@DpJs/InitVue'

const components = {
  AddonWrapper,
  ElementsAdminList,
  DpMapSettingsPreview
}

const apiStores = ['elements']

initialize(components, {}, apiStores)
