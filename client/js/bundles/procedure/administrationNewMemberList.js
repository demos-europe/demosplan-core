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
import DpAddOrganisationList from '@DpJs/components/procedure/admin/DpAddOrganisationList'
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

initialize(components, stores, apiStores)
