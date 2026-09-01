/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { dpApi } from '@demos-europe/demosplan-ui'
import StatementSegment from '@DpJs/components/procedure/StatementSegmentsList/StatementSegment'

describe('StatementSegment recommendation embedding', () => {
  describe('recommendationForEditor', () => {
    it('returns the plain recommendation without the permission', () => {
      const context = { canLinkBoilerplate: false, segment: { attributes: { recommendation: 'plain text' } } }

      expect(StatementSegment.computed.recommendationForEditor.call(context)).toBe('plain text')
    })

    it('returns an empty string while recommendationEmbedded has not loaded yet', () => {
      const context = { canLinkBoilerplate: true, recommendationEmbedded: null, segment: { attributes: { recommendation: 'plain text' } } }

      expect(StatementSegment.computed.recommendationForEditor.call(context)).toBe('')
    })

    it('fills boilerplate tags with their current text once recommendationEmbedded is loaded', () => {
      const context = {
        canLinkBoilerplate: true,
        recommendationEmbedded: '<p>Vorher</p><dp-boilerplate boilerplate-id="1"></dp-boilerplate>',
        boilerplates: { 1: { attributes: { text: '<p>Textbaustein-Inhalt</p>' } } },
      }

      expect(StatementSegment.computed.recommendationForEditor.call(context))
        .toBe('<p>Vorher</p><dp-boilerplate boilerplate-id="1"><p>Textbaustein-Inhalt</p></dp-boilerplate>')
    })
  })

  describe('updateRecommendation', () => {
    it('strips boilerplate tag content before storing when linking is permitted', () => {
      const updateSegment = vi.fn()
      const context = { canLinkBoilerplate: true, updateSegment }

      StatementSegment.methods.updateRecommendation.call(context, '<dp-boilerplate boilerplate-id="1"><p>Text</p></dp-boilerplate>')

      expect(updateSegment).toHaveBeenCalledWith('recommendation', '<dp-boilerplate boilerplate-id="1"></dp-boilerplate>')
    })

    it('stores the value unchanged without the permission', () => {
      const updateSegment = vi.fn()
      const context = { canLinkBoilerplate: false, updateSegment }

      StatementSegment.methods.updateRecommendation.call(context, '<p>Text</p>')

      expect(updateSegment).toHaveBeenCalledWith('recommendation', '<p>Text</p>')
    })
  })

  describe('loadRecommendationEmbedded', () => {
    let getSpy

    const createContext = () => ({
      segment: { id: 'segment-id', attributes: { recommendation: 'fallback text' } },
      recommendationEmbedded: null,
      recommendationEmbeddedLoading: false,
    })

    beforeEach(() => {
      globalThis.Routing = { generate: vi.fn(() => 'segment-url') }
      getSpy = vi.spyOn(dpApi, 'get')
    })

    afterEach(() => {
      vi.restoreAllMocks()
    })

    it('requests the tag-form recommendation and stores it', async () => {
      getSpy.mockResolvedValue({ data: { data: { attributes: { recommendationEmbedded: '<p>Tag-Form</p>' } } } })
      const context = createContext()

      const promise = StatementSegment.methods.loadRecommendationEmbedded.call(context)

      expect(context.recommendationEmbeddedLoading).toBe(true)

      await promise

      expect(globalThis.Routing.generate).toHaveBeenCalledWith(
        'api_resource_get',
        { resourceType: 'StatementSegment', resourceId: 'segment-id' },
      )
      expect(getSpy).toHaveBeenCalledWith('segment-url', { fields: { StatementSegment: 'recommendationEmbedded' } })
      expect(context.recommendationEmbedded).toBe('<p>Tag-Form</p>')
      expect(context.recommendationEmbeddedLoading).toBe(false)
    })

    it('falls back to the substituted recommendation when the request fails', async () => {
      getSpy.mockRejectedValue(new Error('network'))
      const context = createContext()

      await StatementSegment.methods.loadRecommendationEmbedded.call(context)

      expect(context.recommendationEmbedded).toBe('fallback text')
      expect(context.recommendationEmbeddedLoading).toBe(false)
    })
  })
})
