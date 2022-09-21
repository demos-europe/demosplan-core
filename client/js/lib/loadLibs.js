/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

// Dp libs -> initialize on mount of vue instance
import ActionMenu from './ActionMenu'
import CharCount from './CharCount'
import CheckableItem from './CheckableItem'
import Confirm from './DpConfirm'
import FloodControlField from './FloodControlField'
import { FormActions } from './FormActions'
import Pager from './Pager'
import Sticky from './Sticky'
import Tabs from './Tabs'
import ToggleAnything from './ToggleAnything'
import Tooltips from './Tooltips'

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
  Tabs()
  ToggleAnything()
  Pager()
  Sticky()

  window.dplan.tooltips = Tooltips()
}
