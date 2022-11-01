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

import DpDateRangePicker from '@DpJs/components/core/form/DpDateRangePicker'
import DpEditor from '@DpJs/components/core/DpEditor/DpEditor'
import { initialize } from '@DemosPlanCoreBundle/InitVue'

const components = { DpDateRangePicker, DpEditor }

initialize(components)
