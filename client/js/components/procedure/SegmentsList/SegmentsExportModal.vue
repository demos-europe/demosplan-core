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
      icon="export"
      icon-size="medium"
      variant="subtle"
      :text="Translator.trans('export.verb')"
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
          v-if="hasAppliedFilters"
          class="pt-4"
          data-cy="exportModal:appliedFilters"
        >
          <p class="font-semibold">
            {{ Translator.trans('export.segments.filter.applied') }}
          </p>
          <div class="bg-neutral-light-4 rounded-lg p-1.5 mt-1.5 flex flex-col gap-1.5">
            <div
              v-for="filter in appliedFilters"
              :key="filter.label"
            >
              <span class="font-semibold">{{ filter.label }}:</span>
              {{ filter.values.join(', ') }}
            </div>
            <div v-if="searchTerm !== ''">
              <span class="font-semibold">{{ Translator.trans('search') }}:</span>
              {{ searchTerm }}
            </div>
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
          :primary-text="Translator.trans('export.segments')"
          :secondary-text="Translator.trans('abort')"
        />
      </template>
    </dp-modal>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { DpButton, DpButtonRow, DpInlineNotification, DpModal, DpRadio } from '@demos-europe/demosplan-ui'

const props = defineProps({
  // Shaped [{ label, values: [String] }] — built by SegmentsList
  appliedFilters: {
    type: Array,
    required: false,
    default: () => [],
  },

  searchTerm: {
    type: String,
    required: false,
    default: '',
  },
})

const hasAppliedFilters = computed(() =>
  props.appliedFilters.length > 0 || props.searchTerm !== '',
)

const active = ref('xlsx_normal')
const exportModal = ref(null)

const exportTypes = {
  xlsx_normal: {
    label: 'export.xlsx',
    hint: Translator.trans('export.xlsx.hint'),
  },
  csv_normal: {
    label: 'export.csv',
    hint: Translator.trans('export.csv.hint'),
  },
}

const openModal = () => {
  exportModal.value?.toggle()
}
</script>
