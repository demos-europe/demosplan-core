/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for procedure_proposal.html.twig
 */
import { DpEditor, dpValidate } from '@demos-europe/demosplan-ui/src'
import DpProcedureCoordinate from '@DpJs/components/procedure/basicSettings/DpProcedureCoordinate'
import { initialize } from '@DpJs/InitVue'

initialize({
  DpEditor,
  DpProcedureCoordinate
}).then(() => {
  dpValidate()
})
