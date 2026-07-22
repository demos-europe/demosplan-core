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
import StatementSegment from '@DpJs/components/procedure/StatementSegmentsList/StatementSegment'

describe('boilerplate usage tracking', () => {
  let handleInsertText
  let postSpy

  beforeEach(() => {
    handleInsertText = jest.fn()
    postSpy = jest.spyOn(dpApi, 'post').mockResolvedValue()
    globalThis.hasPermission = jest.fn(() => true)
    globalThis.Routing = { generate: jest.fn(() => 'usage-url') }
  })

  afterEach(() => {
    jest.restoreAllMocks()
  })

  describe('StatementSegment.insertBoilerplateText', () => {
    const createContext = () => ({
      procedureId: 'procedure-id',
      segment: { id: 'segment-id' },
    })

    it('inserts the text and records the usage when permitted', () => {
      const context = createContext()

      StatementSegment.methods.insertBoilerplateText.call(context, '<p>Text</p>', 'boilerplate-id', handleInsertText)

      expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
      expect(globalThis.Routing.generate).toHaveBeenCalledWith(
        'dplan_boilerplate_usage_create',
        { procedureId: 'procedure-id', boilerplateId: 'boilerplate-id' },
      )
      expect(postSpy).toHaveBeenCalledWith('usage-url', {}, { segmentId: 'segment-id' })
    })

    it('inserts the text but records nothing without the permission', () => {
      globalThis.hasPermission = jest.fn(() => false)

      StatementSegment.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', handleInsertText)

      expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
      expect(postSpy).not.toHaveBeenCalled()
    })

    it('inserts the text but records nothing without a boilerplate id', () => {
      StatementSegment.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', '', handleInsertText)

      expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
      expect(postSpy).not.toHaveBeenCalled()
    })

    it('swallows a failed usage request', async () => {
      postSpy.mockRejectedValue(new Error('network'))

      await expect(
        StatementSegment.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', handleInsertText),
      ).resolves.toBeUndefined()
    })
  })

  describe('SegmentsBulkEdit.insertBoilerplateText', () => {
    const createContext = (segments = ['segment-1', 'segment-2']) => ({
      procedureId: 'procedure-id',
      segments,
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
      globalThis.hasPermission = jest.fn(() => false)

      SegmentsBulkEdit.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', handleInsertText)

      expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
      expect(postSpy).not.toHaveBeenCalled()
    })
  })
})
