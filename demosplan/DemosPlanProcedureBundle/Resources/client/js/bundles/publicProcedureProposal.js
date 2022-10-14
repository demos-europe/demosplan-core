/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for procedure_proposal.html.twig
 */
import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import DpProcedureCoordinate from '@DemosPlanProcedureBundle/components/basicSettings/DpProcedureCoordinate'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

initialize({
  DpEditor,
  DpProcedureCoordinate
}).then(() => {
  dpValidate()
})
