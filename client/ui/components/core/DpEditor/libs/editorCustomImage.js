/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpResizableImage from '../DpResizableImage'
import { Node } from 'tiptap'
import { nodeInputRule } from 'tiptap-commands'

/**
 * Matches following attributes in Markdown-typed image: [, alt, src, title]
 *
 * Example:
 * ![Lorem](image.jpg) -> [, "Lorem", "image.jpg"]
 * ![](image.jpg "Ipsum") -> [, "", "image.jpg", "Ipsum"]
 * ![Lorem](image.jpg "Ipsum") -> [, "Lorem", "image.jpg", "Ipsum"]
 */
const IMAGE_INPUT_REGEX = /!\[(.+|:?)]\((\S+)(?:(?:\s+)["'](\S+)["'])?\)/

export default class Image extends Node {
  get name () {
    return 'image'
  }

  get schema () {
    return {
      inline: true,
      attrs: {
        src: {},
        alt: {
          default: null
        },
        title: {
          default: null
        },
        width: {
          default: null
        },
        height: {
          default: null
        }
      },
      group: 'inline',
      draggable: true,
      parseDOM: [
        {
          tag: 'img[src]',
          getAttrs: dom => {
            return ({
              src: dom.getAttribute('src'),
              title: dom.getAttribute('title'),
              alt: dom.getAttribute('alt'),
              width: dom.getAttribute('width'),
              height: dom.getAttribute('height')
            })
          }
        }
      ],
      toDOM: node => {
        return ['img', { ...node.attrs, unselectable: 'on' }]
      }
    }
  }

  commands ({ type }) {
    return {
      insertImage: attrs => (state, dispatch) => {
        const { selection } = state
        const position = selection.$cursor ? selection.$cursor.pos : selection.$to.pos
        const node = type.create(attrs)
        const transaction = state.tr.insert(position, node)
        dispatch(transaction)
      }
    }
  }

  inputRules ({ type }) {
    return [
      nodeInputRule(IMAGE_INPUT_REGEX, type, match => {
        const [, alt, src, title, width, height] = match
        return {
          src,
          alt,
          title,
          width,
          height
        }
      })
    ]
  }

  // Return a vue component
  get view () {
    return DpResizableImage
  }
}
