/**
 * Tests for deriving SplitStatementStore state from either drafts-info payload shape:
 * the legacy `textualReference` + `segments` format, and the order-based `contentBlocks` format.
 */

import { deriveFromContentBlocks, deriveFromLegacy, normalizePlace } from '@DpJs/store/statement/storeHelpers/SplitStatementStore/ContentBlocks'

describe('normalizePlace', () => {
  const availablePlaces = [{ value: 'p1', label: 'Place 1' }, { value: 'p2', label: 'Place 2' }]

  it('passes through the frontend-authored { id, name } shape, preferring the known label', () => {
    expect(normalizePlace({ id: 'p1', name: 'Stale name' }, availablePlaces)).toEqual({ id: 'p1', name: 'Place 1' })
  })

  it('normalizes the read-side transformer\'s bare place-id string', () => {
    expect(normalizePlace('p2', availablePlaces)).toEqual({ id: 'p2', name: 'Place 2' })
  })

  it('falls back to the first available place when null', () => {
    expect(normalizePlace(null, availablePlaces)).toEqual({ id: 'p1', name: 'Place 1' })
  })

  it('falls back to an empty place when null and no places are available', () => {
    expect(normalizePlace(null, [])).toEqual({ id: '', name: '' })
  })
})

describe('deriveFromLegacy', () => {
  it('passes segments/textualReference through unchanged and backfills a missing place', () => {
    const attributes = {
      textualReference: '<segment-mark data-segment-id="a">Text</segment-mark>',
      segments: [{ id: 'a', tags: [] }],
    }
    const availablePlaces = [{ value: 'p1', label: 'Place 1' }]

    const result = deriveFromLegacy(attributes, availablePlaces)

    expect(result.initText).toBe(attributes.textualReference)
    expect(result.contentBlocks).toEqual([])
    expect(result.segments[0].place).toEqual({ id: 'p1', name: 'Place 1' })
  })

  it('backfills an empty place when no places are available', () => {
    const attributes = { textualReference: '', segments: [{ id: 'a', tags: [] }] }

    const result = deriveFromLegacy(attributes, [])

    expect(result.segments[0].place).toEqual({ id: '', name: '' })
  })

  it('does not overwrite a place that is already present', () => {
    const attributes = {
      textualReference: '',
      segments: [{ id: 'a', tags: [], place: { id: 'manual', name: 'Manual place' } }],
    }

    const result = deriveFromLegacy(attributes, [{ value: 'p1', label: 'Place 1' }])

    expect(result.segments[0].place).toEqual({ id: 'manual', name: 'Manual place' })
  })
})

describe('deriveFromContentBlocks', () => {
  const availablePlaces = [{ value: 'p1', label: 'Place 1' }]

  it('builds initText by wrapping segment blocks in <segment-mark> and leaving textSection blocks unwrapped, in order', () => {
    const contentBlocks = [
      { type: 'textSection', order: 1, text: 'Before.', textRaw: '<p>Before.</p>' },
      { type: 'segment', order: 2, id: 'a', text: 'Middle.', textRaw: 'Middle.', status: 'confirmed', tags: [] },
      { type: 'textSection', order: 3, text: 'After.', textRaw: '<p>After.</p>' },
    ]

    const result = deriveFromContentBlocks(contentBlocks, availablePlaces)

    expect(result.initText).toBe(
      '<p>Before.</p><segment-mark data-segment-id="a" data-range-confirmed="true">Middle.</segment-mark><p>After.</p>',
    )
  })

  it('sorts blocks by order before building initText, regardless of array order', () => {
    const contentBlocks = [
      { type: 'textSection', order: 2, text: 'Second.', textRaw: 'Second.' },
      { type: 'textSection', order: 1, text: 'First.', textRaw: 'First.' },
    ]

    const result = deriveFromContentBlocks(contentBlocks, availablePlaces)

    expect(result.initText).toBe('First.Second.')
  })

  it('builds segments only from segment blocks, never from textSection blocks', () => {
    const contentBlocks = [
      { type: 'textSection', order: 1, text: 'Leftover.', textRaw: '<p>Leftover.</p>' },
      { type: 'segment', order: 2, id: 'a', text: 'Text', textRaw: 'Text', status: 'confirmed', tags: [] },
    ]

    const result = deriveFromContentBlocks(contentBlocks, availablePlaces)

    expect(result.segments).toHaveLength(1)
    expect(result.segments[0].id).toBe('a')
  })

  it('maps tags/place/assigneeId/deadline from a frontend-authored segment block', () => {
    const contentBlocks = [{
      type: 'segment',
      order: 1,
      id: 'a',
      text: 'Text',
      textRaw: 'Text',
      status: 'confirmed',
      tags: [{ id: 't1', tagName: 'Tag 1' }],
      place: { id: 'p1', name: 'Place 1' },
      assigneeId: 'u1',
      deadline: '2026-01-01',
    }]

    const result = deriveFromContentBlocks(contentBlocks, availablePlaces)

    expect(result.segments[0]).toMatchObject({
      id: 'a',
      status: 'confirmed',
      tags: [{ id: 't1', tagName: 'Tag 1' }],
      place: { id: 'p1', name: 'Place 1' },
      assigneeId: 'u1',
      deadline: '2026-01-01',
    })
  })

  it('tolerates the read-side transformer\'s gaps: bare place-id string, empty tags, no assigneeId/deadline/status', () => {
    const contentBlocks = [{
      type: 'segment',
      order: 1,
      id: 'a',
      text: 'Text',
      textRaw: 'Text',
      tags: [],
      place: 'p1',
    }]

    const result = deriveFromContentBlocks(contentBlocks, availablePlaces)

    expect(result.segments[0]).toEqual({
      id: 'a',
      status: 'confirmed',
      tags: [],
      place: { id: 'p1', name: 'Place 1' },
    })
    expect(result.segments[0]).not.toHaveProperty('assigneeId')
    expect(result.segments[0]).not.toHaveProperty('deadline')
  })

  it('handles a null place on a segment block (never set)', () => {
    const contentBlocks = [{ type: 'segment', order: 1, id: 'a', text: 'Text', textRaw: 'Text', tags: [], place: null }]

    const result = deriveFromContentBlocks(contentBlocks, availablePlaces)

    expect(result.segments[0].place).toEqual({ id: 'p1', name: 'Place 1' })
  })

  it('returns the sorted contentBlocks array itself alongside the derived segments/initText', () => {
    const contentBlocks = [
      { type: 'textSection', order: 2, text: 'B', textRaw: 'B' },
      { type: 'textSection', order: 1, text: 'A', textRaw: 'A' },
    ]

    const result = deriveFromContentBlocks(contentBlocks, availablePlaces)

    expect(result.contentBlocks.map(block => block.text)).toEqual(['A', 'B'])
  })
})
