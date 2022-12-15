/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for development_release_new.html.twig
 */

import { DpDateRangePicker, DpEditor } from '@demos-europe/demosplan-ui'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpDateRangePicker, DpEditor }

initialize(components)
