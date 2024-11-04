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

      // TODO: create valid html string
      this.htmlString = `
        <dp-statement :statement-id="${id}">
          ${relationships.draftSegments.data.map(segment => `
          <dp-segment
            :type="${type}"
            :id="${segment.id}">
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
    console.log(this.htmlString)
    return this.htmlString
  }
}

export const normalizeHtmlString = (htmlString) => {
  htmlString.replace(/\s+/g, '')
}
