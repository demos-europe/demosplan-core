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

  it('Should set the HTML data accordingly', () => {
    const htmlString = `
      <custom-html-tag>
        ${converterData.data.relationships.draftSegments.data.map(segment => `
        <custom-content
          type="${converterData.data.type}"
          id="${segment.id}"
          draft-segments="[${converterData.data.relationships.draftSegments.data.map(s => s.id).join(', ')}]"
          statement-id="${converterData.data.relationships.statement.data.id}">
        </custom-content>`).join('')}
      </custom-html-tag>`.trim()
    const converterWithHtml = converter.fromHtml(htmlString)
    expect(converterWithHtml).toBeInstanceOf(ProseMirrorConverter)
  })

  it('Creates a valid HTML string from prosemirror data', () => {
    const proseMirrorData = converterData
    const validHTML = `
      <custom-html-tag>
        ${proseMirrorData.data.relationships.draftSegments.data.map(segment => `
        <custom-content
          type="${proseMirrorData.data.type}"
          id="${segment.id}"
          draft-segments="[${proseMirrorData.data.relationships.draftSegments.data.map(s => s.id).join(', ')}]"
          statement-id="${proseMirrorData.data.relationships.statement.data.id}">
        </custom-content>`).join('')}
      </custom-html-tag>`.trim()
    const convertedProseMirrorData = converter.fromProseMirror(proseMirrorData).toHtml().getHtml()
    expect(normalizeHtmlString(convertedProseMirrorData)).toBe(normalizeHtmlString(validHTML))
  })

  it('Creates valid prosemirror data from HTML string', () => {
    const proseMirrorData = converterData
    const htmlString = `
      <custom-html-tag>
        ${proseMirrorData.data.relationships.draftSegments.data.map(segment => `
        <custom-content
          type="${proseMirrorData.data.type}"
          id="${segment.id}"
          draft-segments="[${proseMirrorData.data.relationships.draftSegments.data.map(s => s.id).join(', ')}]"
          statement-id="${proseMirrorData.data.relationships.statement.data.id}">
        </custom-content>`).join('')}
      </custom-html-tag>`.trim()
    const convertedHTML = converter.fromHtml(htmlString).toProseMirror()
  })
})
