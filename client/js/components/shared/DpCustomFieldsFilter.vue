<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
    Renders custom field filters in the modal variant (label + DpMultiselect rows).
    Counts next to each option come from the parent via fieldOptionCounts — no data
    fetching happens here. The parent (DpFilterModal) sources counts from the same
    Vuex filter options endpoint that powers all other filter counts.

    v-model shape: { [fieldId]: string[] }  (selected option IDs keyed by field ID)
  -->
</documentation>

<template>
  <template v-if="variant === 'modal'">
    <div
      v-for="field in filterableFields"
      :key="field.id"
    >
      <div class="layout__item u-1-of-3">
        <label
          :for="`custom-field-${field.id}`"
          class="block text-right u-pr"
        >
          {{ field.attributes.name }}
        </label>
      </div>
      <div class="layout__item u-2-of-3">
        <dp-multiselect
          :id="`custom-field-${field.id}`"
          :multiple="field.attributes.fieldType === 'multiSelect'"
          :options="fieldOptions(field)"
          :placeholder="Translator.trans('choose')"
          :value="selectedForField(field.id)"
          label="label"
          track-by="value"
          @input="(val) => handleModalChange(field.id, val)"
        >
          <template v-slot:option="{ props }">
            {{ props.option.label }} ({{ props.option.count }})
          </template>
        </dp-multiselect>
      </div>
    </div>
  </template>
</template>

<script>
import { DpMultiselect } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpCustomFieldsFilter',

  components: {
    DpMultiselect,
  },

  props: {
    customFieldDefinitions: {
      type: Array,
      default: () => [],
    },

    variant: {
      type: String,
      default: 'modal',
      validator: (v) => ['modal'].includes(v),
    },

    /**
     * Statement counts per option, keyed by fieldId then optionId.
     * Shape: { [fieldId]: { [optionId]: count } }
     * Sourced by DpFilterModal from Vuex customFieldOptionCounts getter.
     */
    fieldOptionCounts: {
      type: Object,
      default: () => ({}),
    },

    value: {
      type: Object,
      default: () => ({}),
    },
  },

  emits: ['input'],

  computed: {
    filterableFields () {
      return this.customFieldDefinitions.filter(f =>
        ['singleSelect', 'multiSelect'].includes(f.attributes?.fieldType),
      )
    },
  },

  methods: {
    fieldOptions (field) {
      return (field.attributes?.options ?? []).map(o => ({
        label: o.label,
        value: o.id,
        count: this.fieldOptionCounts[field.id]?.[o.id] ?? 0,
      }))
    },

    selectedForField (fieldId) {
      const ids = this.value[fieldId] ?? []
      const field = this.customFieldDefinitions.find(f => f.id === fieldId)

      if (!field) {
        return null
      }

      const matched = this.fieldOptions(field).filter(o => ids.includes(o.value))

      return field.attributes.fieldType === 'multiSelect' ? matched : (matched[0] ?? null)
    },

    handleModalChange (fieldId, selected) {
      const ids = Array.isArray(selected) ?
        selected.map(o => o.value) :
        (selected ? [selected.value] : [])

      this.$emit('input', { ...this.value, [fieldId]: ids })
    },
  },
}
</script>
