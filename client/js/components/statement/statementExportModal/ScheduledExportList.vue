<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div class="flex justify-between items-center pb-4">
      <span class="text-sm inline-block">
        {{ Translator.trans('export.xlsx.scheduled.active', { number: String(scheduledExports.length) }) }}
      </span>
      <dp-button
        data-cy="scheduledExportList:add"
        icon="calendar-blank"
        icon-size="medium"
        :text="Translator.trans('export.xlsx.scheduled.add')"
        variant="outline"
        @click="$emit('add')"
      />
    </div>

    <div class="flex flex-col gap-2 max-h-12 overflow-y-auto">
      <span v-if="!scheduledExports.length">
        {{ Translator.trans('export.xlsx.scheduled.empty') }}
      </span>
      <div
        v-else
        v-for="scheduledExport in scheduledExports"
        :key="scheduledExport.id"
        class="border border-neutral p-2">
        <div class="flex flex-row justify-between">
          <div class="flex gap-2">
            <dp-icon
              icon="file"
              size="xlarge"
            />
            <div>
              <span class="font-semibold block">XLSX-Datei</span>
              <span class="text-sm">{{ formatScheduledExportDescription(scheduledExport) }}</span>
            </div>
          </div>
          <div class="flex justify-between">
            <dp-button
              data-cy="scheduledExport:edit"
              icon="edit"
              icon-size="large"
              hide-text
              :text="Translator.trans('export.xlsx.scheduled.edit')"
              variant="transparent"
              @click="$emit('edit', scheduledExport.id)"
            />
            <dp-button
              data-cy="scheduledExport:delete"
              icon="delete"
              icon-size="large"
              hide-text
              :text="Translator.trans('export.xlsx.scheduled.delete')"
              variant="transparent"
              @click="$emit('delete', scheduledExport.id)"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { DpButton, DpIcon } from '@demos-europe/demosplan-ui'
import { useScheduledExportOptions } from '@DpJs/composables/useScheduledExportOptions'

interface ScheduledExport {
  id: string
  interval: string
  day: number
}

interface Props {
  scheduledExports: ScheduledExport[]
}

defineProps<Props>()

defineEmits<{
  add: []
  edit: [exportId: string]
  delete: [exportId: string]
}>()

const { getFrequencyLabel, getWeekdayLabel, getMonthDayLabel } = useScheduledExportOptions()

const formatScheduledExportDescription = (scheduledExport: ScheduledExport): string => {
  const frequencyLabel = getFrequencyLabel(scheduledExport.interval)

  if (scheduledExport.interval === 'daily') {
    return frequencyLabel
  }

  if (scheduledExport.interval === 'weekly') {
    const weekdayLabel = getWeekdayLabel(scheduledExport.day)

    return `${frequencyLabel}, ${weekdayLabel}`
  }

  if (scheduledExport.interval === 'monthly') {
    const monthDayLabel = getMonthDayLabel(scheduledExport.day)

    return `${frequencyLabel}, am ${monthDayLabel} Tag des Monats`
  }

  return `${frequencyLabel}, ${scheduledExport.day}`
}
</script>
