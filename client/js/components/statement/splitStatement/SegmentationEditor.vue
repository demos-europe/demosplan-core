<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="segmentation-editor"
    @focus="event => $emit('focus', event)"
    @focusout="$emit('focusout')"
    @mouseleave="$emit('mouseleave')"
    @mouseover="event => $emit('mouseover', event)">
    <div
      id="editor"
      class="c-styled-html"
    />
  </div>
</template>

<script>
import { DOMParser, DOMSerializer, Schema } from 'prosemirror-model'
import { addListNodes } from 'prosemirror-schema-list'
import { EditorState } from 'prosemirror-state'
import { EditorView } from 'prosemirror-view'
import { initRangePlugin } from '@DpJs/lib/prosemirror/plugins'
import { schema } from 'prosemirror-schema-basic'
import { setRange } from '@DpJs/lib/prosemirror/commands'
import { v4 as uuid } from 'uuid'

export default {
  name: 'SegmentationEditor',

  props: {
    editToggleCallback: {
      type: Function,
      required: false,
      default: () => ({})
    },

    initStatementText: {
      type: String,
      required: true
    },

    segments: {
      type: Array,
      required: true
    },

    rangeChangeCallback: {
      type: Function,
      required: false,
      default: () => ({})
    }
  },

  emits: [
    'focus',
    'focusout',
    'mouseleave',
    'mouseover',
    'prosemirror:initialized',
    'prosemirror:maxRange'
  ],

  data () {
    return {
      customMarks: {
        underline: {
          parseDOM: [{ tag: 'u' }],
          toDOM () {
            return ['u']
          }
        },
        link: {
          attrs: {
            href: {},
            class: { default: null }
          },
          inclusive: false,
          parseDOM: [{
            tag: 'a[href]',
            getAttrs (dom) {
              return {
                href: dom.getAttribute('href'),
                class: dom.getAttribute('class')
              }
            }
          }],
          toDOM (node) {
            const { href, class: className } = node.attrs
            return ['a', { href, class: className }, 0]
          }
        }
      },
      maxRange: 0
    }
  },

  methods: {
    getExtendedMarks () {
      let extendedMarks = schema.spec.marks

      for (const [key, value] of Object.entries(this.customMarks)) {
        extendedMarks = extendedMarks.update(key, value)
      }

      return extendedMarks
    },

    initialize () {
      const proseSchema = new Schema({
        nodes: addListNodes(schema.spec.nodes, 'paragraph block*', 'block'),
        marks: this.getExtendedMarks()
      })
      const wrapper = document.createElement('div')
      wrapper.innerHTML = this.initStatementText ?? ''
      const rangePlugin = initRangePlugin(proseSchema, this.rangeChangeCallback, this.editToggleCallback)
      const parsedContent = DOMParser.fromSchema(rangePlugin.schema).parse(wrapper, { preserveWhitespace: true })

      this.maxRange = parsedContent.content.size

      const view = new EditorView(document.querySelector('#editor'), {
        editable: () => false,
        state: EditorState.create({
          doc: parsedContent,
          plugins: rangePlugin.plugins
        })
      })

      const transformedSegments = this.transformSegments(this.segments.filter(segment => segment.charEnd <= this.maxRange))
      transformedSegments.forEach(segment => setRange(view)(segment.from, segment.to, segment.attributes))

      const getContent = (schema) => (state) => {
        const container = document.createElement('div')
        const serialized = DOMSerializer.fromSchema(schema).serializeFragment(state.doc.content, { document: window.document }, container)
        return serialized.innerHTML
      }

      let prosemirrorStateWrapper = {
        view,
        keyAccess: rangePlugin.keys,
        getContent: getContent(proseSchema)
      }

      /**
       * We've put a wrapper around our prosemirror instance and freeze it afterwards to prevent Vue from watching
       * prosemirror internals. This would lead to a huge performance hit otherwise.
       */
      prosemirrorStateWrapper = Object.freeze(prosemirrorStateWrapper)

      this.$emit('prosemirror:maxRange', this.maxRange)
      this.$emit('prosemirror:initialized', prosemirrorStateWrapper)
    },

    transformSegments (segments) {
      const segmentsCpy = JSON.parse(JSON.stringify(segments))
      return segmentsCpy.map(segment => {
        return {
          attributes: {
            rangeId: segment.id,
            isConfirmed: segment.status === 'confirmed',
            pmId: uuid()
          },
          from: segment.charStart,
          to: segment.charEnd
        }
      })
    }
  },

  mounted () {
    this.initialize()
  }
}
</script>
