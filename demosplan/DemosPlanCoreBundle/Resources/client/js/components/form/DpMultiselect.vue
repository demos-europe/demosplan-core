<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
   This is the generic multiselect functional component for vue instances. It is a DPlan wrapper for vue-multiselect: https://github.com/shentao/vue-multiselect
   Component props:
      - options - Array of strings/objects
      - v-model - attribute to bind the component's value
      - track-by - for the options that are of type Object
      - searchable="false" – disables the search functionality (by default the search is enabled)
      - close-on-select="false" – the dropdown stays open after selecting an option
      - clear-on-select="false" – the search query stays the same after selecting an option
      - multiple – for multiselect (by default it is single select)
      - allow-empty - allows the value to be deselected (VORSICHT - the component's value is then NULL so any other computed props or functions using the value may fail)
      - label - what should be shown in dropdown (for example if the option is an object with id and name we can define, that 'name' should be a label here), customLabel is a function that has to return a string

      Groups in select: The options list can also contain groups. It requires passing 3 additional props: group-label, group-values and group-select. group-label is used to locate the group label. group-values should point to the group’s option list. group-select is used to define if selecting the group label should select/unselect all values in the group, or do nothing. Despite that the available options are grouped, the selected options are stored as a flat array of objects.

      For all possible props see: https://vue-multiselect.js.org/#sub-props

      The styling for the component can be found in _multiselect.scss

   -->
  <usage
    :options="optionsArray"
    track-by="id"
    ref="multiselect"
    id=""
    :custom-label="option =>`${option.id} ${option.name ? option.name : ''}`"
    class="custom styling classes"
    :allow-empty="false"
    v-model="selected"
    @input="$emit('whatever you want to emit')">
    <template v-slot:option="{ option }">
      <strong>{{ option.id }}{{ option.name ? ` ${option.name}` : '' }}</strong>
    </template>
  </usage>
</documentation>

<script>
import VueMultiselect from 'vue-multiselect'

export default {
  name: 'DpMultiselect',

  functional: true,

  props: {
    required: {
      required: false,
      type: Boolean,
      default: false
    },

    selectionControls: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  render: function (createElement, context) {
    const defaults = {
      placeholder: Translator.trans('choose'),
      selectLabel: '',
      selectGroupLabel: '',
      selectedLabel: '',
      deselectLabel: '',
      deselectGroupLabel: '',
      tagPlaceholder: Translator.trans('tag.create')
    }
    context.data.attrs = { ...defaults, ...context.data.attrs }
    if (context.props.required) {
      context.data.directives = [
        {
          name: 'dp-validate-multiselect'
        }
      ]
      context.data.staticClass = context.data.staticClass ? context.data.staticClass + ' is-required' : 'is-required'
    }

    const functions = {
      noResult: () => createElement('span', Translator.trans('autocomplete.noResults')),
      noOptions: () => createElement('span', Translator.trans('explanation.noentries'))
    }

    if (context.props.selectionControls) {
      const menuOptions = context.data.attrs.options
      const menuSelectedValue = context.data.model.value

      // Add disabled attribute for selection Controls Buttons when click.
      const buttonSelectAll = document.querySelector('[data-select-all]')
      const buttonUnselectAll = document.querySelector('[data-unselect-all]')
      if (buttonSelectAll) {
        if (menuSelectedValue.length === menuOptions.length) {
          buttonSelectAll.setAttribute('disabled', 'disabled')
        } else {
          buttonSelectAll.removeAttribute('disabled')
        }
      }

      if (buttonUnselectAll) {
        if (menuSelectedValue.length === 0) {
          buttonUnselectAll.setAttribute('disabled', 'disabled')
        } else {
          buttonUnselectAll.removeAttribute('disabled')
        }
      }

      functions.beforeList = () => {
        return createElement('div', {
          attrs: {
            class: 'border--bottom'
          }
        }, [
          createElement(
            'button',
            {
              domProps: {
                innerHTML: Translator.trans('select.all')
              },
              attrs: {
                class: 'btn--blank weight--bold u-ph-0_5 u-pv-0_25',
                type: 'button',
                'data-select-all': ''
              },
              on: {
                click: context.listeners['select-all']
              }
            }
          ),
          createElement(
            'button',
            {
              domProps: {
                innerHTML: Translator.trans('unselect.all')
              },
              attrs: {
                class: 'btn--blank weight--bold u-ph-0_5 u-pv-0_25',
                type: 'button',
                'data-unselect-all': ''
              },
              on: {
                click: context.listeners['unselect-all']
              }
            }
          )
        ])
      }
    }

    context.data.scopedSlots = { ...context.data.scopedSlots, ...functions }

    return createElement(VueMultiselect, context.data, context.children)
  }
}
</script>
