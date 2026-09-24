<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p class="mb-4">
      {{ Translator.trans('export.xlsx.scheduled.description') }}
    </p>
    <fieldset class="border-b border-neutral">
      <legend class="sr-only">
        {{ Translator.trans('export.xlsx.scheduled.add') }}
      </legend>

      <dp-select
        class="mt-4"
        data-cy="scheduledExportForm:selectedFrequency"
        :label="{ text: Translator.trans('export.xlsx.scheduled.frequency') }"
        name="frequency"
        :options="frequencyOptions"
        :selected="selectedFrequency"
        @select="handleFrequencySelect"
      />
      <dp-select
        v-if="daySelect"
        class="mt-4"
        :data-cy="`scheduledExportForm:${daySelect.name}`"
        :label="{ text: daySelect.label }"
        :name="daySelect.name"
        :options="daySelect.options"
        :selected="daySelect.selected"
        @select="value => handleDaySelect(daySelect.name, value)"
      />
    </fieldset>

    <div class="rounded bg-neutral-light-4 p-3 mt-4">
      <dl class="grid grid-cols-3 gap-2">
        <dt>
          {{ `${Translator.trans('export.xlsx.scheduled.frequency')}:` }}
        </dt>
        <dd class="font-semibold col-span-2">
          {{ frequencyLabel }}
        </dd>
        <dt>
          {{ `${Translator.trans('export.xlsx.scheduled.next')}:` }}
        </dt>
        <dd class="font-semibold col-span-2">
          {{ nextExportLabel }}
        </dd>
      </dl>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { DpSelect } from '@demos-europe/demosplan-ui'
import { useScheduledExportDate } from '@DpJs/composables/useScheduledExportDate'
import { useScheduledExportOptions } from '@DpJs/composables/useScheduledExportOptions'
import type { ScheduledExport, ScheduledExportFormData, DaySelect, DaySelectName } from '@DpJs/types/scheduledExport'

interface Props {
  editingExport?: ScheduledExport | null
}

const props = withDefaults(defineProps<Props>(), {
  editingExport: null
})

const formData = defineModel<ScheduledExportFormData>('formData', {
  default: () => (
    {
      frequency: '',
      weekday: null,
      dayOfMonth: null,
    }
  )
})

const selectedFrequency = ref('daily')
const selectedWeekday = ref<number>(1)
const selectedDayOfMonth = ref<number>(5)

const { frequencyOptions, weekdayOptions, dayOfMonthOptions } = useScheduledExportOptions()

const daySelect = computed<DaySelect | null>(() => {
  switch (selectedFrequency.value) {
    case 'weekly':
      return {
        name: 'weekday',
        label: Translator.trans('export.xlsx.scheduled.frequency.weekday'),
        options: weekdayOptions,
        selected: selectedWeekday.value,
      }

    case 'monthly':
      return {
        name: 'dayOfMonth',
        label: Translator.trans('export.xlsx.scheduled.frequency.dayOfMonth'),
        options: dayOfMonthOptions,
        selected: selectedDayOfMonth.value,
      }

    default:
      return null
  }
})

const { getNextExportDate, formatExportDate } = useScheduledExportDate()

const frequencyLabel = computed(() => {
  if (selectedFrequency.value === 'monthly') {
    return Translator.trans('export.xlsx.scheduled.frequency.monthly.selectedDay', { selectedDay: selectedDayOfMonth.value })
  }

  return frequencyOptions.find(({ value }) => value === selectedFrequency.value)?.label ?? ''
})

const nextExportLabel = computed(() => {
  const nextRunAt = props.editingExport?.attributes?.nextRunAt // Use nextRunAt provided by the backend

  if (nextRunAt) {
    return formatExportDate(new Date(nextRunAt))
  }

  let params

  switch (selectedFrequency.value) {
    case 'daily':
      params = { frequency: 'daily' }
      break

    case 'weekly':
      params = {
        frequency: 'weekly',
        weekday: selectedWeekday.value,
      }
      break

    case 'monthly':
      params = {
        frequency: 'monthly',
        dayOfMonth: selectedDayOfMonth.value,
      }
      break

    default:
      return ''
  }

  return formatExportDate(getNextExportDate(params))
})

const handleFrequencySelect = (value: string) => {
  selectedFrequency.value = value
  selectedWeekday.value = 1
  selectedDayOfMonth.value = 5
}

const handleDaySelect = (name: DaySelectName, value: string | number) => {
  const numericValue = typeof value === 'string' ? parseInt(value, 10) : value

  if (name === 'weekday') {
    selectedWeekday.value = numericValue
  } else {
    selectedDayOfMonth.value = numericValue
  }
}

const resetForm = () => {
  selectedFrequency.value = 'daily'
  selectedWeekday.value = 1
  selectedDayOfMonth.value = 5
}

const populateForm = (editingExport: ScheduledExport) => {
  selectedFrequency.value = editingExport.attributes.frequency
  selectedWeekday.value = editingExport.attributes.frequency === 'weekly' ? editingExport.attributes.weekday : 1
  selectedDayOfMonth.value = editingExport.attributes.frequency === 'monthly' ? editingExport.attributes.dayOfMonth : 5
}

watch([selectedFrequency, selectedWeekday, selectedDayOfMonth], () => {
  Object.assign(formData.value, {
    frequency: selectedFrequency.value,
    weekday: selectedFrequency.value === 'weekly' ? selectedWeekday.value : null,
    dayOfMonth: selectedFrequency.value === 'monthly' ? selectedDayOfMonth.value : null,
  })
}, { immediate: true })

watch(() => props.editingExport, (editingExport) => {
  if (editingExport) {
    populateForm(editingExport)
  } else {
    resetForm()
  }
}, { immediate: true })
</script>
