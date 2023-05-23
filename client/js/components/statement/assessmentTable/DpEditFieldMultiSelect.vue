<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-edit-field
    :editable="editable"
    :label="label"
    :label-grid-cols="labelGridCols"
    @save="save"
    @toggleEditing="isEditing => $emit('toggleEditing', isEditing)"
    @reset="reset">
    <template v-slot:display>
      <div>
        <ul
          class="o-list o-list--csv"
          v-if="0 < selected.length">
          <li
            class="o-list__item o-hellip max-width-100p"
            v-for="item in selected"
            :key="item.id"
            v-text="item.name" />
        </ul>
        <span v-if="0 === selected.length || 'undefined' === typeof selected">-</span>
      </div>
    </template>
    <template v-slot:edit>
      <dp-multiselect
        multiple
        :options="options"
        :name="`${entityId}:${fieldKey}`"
        :id="`${entityId}:${fieldKey}`"
        track-by="id"
        class="u-n-ml-0_25"
        v-model="selected"
        label="name"
        @input="val => handleInput(val)"
        :group-values="groupValues"
        :group-label="groupLabel"
        :group-select="groupSelect">
        <template v-slot:option="{ option }">
          <strong v-if="option.$isLabel">{{ option.$groupLabel }}</strong>
          <span v-else>{{ option.name }}</span>
        </template>
        <template v-slot:tag="props">
          <span class="multiselect__tag">
            {{ props.option.name }}
            <i
              aria-hidden="true"
              @click="props.remove(props.option)"
              tabindex="1"
              class="multiselect__tag-icon" />
          </span>
        </template>
      </dp-multiselect>
    </template>
  </dp-edit-field>
</template>

<script>
import DpEditField from './DpEditField'
import { DpMultiselect } from '@demos-europe/demosplan-ui'

export default {

  name: 'DpEditFieldMultiSelect',

  components: {
    DpEditField,
    DpMultiselect
  },

  props: {
    //  Used by Mutations/Actions to identify item
    entityId: {
      required: true,
      type: String
    },

    /*
     *  The key of the field to be updated, as found in the entity (eg. `elementId`)
     *  Mutations/Actions will use it to update/save state
     */
    fieldKey: {
      required: true,
      type: String
    },

    //  Is there the overall possibility to edit the item?
    editable: {
      required: false,
      type: Boolean,
      default: true
    },

    //  Sets the label and some titles on buttons
    label: {
      required: true,
      type: String
    },

    //  Array of objects with keys `id` and `title`
    options: {
      required: true,
      type: Array
    },

    //  The initial value is passed here
    value: {
      required: false,
      type: Array,
      default: () => []
    },

    groupValues: {
      required: false,
      type: String,
      default: ''
    },

    groupLabel: {
      required: false,
      type: String,
      default: ''
    },

    groupSelect: {
      required: false,
      type: Boolean,
      default: false
    },

    isGroupSelect: {
      required: false,
      type: Boolean,
      default: false
    },

    // Grid-size for label -> input = 12 -label
    labelGridCols: {
      required: false,
      type: Number,
      default: 2
    },

    readonly: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  data () {
    return {
      //  Current value
      selected: [],

      //  Previously selected value to be able to restore it on reset
      selectedBefore: []
    }
  },

  watch: {
    value (newValue) {
      this.setInitialValues()
    }
  },

  methods: {
    //  Here, the data emitted on update/save is set.
    emitData () {
      const emitData = {
        id: this.entityId
      }
      emitData[this.fieldKey] = this.selected.map(item => item.id)
      return emitData
    },

    handleInput (val) {
      this.sortSelected()
      this.$emit('field:input', val)
    },

    reset () {
      const editFieldComponent = this.$children.find(child => child.$options.name === 'DpEditField')
      editFieldComponent.$data.loading = false
      editFieldComponent.$data.editingEnabled = false
      this.$emit('toggleEditing', false)
      this.selected = this.selectedBefore
    },

    setInitialValues () {
      let optionsToSearch = this.options
      if (this.isGroupSelect) {
        optionsToSearch = this.options.reduce((acc, optionGroup) => {
          if (optionGroup[this.groupValues]) {
            acc = [...acc, ...optionGroup[this.groupValues]]
          }
          return acc
        }, [])
      }

      this.selected = this.value.map(el => optionsToSearch.find(opt => opt.id === el.id))
      this.selectedBefore = this.selected
    },

    save () {
      if (this.selectedBefore.length !== this.selected.length || this.selected.some(selected => (this.selectedBefore.find(selectedBefore => selected.id === selectedBefore.id)) === undefined)) {
        this.$emit('field:save', this.emitData())
        //  Set new "previous state"-Data
        this.selectedBefore = this.selected
      } else {
        this.reset()
      }
    },

    sortSelected () {
      this.selected.sort((a, b) => (a.name > b.name) ? 1 : ((b.name > a.name) ? -1 : 0))
    }
  },

  created () {
    // This.function has to be called if assessmentBase loads because of the ajax call, but also on mounted because of the paragraph inline edit
    this.setInitialValues()
  },

  mounted () {
    this.$root.$on('reset', () => this.reset())
  }
}
</script>
