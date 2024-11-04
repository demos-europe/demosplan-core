/**
 * This ProseMirrorConverter class is responsible for converting
 * a data-structure in the prosemirror format to an HTML string and vice versa.
 * For more Information about the prosemirror format, see: https://prosemirror.net/docs/
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
      const draftSegments = relationships.draftSegments.data.map(segment => segment.id).join(', ')
      const statementId = relationships.statement.data.id

      // TODO: create valid html string
      this.htmlString = `
        <custom-html-tag>
          ${relationships.draftSegments.data.map(segment => `
          <custom-content
            type="${type}"
            id="${segment.id}"
            draft-segments="[${draftSegments}]"
            statement-id="${statementId}">
          </custom-content>`).join('')}
        </custom-html-tag>`
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

      const customContents = doc.querySelectorAll('custom-content')
      const draftSegments = []
      const included = []

      customContents.forEach(customContent => {
        const id = customContent.getAttribute('id')
        const segmentText = customContent.innerHTML
        draftSegments.push({ id, type: 'DraftSegment' })

        included.push({
          type: 'DraftSegment',
          id,
          attributes: {
            position: {
              start: 0, // TODO: calculate start and stop positions
              stop: segmentText.length
            },
            segment_text: segmentText
          },
          relationships: {
            tags: {
              data: [] // TODO: add tags
            }
          }
        });
      });

      const type = customContents[0]?.getAttribute('type') || 'SegmentedStatement'
      const statementId = customContents[0]?.getAttribute('statement-id') || null

      this.prosemirrorData = {
        data: {
          type,
          id: customContents[0]?.getAttribute('id') || null,
          relationships: {
            draftSegments: {
              data: draftSegments
            },
            statement: {
              data: { type: 'Statement', id: statementId }
            }
          }
        },
        included
      };
      return this
    } catch (error) {
      console.error('Error converting HTML to ProseMirror data: ', error)
    }
  }

  /**
   * Returns a data-structure in the prosemirror format
   * @returns { object }
   */
  toProseMirror() {
    console.log(this.prosemirrorData)
    return this.prosemirrorData
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
