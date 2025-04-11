/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_custom_fields_list.html.twig
 */
import AdministrationCustomFieldsList from '@DpJs/components/procedure/admin/AdministrationCustomFieldsList'
import { initialize } from '@DpJs/InitVue'
const apiStores = ['AdminProcedure', 'CustomField']

const components = {
  AdministrationCustomFieldsList
}

initialize(components, {}, apiStores)
