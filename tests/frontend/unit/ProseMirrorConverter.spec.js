import { ProseMirrorConverter } from '@DpJs/lib/prosemirror/converter'
import { converterData } from '@DpJs/lib/prosemirror/converterData'

describe('ProseMirrorConverter', () => {
  let converter

  beforeEach(() => {
    converter = new ProseMirrorConverter()
  })

  it('Should set the ProseMirror data accordingly', () => {
    const proseMirrorData = converterData
    const converterWithProseMirror = converter.fromProseMirror(proseMirrorData).toHtml()
    cexpect(converterWithProseMirror).toBeInstanceOf(ProseMirrorConverter)
  })

  it('Should set the HTML data accordingly', () => {
    const htmlString = ''
    const converterWithHtml = converter.fromHtml(htmlString)
    expect(converterWithHtml).toBeInstanceOf(ProseMirrorConverter)
  })

  it('Creates a valid HTML string from prosemirror data', () => {
    const proseMirrorData = converterData
    const validHTML = ''
    const convertedProseMirrorData = converter.fromProseMirror(proseMirrorData).toHtml().getHtml()
    expect(convertedProseMirrorData).toBe(validHTML)
  })

  it('Creates valid prosemirror data from HTML string', () => {
    const htmlString = ''
    const validProsemirror = {}
    const convertedHTML = converter.fromHtml(htmlString).toProseMirror()
    expect(convertedHTML).toBe(validProsemirror)
  })
})
