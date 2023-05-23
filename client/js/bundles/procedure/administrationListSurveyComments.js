/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_list_survey_comments.html.twig
 */
import DpSurveyCommentsList from '@DpJs/components/procedure/survey/DpSurveyCommentsList'
import { initialize } from '@DpJs/InitVue'

const components = { DpSurveyCommentsList }

initialize(components)
