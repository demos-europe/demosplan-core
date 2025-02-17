/*
 * This is the obscure-extension for tiptap, built on the basis of tiptap bold-extension.
 * On mark-obscure in tiptap, we wrap up the marked content in <span class='u-obscure'></span> tags, and then before
 * saving the changes we convert them to <dp-obscure>, so that they are correctly saved in BE. But to display the
 * <span class='u-obscure'> tags in the editor we need to use the toDOM function provided by tiptap/prosemirror.
 *
 * InputRules and pasteRules help to handle diverse behaviour when we want to obscure only part of words or we want to
 * use more than one tool (e.g. obscure and bold) simultaneously, etc.
 */

import {
  Mark,
  markInputRule,
  markPasteRule
} from '@tiptap/core'

const markInputRegex = /(?:<o>)([^<o>]+)(?:<o>)$/
const markPasteRegex = /(?:<o>)([^<o>]+)(?:<o>)/g
export default Mark.create({
  name: 'segmentsMark',

  parseHTML () {
    return [
      { tag: '.segments-mark' },
      { tag: 'segments-mark' },
      { tag: 'segmentsMark' },
    ]
  },

  renderHTML () {
    return ['span', { class: 'segments-mark', 'data-range-confirmed': 'true' }, 0]
  },

  addCommands () {
    return {
      setObscure: () => ({ commands }) => {
        return commands.setMark(this.name)
      },
      toggleObscure: () => ({ commands }) => {
        return commands.toggleMark(this.name)
      },
      unsetObscure: () => ({ commands }) => {
        return commands.unsetMark(this.name)
      },
    }
  },

  addInputRules () {
    return [
      markInputRule({
        find: markInputRegex,
        type: this.type
      })
    ]
  },

  addPasteRules () {
    return [
      markPasteRule({
        find: markPasteRegex,
        type: this.type
      })
    ]
  }
})
