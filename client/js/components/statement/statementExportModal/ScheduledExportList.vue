<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div class="flex justify-between items-center pb-4">
      <h3 class="text-sm mb-0">
        {{ Translator.trans('export.xlsx.scheduled.active', { number: String(scheduledExports.length) }) }}
      </h3>
      <dp-button
        data-cy="scheduledExportList:add"
        icon="calendar-blank"
        icon-size="medium"
        :text="Translator.trans('export.xlsx.scheduled.add')"
        variant="outline"
        @click="$emit('add')"
      />
    </div>

    <ul
      v-if="scheduledExports.length"
      class="flex flex-col gap-2 max-h-12 overflow-y-auto">
      <li
        v-for="scheduledExport in scheduledExports"
        :key="scheduledExport.id"
        class="border border-neutral p-2">
        <div class="flex justify-between">
          <dp-loading
            v-if="hasPendingExportAction(scheduledExport.id)"
            class="mx-2"
            hide-label
          />
          <div
            v-else
            class="flex gap-2"
          >
            <dp-icon
              icon="file-xls"
              size="xxlarge"
            />
            <div>
              <span class="font-semibold block">
                {{ Translator.trans('export.xlsx') }}
              </span>
              <span class="text-sm">
                {{ formatScheduledExportDescription(scheduledExport) }}
              </span>
            </div>
          </div>
          <div class="flex">
            <dp-button
              data-cy="scheduledExport:edit"
              icon="edit"
              icon-size="large"
              hide-text
              :disabled="hasPendingExportAction(scheduledExport.id)"
              :text="Translator.trans('export.xlsx.scheduled.edit')"
              variant="transparent"
              @click="handleScheduledExportAction('edit', scheduledExport.id)"
            />
            <dp-button
              class="text-status-failed-icon"
              data-cy="scheduledExport:delete"
              icon="delete"
              icon-size="large"
              hide-text
              :disabled="hasPendingExportAction(scheduledExport.id)"
              :text="Translator.trans('export.xlsx.scheduled.delete')"
              variant="transparent"
              @click="handleScheduledExportAction('delete', scheduledExport.id)"
            />
          </div>
        </div>
      </li>
    </ul>

    <span v-else>
      {{ Translator.trans('export.xlsx.scheduled.empty') }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { DpButton, DpIcon, DpLoading } from '@demos-europe/demosplan-ui'
import { useScheduledExportOptions } from '@DpJs/composables/useScheduledExportOptions'
import type { ScheduledExport } from '@DpJs/types/scheduledExport'
import { ref, watch } from 'vue'

interface Props {
  scheduledExports: ScheduledExport[],
  isLoading: {
    type: Boolean,
    required: false,
    default: false,
  },
}

const props = defineProps<Props>()

const emit = defineEmits<{
  add: []
  edit: [exportId: string]
  delete: [exportId: string]
}>()

const pendingActionExportId = ref(null)

const { getFrequencyLabel, getWeekdayLabel, getDayOfMonthLabel } = useScheduledExportOptions()

watch(() => props.isLoading, (newValue) => {
  if (!newValue && pendingActionExportId.value) {
    // Reset triggered ID when loading completes (success or error)
    pendingActionExportId.value = null
  }
})

const formatScheduledExportDescription = (scheduledExport: ScheduledExport): string => {
  const frequencyLabel = getFrequencyLabel(scheduledExport.attributes.frequency)

  switch (scheduledExport.attributes.frequency) {
    case 'daily':
      return frequencyLabel

    case 'weekly':
      return `${frequencyLabel}, ${getWeekdayLabel(scheduledExport.attributes.weekday)}`

    case 'monthly':
      return Translator.trans('export.xlsx.scheduled.frequency.monthly.everyMonth', { frequency: frequencyLabel, day: getDayOfMonthLabel(scheduledExport.attributes.dayOfMonth) })

    default:
      return frequencyLabel
  }
}

const handleScheduledExportAction = (actionType, scheduledExportId) => {
  pendingActionExportId.value = scheduledExportId
  emit(actionType, scheduledExportId)
}

const hasPendingExportAction = (exportId) =>
  pendingActionExportId.value === exportId
</script>
