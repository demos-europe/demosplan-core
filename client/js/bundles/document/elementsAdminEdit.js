/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for elements_admin_edit.html.twig
 */

import {
  DpDataTable,
  DpDatetimePicker,
  DpEditor,
  DpMultiselect,
  DpUploadFiles,
  dpValidate,
} from '@demos-europe/demosplan-ui'
import ElementAdminEdit from '@DpJs/components/document/ElementAdminEdit'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpDataTable,
  DpDatetimePicker,
  DpEditor,
  DpMultiselect,
  DpUploadFiles,
  ElementAdminEdit,
}

initialize(components).then(() => {
  dpValidate()
})
