/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for map_admin.html.twig
 */

import { initialize } from '@DpJs/InitVue'
import MapAdmin from '@DpJs/components/map/admin/MapAdmin'
import procedureMapSettings from '@DpJs/store/map/ProcedureMapSettings'

const components = { MapAdmin }
const stores = { procedureMapSettings }

initialize(components, stores)
