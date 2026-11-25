/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { dpApi } from '@demos-europe/demosplan-ui'
import SegmentsBulkEdit from '@DpJs/components/procedure/SegmentsBulkEdit/SegmentsBulkEdit.vue'

const { methods } = SegmentsBulkEdit

const createSegment = (id, recommendation, assigneeId = null) => ({
  id,
  attributes: { externId: `M-${id}`, recommendation, text: `Text ${id}` },
  relationships: {
    ...(assigneeId ? { assignee: { data: { id: assigneeId, type: 'AssignableUser' } } } : {}),
    parentStatement: { data: { id: `statement-${id}`, type: 'Statement' } },
  },
})

const createResponse = (segments, included = []) => ({ data: { data: segments, included } })

const createDeferred = () => {
  let resolve
  const promise = new Promise(resolvePromise => {
    resolve = resolvePromise
  })

  return { promise, resolve }
}

describe('SegmentsBulkEdit.getRecommendationEdit', () => {
  const getEdit = (text, isTextAttached) => methods.getRecommendationEdit.call({
    actions: { addRecommendations: { isTextAttached, text } },
  })

  it('fills the recommendations when attaching or replacing with a text', () => {
    expect(getEdit('<p>Text</p>', true)).toBe('fills')
    expect(getEdit('<p>Text</p>', false)).toBe('fills')
  })

  it('keeps the recommendations when attaching an empty text', () => {
    expect(getEdit('', true)).toBe('keeps')
    expect(getEdit('<p></p>', true)).toBe('keeps')
  })

  it('empties the recommendations when replacing them with an empty text', () => {
    expect(getEdit('', false)).toBe('empties')
    expect(getEdit('<p></p>', false)).toBe('empties')
  })
})

describe('SegmentsBulkEdit.loadSegmentsWithoutRecommendation', () => {
  let getSpy

  const createContext = (overrides = {}) => ({
    actions: { addRecommendations: { isTextAttached: true, text: '' } },
    getAssigneeNames: methods.getAssigneeNames,
    getRecommendationEdit: methods.getRecommendationEdit,
    isLoadingSegmentsWithoutRecommendation: false,
    isLockingPlaceSelected: true,
    pagination: { currentPage: 3 },
    procedureId: 'procedure-id',
    requestId: 0,
    segments: ['s1', 's2'],
    segmentsWithoutRecommendation: [],
    toTableRow: methods.toTableRow,
    ...overrides,
  })

  const load = context => methods.loadSegmentsWithoutRecommendation.call(context)

  beforeEach(() => {
    getSpy = vi.spyOn(dpApi, 'get')
    globalThis.Routing = {
      generate: vi.fn((route, params) => `${route}/${params.statementId}/${params.segment}`),
      getBaseUrl: () => '',
    }
    globalThis.dplan = { notify: { notify: vi.fn() } }
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('loads nothing when the chosen place is not locked', async () => {
    const context = createContext({ isLockingPlaceSelected: false })

    await load(context)

    expect(getSpy).not.toHaveBeenCalled()
    expect(context.segmentsWithoutRecommendation).toEqual([])
    expect(context.isLoadingSegmentsWithoutRecommendation).toBe(false)
  })

  it('lists only segments with an empty recommendation, including empty editor content', async () => {
    getSpy.mockResolvedValue(createResponse([
      createSegment('s1', ''),
      createSegment('s2', '<p></p>'),
      createSegment('s3', '<p>Antwort</p>'),
    ]))
    const context = createContext({ segments: ['s1', 's2', 's3'] })

    await load(context)

    expect(context.segmentsWithoutRecommendation.map(row => row.id)).toEqual(['s1', 's2'])
    expect(context.pagination.currentPage).toBe(1)
    expect(context.isLoadingSegmentsWithoutRecommendation).toBe(false)
  })

  it('combines the results of several chunks of ids', async () => {
    const ids = Array.from({ length: 201 }, (unused, index) => `s${index}`)

    getSpy.mockImplementation((url, { filter }) => Promise.resolve(createResponse(
      filter.idIsOneOf.condition.value.map((id, index) => createSegment(id, index % 2 === 0 ? '' : '<p>Antwort</p>')),
    )))
    const context = createContext({ segments: ids })

    await load(context)

    expect(getSpy).toHaveBeenCalledTimes(2)
    expect(getSpy.mock.calls[0][1].filter.idIsOneOf.condition.value).toHaveLength(200)
    expect(getSpy.mock.calls[1][1].filter.idIsOneOf.condition.value).toEqual(['s200'])
    // Chunk 1 has 100 empty segments (even positions), chunk 2 has its single one at position 0
    expect(context.segmentsWithoutRecommendation).toHaveLength(101)
  })

  it('does not warn when the same bulk edit fills every recommendation', async () => {
    const context = createContext({ actions: { addRecommendations: { isTextAttached: false, text: '<p>Neu</p>' } } })

    await load(context)

    expect(getSpy).not.toHaveBeenCalled()
    expect(context.segmentsWithoutRecommendation).toEqual([])
    expect(context.isLoadingSegmentsWithoutRecommendation).toBe(false)
  })

  it('lists every segment when the same bulk edit replaces the recommendations with an empty text', async () => {
    getSpy.mockResolvedValue(createResponse([
      createSegment('s1', '<p>Antwort</p>'),
      createSegment('s2', '<p>Antwort</p>'),
    ]))
    const context = createContext({ actions: { addRecommendations: { isTextAttached: false, text: '' } } })

    await load(context)

    expect(context.segmentsWithoutRecommendation.map(row => row.id)).toEqual(['s1', 's2'])
  })

  it('resolves the assignee name from the included resources', async () => {
    getSpy.mockResolvedValue(createResponse(
      [createSegment('s1', '', 'user-1'), createSegment('s2', '')],
      [{ id: 'user-1', type: 'AssignableUser', attributes: { firstname: 'Motoko', lastname: 'Kusanagi' } }],
    ))
    const context = createContext()

    await load(context)

    expect(context.segmentsWithoutRecommendation.map(row => row.assigneeName)).toEqual(['Motoko Kusanagi', '—'])
  })

  it('ignores the response of an older request after a newer one was started', async () => {
    const older = createDeferred()
    const newer = createDeferred()

    getSpy.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise)
    const context = createContext()

    const olderLoad = load(context)
    const newerLoad = load(context)

    newer.resolve(createResponse([createSegment('new', '')]))
    await newerLoad
    older.resolve(createResponse([createSegment('old', '')]))
    await olderLoad

    expect(context.segmentsWithoutRecommendation.map(row => row.id)).toEqual(['new'])
    expect(context.isLoadingSegmentsWithoutRecommendation).toBe(false)
  })
})
