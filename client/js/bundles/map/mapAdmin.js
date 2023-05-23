/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for map_admin.html.twig
 */

import DpMapAdmin from '@DpJs/components/map/admin/DpMapAdmin'
import { initialize } from '@DpJs/InitVue'

const components = { DpMapAdmin }

initialize(components)
