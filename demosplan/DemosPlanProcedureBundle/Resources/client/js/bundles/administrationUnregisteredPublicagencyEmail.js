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
import DpAccordion from '@DpJs/components/core/DpAccordion'
import DpTiptap from '@DpJs/components/core/DpTiptap'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpAccordion, DpTiptap }

initialize(components).then(() => {
  dpValidate()
})
