/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { embedBoilerplateContent, stripBoilerplateContent } from '@DpJs/components/procedure/StatementSegmentsList/utils/boilerplateTagContent'

describe('boilerplateTagContent', () => {
  const boilerplatesById = {
    'boilerplate-1': { attributes: { text: '<p>Textbaustein eins</p>' } },
    'boilerplate-2': { attributes: { text: '<p>Textbaustein zwei</p><ul><li>Punkt</li></ul>' } },
  }

  describe('embedBoilerplateContent', () => {
    it('fills an empty tag with the boilerplate\'s current text', () => {
      const tagFormHtml = '<p>Vorher</p><dp-boilerplate boilerplate-id="boilerplate-1"></dp-boilerplate><p>Nachher</p>'

      expect(embedBoilerplateContent(tagFormHtml, boilerplatesById)).toBe(
        '<p>Vorher</p><dp-boilerplate boilerplate-id="boilerplate-1"><p>Textbaustein eins</p></dp-boilerplate><p>Nachher</p>',
      )
    })

    it('fills multiple different tags independently', () => {
      const tagFormHtml = '<dp-boilerplate boilerplate-id="boilerplate-1"></dp-boilerplate><dp-boilerplate boilerplate-id="boilerplate-2"></dp-boilerplate>'

      expect(embedBoilerplateContent(tagFormHtml, boilerplatesById)).toBe(
        '<dp-boilerplate boilerplate-id="boilerplate-1"><p>Textbaustein eins</p></dp-boilerplate>' +
        '<dp-boilerplate boilerplate-id="boilerplate-2"><p>Textbaustein zwei</p><ul><li>Punkt</li></ul></dp-boilerplate>',
      )
    })

    it('falls back to an empty paragraph when the boilerplate is not found', () => {
      const tagFormHtml = '<dp-boilerplate boilerplate-id="missing-id"></dp-boilerplate>'

      expect(embedBoilerplateContent(tagFormHtml, boilerplatesById)).toBe(
        '<dp-boilerplate boilerplate-id="missing-id"><p></p></dp-boilerplate>',
      )
    })

    it('leaves html without any boilerplate tag unchanged', () => {
      const tagFormHtml = '<p>Nur normaler Text</p>'

      expect(embedBoilerplateContent(tagFormHtml, boilerplatesById)).toBe(tagFormHtml)
    })
  })

  describe('stripBoilerplateContent', () => {
    it('empties a filled tag back out', () => {
      const contentFormHtml = '<p>Vorher</p><dp-boilerplate boilerplate-id="boilerplate-1"><p>Textbaustein eins</p></dp-boilerplate><p>Nachher</p>'

      expect(stripBoilerplateContent(contentFormHtml)).toBe(
        '<p>Vorher</p><dp-boilerplate boilerplate-id="boilerplate-1"></dp-boilerplate><p>Nachher</p>',
      )
    })

    it('empties multiple tags, including nested lists', () => {
      const contentFormHtml = '<dp-boilerplate boilerplate-id="boilerplate-1"><p>Textbaustein eins</p></dp-boilerplate>' +
        '<dp-boilerplate boilerplate-id="boilerplate-2"><p>Textbaustein zwei</p><ul><li>Punkt</li></ul></dp-boilerplate>'

      expect(stripBoilerplateContent(contentFormHtml)).toBe(
        '<dp-boilerplate boilerplate-id="boilerplate-1"></dp-boilerplate><dp-boilerplate boilerplate-id="boilerplate-2"></dp-boilerplate>',
      )
    })

    it('leaves html without any boilerplate tag unchanged', () => {
      const contentFormHtml = '<p>Nur normaler Text</p>'

      expect(stripBoilerplateContent(contentFormHtml)).toBe(contentFormHtml)
    })
  })

  describe('round trip', () => {
    it('returns the original tag-form html after embedding and stripping again', () => {
      const tagFormHtml = '<p>Vorher</p><dp-boilerplate boilerplate-id="boilerplate-1"></dp-boilerplate>' +
        '<p>Mitte</p><dp-boilerplate boilerplate-id="boilerplate-2"></dp-boilerplate><p>Nachher</p>'

      const embedded = embedBoilerplateContent(tagFormHtml, boilerplatesById)

      expect(stripBoilerplateContent(embedded)).toBe(tagFormHtml)
    })
  })
})
