/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for institution_tag_management.html.twig
 */
import { initialize } from '@DpJs/InitVue'
import InstitutionTagManagement from '@DpJs/components/procedure/admin/InstitutionTagManagement'

const components = { InstitutionTagManagement }

const apiStores = [
  'InstitutionTag',
  'InstitutionTagCategory',
  'InvitableInstitution'
]

initialize(components, {}, apiStores)
