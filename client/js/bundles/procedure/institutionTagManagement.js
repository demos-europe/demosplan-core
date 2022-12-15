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
import InstitutionTagManagement from '@DpJs/components/procedure/admin/InstitutionTagManagement'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { InstitutionTagManagement }

const apiStores = [
  'institutionTag',
  'invitableInstitution'
]

initialize(components, {}, apiStores)
