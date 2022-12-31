/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_new_survey.html.twig
 */

import { DpEditor } from '@demos-europe/demosplan-ui'
import DpSurveyStatus from '@DpJs/components/procedure/DpSurveyStatus'
import dpValidate from '@demos-europe/demosplan-utils/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpEditor,
  DpSurveyStatus
}

initialize(components).then(() => {
  dpValidate()
})
