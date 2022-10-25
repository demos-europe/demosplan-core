/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for institution_tag_management.html.twig
 */
import InstitutionTagList from '@DemosPlanProcedureBundle/components/admin/InstitutionTagList'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { InstitutionTagList }

const apiStores = [
  'institutionTag'
]

initialize(components, {}, apiStores)
