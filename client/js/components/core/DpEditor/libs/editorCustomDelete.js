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

export default class EditorCustomDelete extends Mark {
  get name () {
    return 'delete'
  }

  get schema () {
    return {
      parseDOM: [
        {
          tag: 'del'
        }
      ],
      toDOM: () => ['del', { title: Translator.trans('text.deleted') }, 0]
    }
  }

  commands ({ type }) {
    return () => toggleMark(type)
  }
}
