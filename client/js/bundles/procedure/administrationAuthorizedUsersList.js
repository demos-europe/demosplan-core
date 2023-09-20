/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_authorized_users_list.html.twig
 */

import AuthorizedUsersList from '@DpJs/components/procedure/admin/AuthorizedUsersList'
import { initialize } from '@DpJs/InitVue'

const components = { AuthorizedUsersList }
const stores = {}
const apiStores = []

initialize(components, stores, apiStores)
