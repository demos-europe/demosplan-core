/**
 * This ProseMirrorConverter class is responsible for converting
 * a data-structure in the prosemirror format to an HTML string.
 * For more Information about the prosemirror format, see: https://prosemirror.net/docs/
 *
 * Example usage:
 * - from ProseMirror to html: `converter.fromProseMirror(ProseMirrorData).toHtml().getHtml()`
 *
 * @param proseMirrorData - object
 * @param htmlString - string
 */
export class ProseMirrorConverter {
  constructor() {
    this.htmlString = null
    this.parser = new DOMParser()
    this.prosemirrorData = null
  }

  /**
   * Returns a ProseMirrorConverter instance filled with a data-structure in the prosemirror format.
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
      const { included } = this.prosemirrorData

      // TODO: create valid html string
      this.htmlString = `
        <dp-statement :statement-id="${id}">
          ${relationships.draftSegments.data.map(segment => `
          <dp-segment
            :type="${type}"
            :id="${segment.id}">
            ${included.filter(el => el.id === segment.id).map(el => el.attributes.segment_text)}
          </dp-segment>`).join('')}
        </dp-statement>`.trim()
      return this
    } catch (error) {
      console.error('Error converting ProseMirror data to HTML: ', error)
    }
  }

  /**
   * Returns an HTML string.
   * @returns { string }
   */
  getHtml() {
    return this.htmlString
  }
}

export const normalizeHtmlString = (htmlString) => {
  htmlString.replace(/\s+/g, '')
}
