/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_unregistered_publicagency_email.html.twig
 */
import { DpAccordion, DpEditor } from '@demos-europe/demosplan-ui'
import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpAccordion, DpEditor }

initialize(components).then(() => {
  dpValidate()
})
