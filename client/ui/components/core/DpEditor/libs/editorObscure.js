/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * This is the obscure-extension for tiptap, built on the basis of tiptap bold-extension.
 * On mark-obscure in tiptap, we wrap up the marked content in <span class='u-obscure'></span> tags, and then before
 * saving the changes we convert them to <dp-obscure>, so that they are correctly saved in BE. But to display the
 * <span class='u-obscure'> tags in the editor we need to use the toDOM function provided by tiptap/prosemirror.
 *
 * InputRules and pasteRules help to handle diverse behaviour when we want to obscure only part of words or we want to
 * use more than one tool (e.g. obscure and bold) simultaneously, etc.
 */

import { markInputRule, markPasteRule, toggleMark } from 'tiptap-commands'
import { Mark } from 'tiptap'

export default class EditorObscure extends Mark {
  get name () {
    return 'obscure'
  }

  get schema () {
    return {
      parseDOM: [
        { tag: '.u-obscure' },
        { tag: 'dp-obscure' }
      ],
      toDOM: () => ['span', { class: (hasPermission('feature_obscure_text') ? 'u-obscure' : '') }, 0]
    }
  }
  /*
   * Keys ({ type }) {
   *   return {
   *     'Mod-obscure': toggleMark(type)
   *   }
   * }
   */

  commands ({ type }) {
    return () => toggleMark(type)
  }

  inputRules ({ type }) {
    return [
      markInputRule(/(?:<o>)([^<o>]+)(?:<o>)$/, type)
    ]
  }

  pasteRules ({ type }) {
    return [
      markPasteRule(/(?:<o>)([^<o>]+)(?:<o>)/g, type)
    ]
  }
}
