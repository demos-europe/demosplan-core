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

import { DpEditor, dpValidate } from '@demos-europe/demosplan-ui'
import DpSurveyStatus from '@DpJs/components/procedure/DpSurveyStatus'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpEditor,
  DpSurveyStatus
}

initialize(components).then(() => {
  dpValidate()
})
