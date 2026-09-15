/**
 * Tests for serializing the ProseMirror document into the order-based `contentBlocks` shape
 * (segment blocks for segmentMark ranges, textSection blocks for the leftover gaps), which
 * replaces sending `textualReference` + `<segment-mark>` HTML on save/confirm.
 */

import { addListNodes } from 'prosemirror-schema-list'
import { DOMParser } from 'prosemirror-model'
import { EditorState } from 'prosemirror-state'
import { initRangePlugin } from '@DpJs/lib/prosemirror/plugins'
import { schema } from 'prosemirror-schema-basic'
import { Schema } from 'prosemirror-model'
import { segmentMark } from '@DpJs/lib/prosemirror/marks'
import SplitStatementView from '@DpJs/components/statement/splitStatement/SplitStatementView'

const { extractContentBlocks } = SplitStatementView.methods

/**
 * Builds a real, minimal ProseMirror EditorState from an HTML string, using the same schema
 * construction as `SegmentationEditor.vue`'s `initialize()`, and wraps it into the shape
 * `extractContentBlocks` expects on `this`.
 */
const buildContext = (html, segments = []) => {
  const proseSchema = new Schema({
    nodes: addListNodes(schema.spec.nodes, 'paragraph block*', 'block'),
    marks: schema.spec.marks.update('segmentMark', segmentMark),
  })
  const rangePlugin = initRangePlugin(proseSchema, () => {}, () => {})
  const wrapper = document.createElement('div')

  wrapper.innerHTML = html
  const doc = DOMParser.fromSchema(rangePlugin.schema).parse(wrapper, { preserveWhitespace: true })
  const state = EditorState.create({ doc, plugins: rangePlugin.plugins })

  return {
    prosemirror: {
      view: { state },
      keyAccess: rangePlugin.keys,
    },
    segmentById: id => segments.find(segment => segment.id === id),
  }
}

describe('SplitStatementView.extractContentBlocks', () => {
  it('produces textSection/segment/textSection blocks for a segment isolated in its own paragraph', () => {
    const html = '<p>Before.</p><p><segment-mark data-segment-id="a" data-range-confirmed="true">Middle.</segment-mark></p><p>After.</p>'
    const context = buildContext(html, [{ id: 'a', tags: [] }])

    const blocks = extractContentBlocks.call(context)

    expect(blocks.map(block => block.type)).toEqual(['textSection', 'segment', 'textSection'])
    expect(blocks.map(block => block.order)).toEqual([1, 2, 3])
    expect(blocks[0].text.trim()).toBe('Before.')
    expect(blocks[1]).toMatchObject({ id: 'a', status: 'confirmed' })
    expect(blocks[1].text).toContain('Middle.')
    expect(blocks[2].text.trim()).toBe('After.')
  })

  it('preserves all text with no loss or duplication when a segment sits mid-paragraph', () => {
    const html = '<p>Before <segment-mark data-segment-id="a" data-range-confirmed="false">middle</segment-mark> after.</p>'
    const context = buildContext(html, [{ id: 'a', tags: [] }])

    const blocks = extractContentBlocks.call(context)

    expect(blocks.map(block => block.type)).toEqual(['textSection', 'segment', 'textSection'])
    expect(blocks[1].status).toBe(false)

    // No characters lost or duplicated across the split, regardless of how each piece is wrapped.
    const reconstructedPlainText = blocks.map(block => block.text).join('')

    expect(reconstructedPlainText).toBe('Before middle after.')
  })

  it('never leaks ProseMirror-internal editing attributes (data-range-*, data-pm-id) into the persisted HTML', () => {
    const html = '<p>Before <segment-mark data-segment-id="a" data-range-confirmed="true">middle</segment-mark> after.</p>'
    const context = buildContext(html, [{ id: 'a', tags: [] }])

    const blocks = extractContentBlocks.call(context)

    blocks.forEach(block => {
      expect(block.textRaw).not.toMatch(/data-(range-\w+|pm-id|segment-id)/)
    })
  })

  it('preserves both paragraphs of a segment spanning multiple blocks', () => {
    const html = '<p><segment-mark data-segment-id="a" data-range-confirmed="true">Para one.</segment-mark></p>' +
      '<p><segment-mark data-segment-id="a" data-range-confirmed="true">Para two.</segment-mark></p>'
    const context = buildContext(html, [{ id: 'a', tags: [] }])

    const blocks = extractContentBlocks.call(context)

    expect(blocks).toHaveLength(1)
    expect(blocks[0].type).toBe('segment')
    expect(blocks[0].text).toBe('<p>Para one.</p><p>Para two.</p>')
  })

  it('does not leak a spurious empty paragraph when a gap boundary lands exactly at a sibling paragraph edge', () => {
    /*
     * "Before."/"After." each occupy their own whole paragraph, so the gap boundaries around the
     * segment land exactly at the neighboring paragraph's start/end - a ProseMirror doc.slice()
     * edge case that (without normalization) leaks an extra empty "<p></p>" into the fragment.
     */
    const html = '<p>Before.</p><p><segment-mark data-segment-id="a" data-range-confirmed="true">Middle.</segment-mark></p><p>After.</p>'
    const context = buildContext(html, [{ id: 'a', tags: [] }])

    const blocks = extractContentBlocks.call(context)

    expect(blocks[0].textRaw).toBe('<p>Before.</p>')
    expect(blocks[1].textRaw).toBe('Middle.')
    expect(blocks[2].textRaw).toBe('<p>After.</p>')
  })

  it('normalizes gap boundaries through multiple nesting levels (list items)', () => {
    const html = '<p>Intro.</p><ul><li><segment-mark data-segment-id="a" data-range-confirmed="true">Item one.</segment-mark></li><li>Item two.</li></ul>'
    const context = buildContext(html, [{ id: 'a', tags: [] }])

    const blocks = extractContentBlocks.call(context)

    expect(blocks.map(block => block.type)).toEqual(['textSection', 'segment', 'textSection'])
    expect(blocks[0].textRaw).toBe('<p>Intro.</p>')
    expect(blocks[2].textRaw).toBe('<ul><li><p>Item two.</p></li></ul>')
  })

  it('skips a whitespace-only gap between two segments', () => {
    const html = '<p><segment-mark data-segment-id="a" data-range-confirmed="true">First</segment-mark></p>' +
      '<p> </p>' +
      '<p><segment-mark data-segment-id="b" data-range-confirmed="true">Second</segment-mark></p>'
    const context = buildContext(html, [{ id: 'a', tags: [] }, { id: 'b', tags: [] }])

    const blocks = extractContentBlocks.call(context)

    expect(blocks.map(block => block.type)).toEqual(['segment', 'segment'])
    expect(blocks.map(block => block.id)).toEqual(['a', 'b'])
  })

  it('produces a single textSection block when there are no segments', () => {
    const html = '<p>Just plain text, nothing segmented yet.</p>'
    const context = buildContext(html, [])

    const blocks = extractContentBlocks.call(context)

    expect(blocks).toHaveLength(1)
    expect(blocks[0]).toMatchObject({ type: 'textSection', order: 1 })
  })

  it('does not emit a zero-width textSection block between adjacent segments', () => {
    const html = '<p>' +
      '<segment-mark data-segment-id="a" data-range-confirmed="true">First</segment-mark>' +
      '<segment-mark data-segment-id="b" data-range-confirmed="true">Second</segment-mark>' +
      '</p>'
    const context = buildContext(html, [{ id: 'a', tags: [] }, { id: 'b', tags: [] }])

    const blocks = extractContentBlocks.call(context)

    expect(blocks.map(block => block.type)).toEqual(['segment', 'segment'])
  })

  it('merges tags/place/assigneeId/deadline from the store segment, omitting absent fields', () => {
    const html = '<p><segment-mark data-segment-id="a" data-range-confirmed="true">Text</segment-mark></p>'
    const segments = [{
      id: 'a',
      tags: [{ id: 't1', tagName: 'Tag 1' }],
      place: { id: 'p1', name: 'Place 1' },
      assigneeId: 'u1',
      deadline: '2026-01-01',
    }]
    const context = buildContext(html, segments)

    const blocks = extractContentBlocks.call(context)
    const segmentBlock = blocks.find(block => block.type === 'segment')

    expect(segmentBlock.tags).toEqual(segments[0].tags)
    expect(segmentBlock.place).toEqual(segments[0].place)
    expect(segmentBlock.assigneeId).toBe('u1')
    expect(segmentBlock.deadline).toBe('2026-01-01')

    const htmlWithoutOptionalFields = '<p><segment-mark data-segment-id="b" data-range-confirmed="true">Text</segment-mark></p>'
    const bareContext = buildContext(htmlWithoutOptionalFields, [{ id: 'b', tags: [] }])
    const bareBlocks = extractContentBlocks.call(bareContext)
    const bareSegmentBlock = bareBlocks.find(block => block.type === 'segment')

    expect(bareSegmentBlock).not.toHaveProperty('place')
    expect(bareSegmentBlock).not.toHaveProperty('assigneeId')
    expect(bareSegmentBlock).not.toHaveProperty('deadline')
  })

  it('derives status from the ProseMirror mark, not from a stale/absent store segment status', () => {
    const html = '<p><segment-mark data-segment-id="a" data-range-confirmed="true">Text</segment-mark></p>'
    /*
     * The store segment carries no `status` at all (or a stale one) - the block must still reflect
     * the PM mark's isConfirmed=true.
     */
    const context = buildContext(html, [{ id: 'a', tags: [], status: false }])

    const blocks = extractContentBlocks.call(context)
    const segmentBlock = blocks.find(block => block.type === 'segment')

    expect(segmentBlock.status).toBe('confirmed')
  })
})
