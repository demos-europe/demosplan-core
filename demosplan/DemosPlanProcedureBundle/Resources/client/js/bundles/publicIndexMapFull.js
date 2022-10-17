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
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import locationStore from './../store/Location'
import Procedures from '../components/publicindex/Procedures'
import procedureStore from './../store/Procedure'
import RegisterFlyout from '@DpJs/components/core/RegisterFlyout'

const stores = {
  location: locationStore,
  procedure: procedureStore
}
const components = {
  DpProcedures: Procedures,
  RegisterFlyout
}

initialize(components, stores)
