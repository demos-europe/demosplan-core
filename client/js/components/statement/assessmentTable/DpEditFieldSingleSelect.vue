<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <!--
        The Component renders an Editable Item-Row with a label-column and a text/selectbox-toggle
        It depends on the dp-edit-field and acts as wrapper for that
    -->
    <usage variant="minimum (required props)">
        <dp-edit-field-single-select
            label="Label Title"
            field-key="stringToIdentifyField"
            entity-id="someId"
            :options="[{'Array of Objects with options': 'requires title'}]"
            :value="{'selected': 'Object'}"
            @field:update="updateMethod"
            @field:save="saveMethod"
        ></dp-edit-field-single-select>
    </usage>
    <usage variant="full">
        <dp-edit-field-single-select
            label="Label Title"
            field-key="stringToIdentifyField"
            entity-id="someId"
            :options="[{'Array of Objects with options': 'requires title'}]"
            :value="{'selected': 'Object'}"
            @field:update="updateMethod"
            @field:save="saveMethod"
            :label-grid-cols="4"
            ref="stringToIdentifyField"
            :editable="true"
            :readonly="false"
        ></dp-edit-field-single-select>
    </usage>
</documentation>

<template>
  <dp-edit-field
    :editable="editable"
    :label="label"
    :label-grid-cols="labelGridCols"
    :readonly="readonly"
    @save="save"
    @reset="reset"
    @toggleEditing="isEditing => $emit('toggleEditing', isEditing)">
    <template v-slot:display>
      <div class="break-words">
        {{ selected.title }}
        <span v-if="'' === selected || 'undefined' === typeof selected">-</span>
      </div>
    </template>
    <template v-slot:edit>
      <dp-multiselect
        :id="`${entityId}:${fieldKey}`"
        v-model="selected"
        :allow-empty="false"
        class="u-n-ml-0_25"
        data-cy="multiSelectElement"
        label="title"
        :name="`${entityId}:${fieldKey}`"
        :options="options"
        track-by="id"
        @input="val => $emit('field:input', val)">
        <template v-slot:option="{ props }">
          {{ props.option.title }}
        </template>
      </dp-multiselect>
    </template>
  </dp-edit-field>
</template>

<script>
import { DpMultiselect, hasOwnProp } from '@demos-europe/demosplan-ui'
import DpEditField from './DpEditField'

export default {
  name: 'DpEditFieldSingleSelect',

  components: {
    DpMultiselect,
    DpEditField
  },

  props: {
    entityId: {
      required: true,
      type: String
    },

    fieldKey: {
      required: true,
      type: String
    },

    editable: {
      required: false,
      type: Boolean,
      default: true
    },

    label: {
      required: true,
      type: String
    },

    options: {
      required: true,
      type: Array
    },

    value: {
      required: false,
      type: [Object, String],
      default: () => { return {} }
    },

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
      selected: '',
      selectedBefore: ''
    }
  },

  watch: {
    value: {
      handler () {
        this.updateSelectedValue()
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    }
  },

  methods: {
    emitData () {
      const emitData = {
        id: this.entityId
      }
      emitData[this.fieldKey] = this.selected.id
      return emitData
    },

    reset () {
      const editFieldComponent = this.$children.find(child => child.$options.name === 'DpEditField')
      editFieldComponent.$data.loading = false
      editFieldComponent.$data.editingEnabled = false
      this.$emit('toggleEditing', false)
      this.selected = this.selectedBefore
    },

    save () {
      if (JSON.stringify(this.selectedBefore) !== JSON.stringify(this.selected)) {
        this.$emit('field:save', this.emitData())
        //  Set new "previous state"-Data
        this.selectedBefore = this.selected
      } else {
        this.reset()
      }
    },

    setInitialValues () {
      this.updateSelectedValue()
      this.selectedBefore = this.selected
    },

    updateSelectedValue () {
      // First check if value is an object, if not - create and object from string
      if (typeof this.value === 'string' && this.value !== '') {
        const objectValue = this.options.find((option) => option.id === this.value)
        if (objectValue) {
          this.selected = objectValue
        } else {
          this.selected = ''
        }
      } else if (this.value !== null && typeof this.value === 'object' && hasOwnProp(this.value, 'title') && hasOwnProp(this.value, 'id')) {
        this.selected = this.value
      } else {
        this.selected = ''
      }
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
