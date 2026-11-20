/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_new_member_list.html.twig
 */
import DpAddOrganisationList from '@DpJs/components/procedure/admin/DpAddOrganisationList.vue'
import FilterFlyoutStore from '@DpJs/store/procedure/FilterFlyout'
import { initialize } from '@DpJs/InitVue'

const components = {
  DpAddOrganisationList,
}

const apiStores = [
  'InvitableToeb',
  'InstitutionTag',
  'InstitutionTagCategory',
  'InstitutionLocationContact',
]

const stores = {
  FilterFlyout: FilterFlyoutStore,
}

/**
 * Use API Platform 3.0 for reading the data of this page. Adding institutions to the procedure stays on its
 * current endpoint.
 */
const pageSpecificApi3Modules = {
  InvitableToeb: ['list'],
  InstitutionTag: ['list'],
  InstitutionTagCategory: ['list'],
}

initialize(components, stores, apiStores, {}, () => {}, pageSpecificApi3Modules)
