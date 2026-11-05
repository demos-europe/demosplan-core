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
import FilterFlyoutStore from '@DpJs/store/procedure/FilterFlyout'
import { initialize } from '@DpJs/InitVue'
import InstitutionTagManagement from '@DpJs/components/procedure/admin/InstitutionTagManagement/InstitutionTagManagement'

const components = { InstitutionTagManagement }

const stores = {
  FilterFlyout: FilterFlyoutStore,
}

const apiStores = [
  'InstitutionTag',
  'InstitutionTagCategory',
  'InvitableInstitution',
]

/**
 * Use API Platform 3.0 for reading InvitableInstitution on this page, updates stay on 2.0
 */
const pageSpecificApi3Modules = {
  InvitableInstitution: [
    'list',
    'get',
  ],
}

initialize(components, stores, apiStores, {}, () => {}, pageSpecificApi3Modules)
