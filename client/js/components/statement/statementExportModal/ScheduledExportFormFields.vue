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
        :label="{ text: Translator.trans('export.xlsx.scheduled.interval') }"
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
          {{ `${Translator.trans('export.xlsx.scheduled.interval')}:` }}
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

interface ScheduledExport {
  id: string
  interval: string
  day: number
}

interface Props {
  editingExport?: ScheduledExport | null
}

interface SelectOption {
  label: string
  value: string | number
}

type DaySelectName = 'weekday' | 'monthDay'

interface DaySelect {
  name: DaySelectName
  label: string
  options: SelectOption[]
  selected: string | number
}

const props = withDefaults(defineProps<Props>(), {
  editingExport: null
})

const formData = defineModel<{ interval: string; day: number | null }>('formData', {
  default: () => ({ interval: '', day: null })
})

const getSelectedDay = () => {
  if (selectedFrequency.value === 'weekly') {
    return selectedWeekday.value
  }

  if (selectedFrequency.value === 'monthly') {
    return selectedMonthDay.value
  }

  return null
}

const selectedFrequency = ref('daily')
const selectedWeekday = ref<number>(1)
const selectedMonthDay = ref<number>(5)

const { frequencyOptions, weekdayOptions, monthDayOptions } = useScheduledExportOptions()

const daySelect = computed<DaySelect | null>(() => {
  switch (selectedFrequency.value) {
    case 'weekly':
      return {
        name: 'weekday',
        label: Translator.trans('export.xlsx.scheduled.interval.weekday'),
        options: weekdayOptions,
        selected: selectedWeekday.value,
      }

    case 'monthly':
      return {
        name: 'monthDay',
        label: Translator.trans('export.xlsx.scheduled.interval.monthDay'),
        options: monthDayOptions,
        selected: selectedMonthDay.value,
      }

    default:
      return null
  }
})

const { getNextExportDate, formatExportDate } = useScheduledExportDate()

const frequencyLabel = computed(() => {
  if (selectedFrequency.value === 'monthly') {
    return `Am ${selectedMonthDay.value}. Tag jedes Monats`
  }

  return frequencyOptions.find(({ value }) => value === selectedFrequency.value)?.label ?? ''
})

const nextExportLabel = computed(() => {
  switch (selectedFrequency.value) {
    case 'daily':
      return formatExportDate(getNextExportDate({ interval: 'daily' }))

    case 'weekly':
      return formatExportDate(getNextExportDate({ interval: 'weekly', weekday: selectedWeekday.value }))

    case 'monthly':
      return formatExportDate(getNextExportDate({ interval: 'monthly', dayOfMonth: selectedMonthDay.value }))

    default:
      return ''
  }
})

const handleFrequencySelect = (value: string) => {
  selectedFrequency.value = value
  selectedWeekday.value = 1
  selectedMonthDay.value = 5
}

const handleDaySelect = (name: DaySelectName, value: string | number) => {
  const numericValue = typeof value === 'string' ? parseInt(value, 10) : value

  if (name === 'weekday') {
    selectedWeekday.value = numericValue
  } else {
    selectedMonthDay.value = numericValue
  }
}

const resetForm = () => {
  selectedFrequency.value = 'daily'
  selectedWeekday.value = 1
  selectedMonthDay.value = 5
}

const populateForm = (editingExport: ScheduledExport) => {
  selectedFrequency.value = editingExport.interval
  selectedWeekday.value = editingExport.interval === 'weekly' ? editingExport.day : 1
  selectedMonthDay.value = editingExport.interval === 'monthly' ? editingExport.day : 5
}

watch([selectedFrequency, selectedWeekday, selectedMonthDay], () => {
  Object.assign(formData.value, {
    interval: selectedFrequency.value,
    day: getSelectedDay(),
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
