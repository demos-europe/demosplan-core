/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_user.html.twig
 */

import DpCreateItem from '@DpJs/components/user/DpCreateItem'
import DpUserList from '@DpJs/components/user/DpUserList/DpUserList'
import DpUserListExtended from '@DpJs/components/user/DpUserListExtended'
import { initialize } from '@DpJs/InitVue'
import UserFormFields from '@DpJs/store/user/UserFormFields'

const stores = {
  UserFormFields
}
const components = {
  DpCreateItem,
  DpUserList,
  DpUserListExtended
}
const apiStores = [
  'AdministratableUser',
  'Department',
  'Orga',
  'Role'
]

initialize(components, stores, apiStores)
