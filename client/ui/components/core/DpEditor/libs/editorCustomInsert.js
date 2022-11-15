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

export default class EditorCustomInsert extends Mark {
  get name () {
    return 'insert'
  }

  get schema () {
    return {
      parseDOM: [
        {
          tag: 'ins'
        }
      ],
      toDOM: () => ['ins', { title: Translator.trans('text.inserted') }, 0]
    }
  }

  commands ({ type }) {
    return () => toggleMark(type)
  }
}
