/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { dpApi } from '@demos-europe/demosplan-ui'
import SegmentsBulkEdit from '@DpJs/components/procedure/SegmentsBulkEdit/SegmentsBulkEdit'

describe('SegmentsBulkEdit.insertBoilerplateText', () => {
  let handleInsertText
  let postSpy

  const createContext = (segments = ['segment-1', 'segment-2']) => ({
    procedureId: 'procedure-id',
    segments,
  })

  beforeEach(() => {
    handleInsertText = vi.fn()
    postSpy = vi.spyOn(dpApi, 'post').mockResolvedValue()
    globalThis.hasPermission = vi.fn(() => true)
    globalThis.Routing = { generate: vi.fn(() => 'usage-url') }
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('records the usage for every selected segment when permitted', () => {
    SegmentsBulkEdit.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', handleInsertText)

    expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
    expect(globalThis.Routing.generate).toHaveBeenCalledWith(
      'dplan_boilerplate_usage_create_bulk',
      { procedureId: 'procedure-id', boilerplateId: 'boilerplate-id' },
    )
    expect(postSpy).toHaveBeenCalledWith('usage-url', {}, { segmentIds: ['segment-1', 'segment-2'] })
  })

  it('records nothing when no segments are selected', () => {
    SegmentsBulkEdit.methods.insertBoilerplateText.call(createContext([]), '<p>Text</p>', 'boilerplate-id', handleInsertText)

    expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
    expect(postSpy).not.toHaveBeenCalled()
  })

  it('records nothing without the permission', () => {
    globalThis.hasPermission = vi.fn(() => false)

    SegmentsBulkEdit.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', handleInsertText)

    expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
    expect(postSpy).not.toHaveBeenCalled()
  })
})
