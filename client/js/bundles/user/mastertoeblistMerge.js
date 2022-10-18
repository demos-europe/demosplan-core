/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for mastertoeblist_merge.html.twig
 */

import { initialize } from '@DemosPlanCoreBundle/InitVue'
import MasterToebListMerge from '@DemosPlanUserBundle/components/MasterToebListMerge/MasterToebListMerge'

const components = {
  MasterToebListMerge
}

initialize(components)
