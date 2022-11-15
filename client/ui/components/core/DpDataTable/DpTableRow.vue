<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import DomPurify from 'dompurify'
import { DpIcon } from 'demosplan-ui/components'
import DpWrapTrigger from './DpWrapTrigger'
import { hasOwnProp } from 'demosplan-utils'

export default {
  name: 'DpTableRow',

  functional: true,

  props: {
    checked: {
      type: Boolean,
      required: true
    },

    /**
     * Is the expandable content currently expanded?
     */
    expanded: {
      type: Boolean,
      required: false,
      default: false
    },

    hasFlyout: {
      type: Boolean,
      required: true
    },

    /**
     * The header of every column of the table is defined here.
     *
     * Each column is represented by an object with a `field` key whose value should match
     * a key of the objects inside `items`. The `label` key controls the header of the column.
     * The header can also have a tooltip. To define the width the column is initially
     * rendered with when `isResizable` is used, the key `initialWidth` takes a px value.
     */
    headerFields: {
      type: Array,
      required: true
    },

    fields: {
      type: Array,
      required: true
    },

    item: {
      type: Object,
      required: true
    },

    isDraggable: {
      type: Boolean,
      required: false,
      default: false
    },

    isExpandable: {
      type: Boolean,
      required: false,
      default: false
    },

    isLoading: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * The item can be locked for selection. Instead of the checkbox, a lock icon is rendered with a tooltip.
     */
    isLocked: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * If an item is locked for selection, the message to be shown inside the tooltip can be set here.
     */
    isLockedMessage: {
      type: String,
      required: false,
      default: null
    },

    isResizable: {
      type: Boolean,
      required: false,
      default: false
    },

    isSelectable: {
      type: Boolean,
      required: true
    },

    isSelectableName: {
      type: String,
      required: false,
      default: null
    },

    isTruncatable: {
      type: Boolean,
      required: false,
      default: false
    },

    searchTerm: {
      type: RegExp,
      required: true
    },

    trackBy: {
      type: String,
      required: true
    },

    /**
     * Is the truncatable content currently truncated?
     * Rename to `notTruncated`?
     */
    wrapped: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  render: function (h, { listeners, props, scopedSlots }) {
    const {
      checked,
      expanded,
      fields,
      hasFlyout,
      headerFields,
      isDraggable,
      isExpandable,
      isLoading,
      isLocked,
      isLockedMessage,
      isSelectable,
      isSelectableName,
      isTruncatable,
      item,
      searchTerm,
      trackBy,
      wrapped
    } = props

    let draggableCell = []
    if (isDraggable) {
      draggableCell = [
        h('td', {
          attrs: {
            class: 'c-data-table__cell--narrow'
          }
        }, [
          h(DpIcon, {
            attrs: {
              class: 'c-data-table__drag-handle u-valign--middle'
            },
            props: {
              icon: 'drag-handle'
            }
          })
        ])
      ]
    }

    let checkboxCell = []

    if (isSelectable) {
      let checkboxElement
      let checkboxData
      if (isLocked) {
        checkboxElement = DpIcon
        checkboxData = {
          class: 'u-valign--middle color--grey-light',
          props: { icon: 'lock' },
          directives: [
            {
              name: 'tooltip',
              value: isLockedMessage
            }
          ]
        }
      } else {
        checkboxElement = 'input'
        checkboxData = {
          attrs: {
            type: 'checkbox',
            class: 'u-m-0 u-valign--middle',
            'data-cy': 'selectItem',
            name: isSelectableName || null,
            value: isSelectableName ? item[trackBy] : null
          },
          domProps: { checked: checked },
          on: { click: () => listeners.toggleSelect(item[trackBy]) }
        }
      }

      checkboxCell = [h('td', {
        attrs: { class: 'c-data-table__cell--narrow' }
      }, [h(checkboxElement, checkboxData)])]
    }

    let flyoutCell = []
    if (hasFlyout) {
      flyoutCell = [h('td', {
        attrs: {
          class: 'overflow-visible'
        },
        scopedSlots: {
          flyout: scopedSlots.flyout
        }
      }, [(scopedSlots.flyout && scopedSlots.flyout(item))])]
    }

    let expandableCell = []
    if (isExpandable) {
      expandableCell = [h('td', {
        attrs: {
          class: `c-data-table__cell--narrow ${expanded ? 'is-open' : ''}`,
          title: Translator.trans(expanded ? 'aria.collapse' : 'aria.expand')
        },
        on: {
          click: () => listeners.toggleExpand(item[trackBy])
        }
      }, [h(DpWrapTrigger, {
        props: {
          expanded: expanded
        }
      })])]
    }

    let truncatableCell = []
    if (isTruncatable) {
      truncatableCell = [h('td', {
        attrs: {
          class: `c-data-table__cell--narrow ${wrapped ? 'is-open' : ''}`,
          title: Translator.trans(wrapped ? 'aria.collapse' : 'aria.expand')
        },
        on: {
          click: () => listeners.toggleWrap(item[trackBy])
        }
      }, [h(DpWrapTrigger, {
        props: {
          expanded: wrapped
        }
      })])]
    }

    const rowContent = [
      ...draggableCell,
      ...checkboxCell,
      ...fields.map((field, idx) => {
        let txt = item[field]
        let highlighted = null
        const headerField = headerFields.find((hf) => hf.field === field)
        if (searchTerm && txt) {
          txt = DomPurify.sanitize(txt)
          highlighted = txt.replace(searchTerm, '<span style="background-color: yellow;">$&</span>')
          highlighted = h('span', {
            domProps: {
              innerHTML: highlighted
            }
          })
        }

        let cellAttributes = {}
        let cellInnerElement = null
        let cellInnerElementStyle = ''
        if (!wrapped && typeof headerField.initialWidth !== 'undefined') {
          cellInnerElementStyle = `width: ${headerField.initialWidth}px;`
        }
        if (!wrapped && typeof headerField.initialMaxWidth !== 'undefined') {
          cellInnerElementStyle += `max-width: ${headerField.initialMaxWidth}px;`
        }
        if (!wrapped && typeof headerField.initialMinWidth !== 'undefined') {
          cellInnerElementStyle += `min-width: ${headerField.initialMinWidth}px;`
        }
        if (isTruncatable) {
          cellAttributes = {
            attrs: {
              class: 'c-data-table__resizable',
              'data-col-idx': `${idx}`
            }
          }
          cellInnerElement = h('div', {
            attrs: {
              class: `${wrapped ? 'c-data-table__resizable--wrapped overflow-word-break' : 'c-data-table__resizable--truncated overflow-word-break'}`,
              style: cellInnerElementStyle
            }
          }, [(scopedSlots[field] && scopedSlots[field](item)) || highlighted || txt || ''])
        }

        return h('td',
          {
            ...cellAttributes,
            key: `${field}:${idx}`,
            scopedSlots: {
              [field]: scopedSlots[field]
            }
          },
          [cellInnerElement || (scopedSlots[field] && scopedSlots[field](item)) || highlighted || txt || '']
        )
      }),
      ...flyoutCell,
      ...expandableCell,
      ...truncatableCell
    ]

    const content = [h('tr', {
      attrs: {
        class: `row ${isLoading ? 'opacity-7' : ''} ${expanded ? 'is-expanded-row' : ''}`
      }
    }, rowContent)]

    if (expanded && hasOwnProp(scopedSlots, 'expandedContent')) {
      const expandedContent = scopedSlots.expandedContent(item) || ''
      const expandedRow = h('tr',
        {
          attrs: {
            class: `${isLoading ? 'opacity-7' : ''} ${expanded ? 'is-expanded-content' : ''}`
          },
          on: {
            mouseenter: (e) => e.target.previousSibling.classList.add('is-hovered-content'),
            mouseleave: (e) => e.target.previousSibling.classList.remove('is-hovered-content')
          }
        },
        [h('td', { attrs: { colspan: rowContent.length } }, expandedContent)]
      )

      content.push(expandedRow)
    }

    return content
  }
}
</script>
