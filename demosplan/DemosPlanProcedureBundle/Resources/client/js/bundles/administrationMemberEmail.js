/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_member_email.html.twig
 */
import DpAccordion from '@DemosPlanCoreBundle/components/DpAccordion'
import DpEmailList from '@DemosPlanProcedureBundle/components/basicSettings/DpEmailList'
import DpInlineNotification from '@DemosPlanCoreBundle/components/DpInlineNotification'
import { DpLabel } from 'demosplan-ui/components'
import DpTiptap from '@DemosPlanCoreBundle/components/DpTiptap'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpAccordion,
  DpEmailList,
  DpInlineNotification,
  DpLabel,
  DpTiptap
}

initialize(components).then(() => {
  dpValidate()
})
