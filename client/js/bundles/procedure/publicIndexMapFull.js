/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for public_index.html.twig
 * where the map is more or less across the whole site
 */
import { initialize } from '@DpJs/InitVue'
import locationStore from '@DpJs/store/procedure/Location'
import Procedures from '@DpJs/components/procedure/publicindex/Procedures'
import procedureStore from '@DpJs/store/procedure/Procedure'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'

const stores = {
  location: locationStore,
  procedure: procedureStore
}
const components = {
  DpProcedures: Procedures,
  RegisterFlyout
}

initialize(components, stores)
