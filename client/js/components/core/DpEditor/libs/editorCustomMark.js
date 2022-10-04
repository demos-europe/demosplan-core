/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { Mark } from 'tiptap'
import { toggleMark } from 'tiptap-commands'

export default class EditorCustomMark extends Mark {
  get name () {
    return 'mark'
  }

  get schema () {
    return {
      parseDOM: [
        {
          tag: 'mark'
        }
      ],
      toDOM: () => ['mark', { title: Translator.trans('text.mark') }, 0]
    }
  }

  commands ({ type }) {
    return () => toggleMark(type)
  }
}
