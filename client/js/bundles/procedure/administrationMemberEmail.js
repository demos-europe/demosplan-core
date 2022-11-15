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
import { DpAccordion } from '@demos-europe/demosplan-ui/components/core'
import { DpEditor } from '@demos-europe/demosplan-ui/components/core'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import { DpInlineNotification } from '@demos-europe/demosplan-ui/components/core'
import { DpLabel } from '@demos-europe/demosplan-ui/components'
import { dpValidate } from '@demos-europe/demosplan-utils/lib/validation'
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
