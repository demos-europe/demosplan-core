<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <dp-button
      data-cy="exportModal:open"
      :disabled="isExportDisabled"
      :text="Translator.trans('export.verb')"
      icon="download"
      icon-size="medium"
      variant="subtle"
      @click="openModal"
    />

    <dp-modal
      ref="exportModal"
      content-classes="w-11/12 sm:w-9/12 md:w-7/12 lg:w-6/12 xl:w-5/12 h-fit"
    >
      <template v-slot:header>
        <h2>{{ Translator.trans('export.segments') }}</h2>
      </template>

      <fieldset class="pb-0">
        <legend
          class="text-base pb-4"
          v-text="Translator.trans('export.type')"
        />
        <div class="flex flex-row gap-2">
          <dp-radio
            v-for="(exportType, key) in exportTypes"
            :id="key"
            :key="key"
            class="bg-neutral-light-4 border-l-4 border-interactive rounded-sm p-2"
            :class="{ 'border-transparent bg-transparent' : active !== key }"
            :data-cy="`exportModal:exportType:${key}`"
            :label="{
              text: Translator.trans(exportType.label),
            }"
            :value="key"
            :checked="active === key"
            @change="active = key"
          />
        </div>

        <dp-inline-notification
          v-if="exportTypes[active].hint"
          class="mt-4"
          :message="exportTypes[active].hint"
          type="warning"
        />

        <div
          v-if="hasAppliedFiltersOrSearch"
          class="pt-4"
          data-cy="exportModal:appliedFilters"
        >
          <p class="font-semibold">
            {{ Translator.trans('export.segments.filter.applied') }}
          </p>
          <div class="bg-neutral-light-4 rounded-lg p-1.5 mt-1.5">
            <dl class="description-list-inline w-full">
              <template
                v-for="filter in appliedFilters"
                :key="filter.label"
              >
                <dt>{{ filter.label }}:</dt>
                <dd>{{ filter.values.join(', ') }}</dd>
              </template>
              <template v-if="searchTerm !== ''">
                <dt>{{ Translator.trans('search') }}:</dt>
                <dd>{{ searchTerm }}</dd>
              </template>
            </dl>
          </div>
        </div>

        <p class="text-base pt-4 mb-0">
          {{ Translator.trans('export.segments.column.hint') }}
        </p>
      </fieldset>

      <template v-slot:footer>
        <dp-button-row
          class="text-right mt-auto"
          data-cy="exportModal"
          primary
          secondary
          :primary-text="confirmButtonText"
          :secondary-text="Translator.trans('abort')"
          @primary-action="handleExport"
          @secondary-action="closeModal"
        />
      </template>
    </dp-modal>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { DpButton, DpButtonRow, DpInlineNotification, DpModal, DpRadio, hasPermission } from '@demos-europe/demosplan-ui'

const props = defineProps({
  // Shaped [{ label, values: [String] }] — built by SegmentsList
  appliedFilters: {
    type: Array,
    required: false,
    default: () => [],
  },

  isExportDisabled: {
    type: Boolean,
    required: false,
    default: false,
  },

  searchTerm: {
    type: String,
    required: false,
    default: '',
  },

  segmentCount: {
    type: Number,
    required: false,
    default: 0,
  },
})

const emit = defineEmits(['open', 'export'])

const confirmButtonText = computed(() =>
  Translator.trans('export.segments.count', { count: props.segmentCount }),
)

const hasAppliedFiltersOrSearch = computed(() =>
  props.appliedFilters.length > 0 || props.searchTerm !== '',
)

const exportTypes = {}

if (hasPermission('feature_segments_list_export_xlsx')) {
  exportTypes.xlsx_normal = {
    label: 'export.xlsx',
    hint: Translator.trans('export.xlsx.hint'),
  }
}

if (hasPermission('feature_segments_list_export_csv')) {
  exportTypes.csv_normal = {
    label: 'export.csv',
    hint: Translator.trans('export.csv.hint'),
  }
}

const active = ref(Object.keys(exportTypes)[0])
const exportModal = ref(null)

const openModal = () => {
  active.value = Object.keys(exportTypes)[0]
  emit('open')
  exportModal.value?.toggle()
}

const closeModal = () => {
  exportModal.value?.toggle()
}

const handleExport = () => {
  emit('export', { type: active.value })
  closeModal()
}
</script>
