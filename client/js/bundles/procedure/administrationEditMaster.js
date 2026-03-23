/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_edit_master.html.twig
 */

import {
  DpCheckbox,
  DpContextualHelp,
  DpEditor,
  DpInput,
  DpMultiselect,
  DpTextArea,
  DpUploadFiles,
  dpValidate,
} from '@demos-europe/demosplan-ui'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import { initialize } from '@DpJs/InitVue'
import ProcedureTemplateBasicSettings from '@DpJs/components/procedure/basicSettings/ProcedureTemplateBasicSettings'

const components = {
  DpCheckbox,
  DpContextualHelp,
  DpEditor,
  DpEmailList,
  DpInput,
  DpMultiselect,
  DpTextArea,
  DpUploadFiles,
  ProcedureTemplateBasicSettings,
}

initialize(components).then(() => {
  dpValidate()
})
