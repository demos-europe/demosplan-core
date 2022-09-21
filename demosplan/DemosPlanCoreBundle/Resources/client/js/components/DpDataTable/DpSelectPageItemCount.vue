<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
export default {
  name: 'DpSelectPageItemCount',

  functional: true,

  props: {
    currentItemCount: {
      type: Number,
      required: true
    },

    pageCountOptions: {
      type: Array,
      required: true
    },

    translations: {
      type: Object,
      required: true
    }
  },

  render: function (h, { props, listeners, data }) {
    const selectEl = h('select', {
      attrs: {
        id: 'item-count',
        class: 'o-form__control-select width-auto u-mr-0_25'
      },
      on: {
        change: (e) => {
          listeners['changed-count'](parseInt(e.target.value))
        }
      }
    }, [
      ...props.pageCountOptions.map(option => {
        return h('option', {
          domProps: {
            value: option,
            selected: option === props.currentItemCount
          }
        }, option)
      })
    ])

    const selectHintEl = h('label', {
      attrs: {
        for: 'item-count',
        class: 'display--inline u-mb-0'
      }
    },
    props.translations.pagerElementsPerPage)

    return h('div', {
      attrs: {
        class: data.staticClass
      }
    }, [selectEl, selectHintEl])
  }
}
</script>
