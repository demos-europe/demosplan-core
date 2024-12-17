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
import { initialize } from '@DpJs/InitVue'

const components = {
  DpAddOrganisationList
}

const apiStores = ['InvitableToeb']

initialize(components, {}, apiStores)
