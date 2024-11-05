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
    const { included } = converterData
    const validHTML = `
      <dp-statement :statement-id="${proseMirrorData.data.id}">
        ${proseMirrorData.data.relationships.draftSegments.data.map(segment => `
        <dp-segment
          type="${proseMirrorData.data.type}"
          id="${segment.id}"
          tags="${JSON.stringify(included.filter(el => el.id === segment.id)[0].relationships.tags.data.map(tags => tags.id)).replace(/"/g, '\'')}">
          ${included.filter(el => el.id === segment.id).map(el =>  el.attributes.segment_text)}
        </dp-segment>`).join('')}
      </dp-statement>`.trim()
    const convertedProseMirrorData = converter.fromProseMirror(proseMirrorData).toHtml().getHtml()
    expect(normalizeHtmlString(convertedProseMirrorData)).toBe(normalizeHtmlString(validHTML))
  })
})
