<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
    Renders a label + DpMultiselect for a single/multi-select custom field filter definition.
    Manages its own sort state internally. Counts next to each option come from the parent via
    the options prop - no data fetching happens here. The parent (DpFilterModal) sources counts
    from the same Vuex filter options endpoint that powers all other filter counts, refreshed on
    the open/close events emitted by this component.
  -->
</documentation>

<template>
  <dp-label
    class="layout__item u-1-of-3 pl-0 text-right"
    style="display: inline-block"
    :for="`filter-item-${filterDefinition.id}`"
    :text="filterDefinition.name"
  /><!--
 --><div class="layout__item u-2-of-3">
    <dp-multiselect
      :id="`filter-item-${filterDefinition.id}`"
      :close-on-select="false"
      label="label"
      :multiple="filterDefinition.fieldType === 'multiSelect'"
      :options="sortedOptions"
      selection-controls
      track-by="value"
      :value="value"
      @close="emit('close')"
      @deselect-all="emit('input', null)"
      @input="val => emit('input', val)"
      @open="emit('open')"
      @select-all="val => emit('input', val)"
    >
      <template v-slot:beforeList>
        <li>
          <button
            class="btn--blank o-link--default"
            type="button"
            @click="toggleSort"
          >
            <i
              aria-hidden="true"
              class="fa pr-1"
              :class="sortingType === 'alphabetic' ? 'fa-sort-numeric-desc' : 'fa-sort-alpha-asc'"
            />
            {{ sortingType === 'alphabetic' ? Translator.trans('sort.count.desc') : Translator.trans('sort.alphabet.asc') }}
          </button>
        </li>
      </template>
      <template v-slot:option="{ props: optionProps }">
        {{ optionProps.option.label }}
        <template v-if="optionProps.option.count !== undefined">
          ({{ optionProps.option.count }})
        </template>
      </template>
      <template v-slot:tag="{ props: tagProps }">
        <span class="multiselect__tag">
          <span>
            {{ tagProps.option.label }}
            <template v-if="tagProps.option.count !== undefined">
              ({{ tagProps.option.count }})
            </template>
          </span>
          <button
            :aria-label="`${Translator.trans('remove')}: ${tagProps.option.label}`"
            class="multiselect__tag-icon"
            type="button"
            @click="tagProps.remove(tagProps.option)"
          />
        </span>
      </template>
    </dp-multiselect>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType, ref } from 'vue'
import type { FilterDefinition, SelectOption } from '@DpJs/types/filters'
import { DpLabel, DpMultiselect } from '@demos-europe/demosplan-ui'

const props = defineProps({
  filterDefinition: {
    type: Object as PropType<FilterDefinition>,
    required: true,
  },

  options: {
    type: Array as PropType<SelectOption[]>,
    required: false,
    default: () => [],
  },

  value: {
    type: [Object, Array, null] as PropType<SelectOption | SelectOption[] | null>,
    required: false,
    default: null,
  },
})

const emit = defineEmits<{
  close: []
  input: [value: SelectOption | SelectOption[] | null]
  open: []
}>()

const sortingType = ref<'alphabetic' | 'count'>('alphabetic')

const toggleSort = (): void => {
  sortingType.value = sortingType.value === 'alphabetic' ? 'count' : 'alphabetic'
}

const sortedOptions = computed((): SelectOption[] => {
  const optionsCopy = [...props.options]

  return sortingType.value === 'count' ?
    optionsCopy.sort((a, b) => (b.count ?? 0) - (a.count ?? 0)) :
    optionsCopy.sort((a, b) => a.label.localeCompare(b.label))
})
</script>
