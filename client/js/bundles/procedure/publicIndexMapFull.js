/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
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
import { DpRegisterFlyout } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'
import locationStore from '@DpJs/store/procedure/Location'
import Procedures from '@DpJs/components/procedure/publicindex/Procedures'
import procedureStore from '@DpJs/store/procedure/Procedure'

const stores = {
  location: locationStore,
  procedure: procedureStore
}
const components = {
  DpProcedures: Procedures,
  DpRegisterFlyout
}

initialize(components, stores)
