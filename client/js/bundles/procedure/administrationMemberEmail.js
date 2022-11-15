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
import { DpAccordion } from 'demosplan-ui/components/core'
import { DpEditor } from 'demosplan-ui/components/core'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import { DpInlineNotification } from 'demosplan-ui/components/core'
import { DpLabel } from 'demosplan-ui/components'
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpAccordion,
  DpEditor,
  DpEmailList,
  DpInlineNotification,
  DpLabel
}

initialize(components).then(() => {
  dpValidate()
})
