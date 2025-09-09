/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

// Dp libs -> initialize on mount of vue instance
import {
  ActionMenu,
  Confirm,
  Sticky
} from '@demos-europe/demosplan-ui'

import {
  CharCount,
  CheckableItem,
  FloodControlField,
  FormActions,
  Pager,
  ToggleAnything
} from './libs'

/*
 * Libs to be invoked after vue mounted
 * -> register DOM event handlers not until vue has rendered templates
 */
export function loadLibs () {
  ActionMenu()
  CharCount()
  CheckableItem()
  Confirm()
  FloodControlField()
  FormActions()
  ToggleAnything()
  Pager()
  Sticky()
}
