<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
    Renders custom field filters in two visual variants:

    'modal'  → label + DpMultiselect rows for embedding inside DpFilterModal tabs.
               Uses v-model ({ [fieldId]: string[] }) and renders hidden form inputs
               so selections are submitted with the filter form.

    'flyout' → FilterFlyout buttons for embedding in the SegmentsList filter bar.
               Re-emits @filter-apply with the same condition-map format as FilterFlyout,
               so the parent can wire it directly to sendFilterQuery.

    The parent is responsible for fetching customFieldDefinitions (e.g. via
    useCustomFieldDefinitions / useCustomFields) and passing them as a prop.
    Only Select and MultiSelect field types are rendered — Text fields are skipped.
  -->
</documentation>

<template>
  <!-- Modal row variant: matches DpFilterModalSelectItem layout inside DpFilterModal. Selections are tracked in customFieldFilterValue (DpFilterModal data) and passed explicitly to updateFilterHash in submitWithSave — no hidden form inputs needed. -->
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
        />
      </div>
    </div>
  </template>

  <!-- Flyout variant: matches FilterFlyout button row in SegmentsList -->
  <template v-else-if="variant === 'flyout'">
    <filter-flyout
      v-for="field in filterableFields"
      :key="field.id"
      :category="{ id: field.id, label: field.attributes.name }"
      class="inline-block"
      :operator="'CUSTOM_FIELD_CONTAINS'"
      :path="'customFields'"
      @filter-apply="(conditions) => $emit('filter-apply', conditions)"
      @filterOptions:request="seedFlyoutOptions(field)"
    />
  </template>
</template>

<script>
import { DpMultiselect } from '@demos-europe/demosplan-ui'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout'

export default {
  name: 'DpCustomFieldsFilter',

  components: {
    DpMultiselect,
    FilterFlyout,
  },

  props: {
    /**
     * Definitions fetched by the parent via useCustomFieldDefinitions / useCustomFields.
     * Only Select and MultiSelect entries produce rendered filters.
     */
    customFieldDefinitions: {
      type: Array,
      default: () => [],
    },

    /**
     * 'modal'  → label + DpMultiselect rows (for DpFilterModal)
     * 'flyout' → FilterFlyout buttons (for SegmentsList)
     */
    variant: {
      type: String,
      default: 'modal',
      validator: (v) => ['modal', 'flyout'].includes(v),
    },

    /**
     * Selected state — only used in 'modal' variant.
     * Shape: { [fieldId]: string[] }  (selected option IDs)
     */
    value: {
      type: Object,
      default: () => ({}),
    },
  },

  emits: [
    /** modal variant: emits { [fieldId]: string[] } */
    'input',
    /** flyout variant: emits the FilterFlyout condition-map ({ [optionId]: { condition } }) */
    'filter-apply',
  ],

  computed: {
    filterableFields () {
      return this.customFieldDefinitions.filter(f =>
        ['singleSelect', 'multiSelect'].includes(f.attributes?.fieldType)
      )
    },
  },

  methods: {
    // ── Shared ─────────────────────────────────────────────────────────────

    fieldOptions (field) {
      return (field.attributes?.options ?? []).map(o => ({ label: o.label, value: o.id }))
    },

    // ── Modal variant ───────────────────────────────────────────────────────

    selectedForField (fieldId) {
      const ids = this.value[fieldId] ?? []
      const field = this.customFieldDefinitions.find(f => f.id === fieldId)

      if (!field) {
        return field?.attributes?.fieldType === 'multiSelect' ? [] : null
      }

      const matched = this.fieldOptions(field).filter(o => ids.includes(o.value))

      return field.attributes.fieldType === 'multiSelect' ? matched : (matched[0] ?? null)
    },

    handleModalChange (fieldId, selected) {
      const ids = Array.isArray(selected)
        ? selected.map(o => o.value)
        : (selected ? [selected.value] : [])
      this.$emit('input', { ...this.value, [fieldId]: ids })
    },

    // ── Flyout variant ──────────────────────────────────────────────────────

    seedFlyoutOptions (field) {
      const options = (field.attributes?.options ?? []).map(o => ({ id: `${field.id}__${o.id}`, label: o.label, selected: false }))
      this.$store.commit('FilterFlyout/setUngroupedOptions', { categoryId: field.id, options })
      this.$store.commit('FilterFlyout/setIsLoading', { categoryId: field.id, isLoading: false })
    },
  },
}
</script>
