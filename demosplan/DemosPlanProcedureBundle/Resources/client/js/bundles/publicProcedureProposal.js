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
import DpProcedureCoordinate from '@DemosPlanProcedureBundle/components/basicSettings/DpProcedureCoordinate'
import DpTiptap from '@DemosPlanCoreBundle/components/DpTiptap'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

initialize({
  DpProcedureCoordinate,
  DpTiptap
}).then(() => {
  dpValidate()
})
