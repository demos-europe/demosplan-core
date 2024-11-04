import { normalizeHtmlString, ProseMirrorConverter } from '@DpJs/lib/prosemirror/converter'
import { converterData } from '@DpJs/lib/prosemirror/converterData'

describe('ProseMirrorConverter', () => {
  let converter

  beforeEach(() => {
    converter = new ProseMirrorConverter()
  })

  it('Should set the ProseMirror data accordingly', () => {
    const proseMirrorData = converterData
    const converterWithProseMirror = converter.fromProseMirror(proseMirrorData).toHtml()
    expect(converterWithProseMirror).toBeInstanceOf(ProseMirrorConverter)
  })

  it('Creates a valid HTML string from prosemirror data', () => {
    const proseMirrorData = converterData
    const validHTML = `
      <dp-statement :statement-id="${proseMirrorData.data.relationships.statement.data.id}">
        ${proseMirrorData.data.relationships.draftSegments.data.map(segment => `
        <dp-segment
          :type="${proseMirrorData.data.type}"
          :id="${segment.id}">
        </dp-segment>`).join('')}
      </dp-statement>`.trim()
    const convertedProseMirrorData = converter.fromProseMirror(proseMirrorData).toHtml().getHtml()
    expect(normalizeHtmlString(convertedProseMirrorData)).toBe(normalizeHtmlString(validHTML))
  })
})
