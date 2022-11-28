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
import { DpEditor } from '@demos-europe/demosplan-ui'
import DpProcedureCoordinate from '@DpJs/components/procedure/basicSettings/DpProcedureCoordinate'
import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

initialize({
  DpEditor,
  DpProcedureCoordinate
}).then(() => {
  dpValidate()
})
