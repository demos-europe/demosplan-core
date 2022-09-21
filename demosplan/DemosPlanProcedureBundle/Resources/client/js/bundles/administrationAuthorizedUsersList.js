/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for administration_authorized_users_list.html.twig
 */

import AuthorizedUsersList from '@DemosPlanProcedureBundle/components/admin/AuthorizedUsersList'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { AuthorizedUsersList }
const stores = {}
const apiStores = []

initialize(components, stores, apiStores)
