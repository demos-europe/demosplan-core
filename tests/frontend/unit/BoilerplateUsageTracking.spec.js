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
    handleInsertText = vi.fn()
    postSpy = vi.spyOn(dpApi, 'post').mockResolvedValue()
    globalThis.hasPermission = vi.fn(() => true)
    globalThis.Routing = { generate: vi.fn(() => 'usage-url') }
    globalThis.Translator = { trans: vi.fn(key => key) }
    globalThis.dplan = { notify: { error: vi.fn() } }
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('StatementSegment.insertBoilerplateText', () => {
    const createContext = () => ({
      procedureId: 'procedure-id',
      segment: { id: 'segment-id' },
      canLinkBoilerplate: true,
    })

    it('inserts the text as a linked node and records the usage when permitted', () => {
      const context = createContext()
      const insertBoilerplate = vi.fn(() => true)

      StatementSegment.methods.insertBoilerplateText.call(context, '<p>Text</p>', 'boilerplate-id', insertBoilerplate, handleInsertText)

      expect(insertBoilerplate).toHaveBeenCalledWith('boilerplate-id', '<p>Text</p>')
      expect(handleInsertText).not.toHaveBeenCalled()
      expect(globalThis.Routing.generate).toHaveBeenCalledWith(
        'dplan_boilerplate_usage_create',
        { procedureId: 'procedure-id', boilerplateId: 'boilerplate-id' },
      )
      expect(postSpy).toHaveBeenCalledWith('usage-url', {}, { segmentId: 'segment-id' })
    })

    it('falls back to plain text and records nothing without the permission', () => {
      const context = { ...createContext(), canLinkBoilerplate: false }
      const insertBoilerplate = vi.fn(() => true)

      StatementSegment.methods.insertBoilerplateText.call(context, '<p>Text</p>', 'boilerplate-id', insertBoilerplate, handleInsertText)

      expect(insertBoilerplate).not.toHaveBeenCalled()
      expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
      expect(postSpy).not.toHaveBeenCalled()
    })

    it('falls back to plain text and records nothing without a boilerplate id', () => {
      const insertBoilerplate = vi.fn(() => true)

      StatementSegment.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', '', insertBoilerplate, handleInsertText)

      expect(insertBoilerplate).not.toHaveBeenCalled()
      expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
      expect(postSpy).not.toHaveBeenCalled()
    })

    it('notifies and records nothing when the boilerplate is already linked', () => {
      const insertBoilerplate = vi.fn(() => false)

      StatementSegment.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', insertBoilerplate, handleInsertText)

      expect(handleInsertText).not.toHaveBeenCalled()
      expect(globalThis.dplan.notify.error).toHaveBeenCalledWith('boilerplate.link.exists')
      expect(postSpy).not.toHaveBeenCalled()
    })

    it('swallows a failed usage request', async () => {
      const insertBoilerplate = vi.fn(() => true)

      postSpy.mockRejectedValue(new Error('network'))

      await expect(
        StatementSegment.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', insertBoilerplate, handleInsertText),
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
      globalThis.hasPermission = vi.fn(() => false)

      SegmentsBulkEdit.methods.insertBoilerplateText.call(createContext(), '<p>Text</p>', 'boilerplate-id', handleInsertText)

      expect(handleInsertText).toHaveBeenCalledWith('<p>Text</p>')
      expect(postSpy).not.toHaveBeenCalled()
    })
  })
})
