<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { DpIcon } from 'demosplan-ui/components'
import DpWrapTrigger from './DpWrapTrigger'
import { hasOwnProp } from 'demosplan-utils'
import { renderResizeWrapper } from './lib/ResizableColumns'

export default {
  name: 'DpTableHeader',

  functional: true,

  props: {
    checked: {
      type: Boolean,
      required: true
    },

    hasFlyout: {
      type: Boolean,
      required: true
    },

    headerFields: {
      type: Array,
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

    isResizable: {
      type: Boolean,
      required: false,
      default: false
    },

    isSelectable: {
      type: Boolean,
      required: true
    },

    isSticky: {
      type: Boolean,
      required: false,
      default: false
    },

    isTruncatable: {
      type: Boolean,
      required: false,
      default: false
    },

    translations: {
      type: Object,
      required: true
    }
  },

  render: function (h, { props, listeners, scopedSlots }) {
    const {
      checked,
      headerFields,
      hasFlyout,
      indeterminate,
      isDraggable,
      isExpandable,
      isResizable,
      isSelectable,
      isSticky,
      isTruncatable,
      translations
    } = props

    let draggableCell = []
    if (isDraggable) {
      draggableCell = [
        h('th', {
          attrs: {
            class: 'c-data-table__cell--narrow'
          }
        }, [
          h(DpIcon, {
            attrs: {
              class: 'c-data-table__drag-handle'
            },
            props: {
              icon: 'drag-handle'
            }
          })
        ])
      ]
    }

    const checkboxData = {}
    let checkboxCell = []
    if (isSelectable) {
      checkboxData.attrs = {
        'aria-label': translations.headerSelectHint,
        title: translations.headerSelectHint,
        type: 'checkbox',
        'data-cy': 'selectAll'
      }
      checkboxData.ref = 'selectAll'
      checkboxData.on = { click: () => listeners.toggleSelectAll() }
      checkboxData.domProps = { checked: checked, indeterminate: indeterminate }
      checkboxCell = [h('th', {
        attrs: {
          class: 'c-data-table__cell--narrow'
        }
      }, [h('input', checkboxData)])]
    }

    let flyoutCell = []
    if (hasFlyout) {
      flyoutCell = [h('th')]
    }

    let expandableCell = []
    if (isExpandable) {
      DpWrapTrigger.attrs = {
        title: translations.headerExpandHint
      }
      expandableCell = [h('th', {
        attrs: {
          class: 'c-data-table__cell--narrow'
        },
        on: {
          click: () => listeners.toggleExpandAll()
        }
      }, [h(DpWrapTrigger)])]
    }

    let truncatableCell = []
    if (isTruncatable) {
      DpWrapTrigger.attrs = {
        title: translations.headerExpandHint
      }
      truncatableCell = [h('th', {
        attrs: {
          class: 'c-data-table__cell--narrow'
        },
        on: {
          click: () => listeners.toggleWrapAll()
        }
      }, [h(DpWrapTrigger)])]
    }

    const headerCells = headerFields.map((hf, idx) => {
      const isLast = idx === (headerFields.length - 1)
      const resizeable = hasOwnProp(hf, 'resizeable') ? hf.resizeable : true
      const headerContent = [(scopedSlots[`header-${hf.field}`] && scopedSlots[`header-${hf.field}`](hf)) || hf.label]
      const content = isResizable ? renderResizeWrapper(h, headerContent, idx, isLast, resizeable, hf.label, hf.tooltip) : headerContent

      return isResizable
        ? content
        : h('th', {
          scopedSlots: {
            [`header-${hf.field}`]: scopedSlots[`header-${hf.field}`]
          }
        }, headerContent)
    })

    return h('tr', {
      ref: 'tableHeader',
      attrs: isSticky
        ? { class: 'c-data-table__sticky-header' }
        : {}
    }, [
      ...draggableCell,
      ...checkboxCell,
      ...headerCells,
      ...flyoutCell,
      ...expandableCell,
      ...truncatableCell
    ])
  }
}
</script>
