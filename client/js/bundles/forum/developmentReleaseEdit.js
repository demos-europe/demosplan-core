/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for development_release_edit.html.twig
 */

import { DpDateRangePicker, DpEditor } from 'demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpDateRangePicker, DpEditor }

initialize(components)
