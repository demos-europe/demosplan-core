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

import { DpContextualHelp, DpEditor, DpMultiselect, DpUploadFiles, dpValidate } from '@demos-europe/demosplan-ui'
import AdministrationMaster from '@DpJs/lib/procedure/AdministrationMaster'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import { initialize } from '@DpJs/InitVue'
import ProcedureTemplateBasicSettings from '@DpJs/components/procedure/basicSettings/ProcedureTemplateBasicSettings'

const components = {
  DpContextualHelp,
  DpEditor,
  DpEmailList,
  DpMultiselect,
  DpUploadFiles,
  ProcedureTemplateBasicSettings,
}

initialize(components).then(() => {
  AdministrationMaster()
  dpValidate()
})
