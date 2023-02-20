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
import { DpAccordion, DpEditor, DpInlineNotification, DpLabel } from '@demos-europe/demosplan-ui'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'
import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpAccordion,
  DpEditor,
  DpEmailList,
  DpInlineNotification,
  DpLabel
}

const stores = {
  boilerplates: BoilerplatesStore
}

initialize(components, stores).then(() => {
  dpValidate()
})
