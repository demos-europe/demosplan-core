/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

// This is used in blp participation area where RegisterFlyout is used in the header of every view.
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import RegisterFlyout from '@DpJs/components/core/RegisterFlyout'

const components = {
  RegisterFlyout
}

initialize(components)
