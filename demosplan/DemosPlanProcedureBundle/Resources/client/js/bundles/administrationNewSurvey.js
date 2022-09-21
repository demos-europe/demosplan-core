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

import DpSurveyStatus from '../components/DpSurveyStatus'
import DpTiptap from '@DemosPlanCoreBundle/components/DpTiptap'
import dpValidate from '@DpJs/lib/validation/dpValidate'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = {
  DpSurveyStatus,
  DpTiptap
}

initialize(components).then(() => {
  dpValidate()
})
