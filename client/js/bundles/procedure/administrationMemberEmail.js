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
import DpAccordion from '@DpJs/components/core/DpAccordion'
import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import DpInlineNotification from '@DpJs/components/core/DpInlineNotification'
import { DpLabel } from 'demosplan-ui/components'
import dpValidate from '@DpJs/lib/core/validation/dpValidate'
import { initialize } from '@DpJs/InitVue'

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
