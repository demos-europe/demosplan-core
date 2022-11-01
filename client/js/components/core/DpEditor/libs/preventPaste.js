/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { Extension, Plugin } from 'tiptap'

export default class preventPaste extends Extension {
  get name () {
    return 'PreventProp'
  }

  get plugins () {
    return [
      new Plugin({
        props: {
          handlePaste () {
            return true
          }
        }
      })
    ]
  }
}
