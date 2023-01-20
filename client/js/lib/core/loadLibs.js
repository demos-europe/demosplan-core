/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
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
} from '@demos-europe/demosplan-utils'

import {
  addFormHiddenField,
  CharCount,
  CheckableItem,
  FloodControlField,
  FormActions,
  Pager,
  removeFormHiddenField,
  ToggleAnything,
  Tooltips
} from './libs'

/*
 * Libs to be invoked after vue mounted
 * -> register DOM event handlers not until vue has rendered templates
 */
export function loadLibs () {
  ActionMenu()
  addFormHiddenField()
  CharCount()
  CheckableItem()
  Confirm()
  FloodControlField()
  FormActions()
  removeFormHiddenField()
  ToggleAnything()
  Pager()
  Sticky()

  window.dplan.tooltips = Tooltips()
}
