/**
 * This ProseMirrorConverter class is responsible for converting
 * ProseMirror data to an HTML string and to convert proseMirror data
 * to an HTML string.
 *
 * Example usage:
 * - from ProseMirror to html: `converter.fromProseMirror(ProseMirrorData).toHtml().getHtml()`
 * - from html to ProseMirror: `converter.fromHtml(htmlString).toProseMirror()`
 *
 * @param proseMirrorData - object
 * @param htmlString - string
 */
export class ProseMirrorConverter {
  constructor() {
    this.prosemirrorData = null
    this.htmlString = null
  }

  /**
   * Returns a ProseMirrorConverter instance filled with ProseMirror data.
   * @param proseMirrorData - string
   * @returns { ProseMirrorConverter }
   */
  fromProseMirror = (proseMirrorData) => {
    this.prosemirrorData = proseMirrorData
    return this
  }

  /**
   * Converts ProseMirror data to an HTML string.
   * @returns { ProseMirrorConverter }
   */
  toHtml = () => {
    try {
      const { type, id, relationships } = this.prosemirrorData.data
      const draftSegments = relationships.draftSegments.data.map(segment => segment.id).join(', ')
      const statementId = relationships.statement.data.id

      this.htmlString = ''
      console.log(type, id, draftSegments, statementId)
      // TODO: return HTML string.
      return this
    } catch (error) {
      console.error('Error converting ProseMirror data to HTML: ', error)
    }
  }

  /**
   * Returns a ProseMirrorConverter instance filled with an HTML string.
   * @param htmlString
   * @returns { ProseMirrorConverter }
   */
  fromHtml(htmlString) {
    try {
      const parser = new DOMParser()
      const doc = parser.parseFromString(htmlString, 'text/html')

      const customContent = doc.querySelector('custom-content')
      const type = customContent.getAttribute('type')
      const id = customContent.getAttribute('id')
      const draftSegments = customContent.getAttribute('draft-segments').split(', ')
      const statementId = customContent.getAttribute('statement-id')

      // TODO: Return real ProseMirror data object.
      this.prosemirrorData = {}
      console.log(type, id, draftSegments, statementId)
      return this
    } catch (error) {
      console.error('Error converting HTML to ProseMirror data: ', error)
    }
  }

  /**
   * Returns ProseMirror data.
   * @returns { object }
   */
  toProseMirror() {
    return this.prosemirrorData
  }

  /**
   * Returns an HTML string.
   * @returns { string }
   */
  getHtml() {
    return this.htmlString
  }
}
