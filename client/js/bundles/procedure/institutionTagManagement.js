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
import InstitutionTagManagement from '@DpJs/components/procedure/admin/InstitutionTagManagement/InstitutionTagManagement'
import FilterFlyoutStore from '@DpJs/store/procedure/FilterFlyout'
import { initialize } from '@DpJs/InitVue'

const components = { InstitutionTagManagement }

const stores = {
  FilterFlyout: FilterFlyoutStore,
}

const apiStores = [
  'InstitutionTag',
  'InstitutionTagCategory',
  'InvitableInstitution'
]

initialize(components, stores, apiStores)
