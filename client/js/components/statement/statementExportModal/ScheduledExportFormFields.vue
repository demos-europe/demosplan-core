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
      <dp-select
        class="mt-4"
        data-cy="scheduledExport:selectedFrequency"
        :label="{ text: Translator.trans('export.xlsx.scheduled.interval') }"
        name="frequency"
        :options="frequencyOptions"
        :selected="selectedFrequency"
        @select="handleFrequencySelect"
      />
      <dp-select
        v-if="dependentSelect"
        class="mt-4"
        :data-cy="`scheduledExport:${dependentSelect.name}`"
        :label="{ text: dependentSelect.label }"
        :name="dependentSelect.name"
        :options="dependentSelect.options"
        :selected="dependentSelect.selected"
        @select="value => handleSelect(dependentSelect.name, value)"
      />
    </fieldset>

    <fieldset class="pb-0">
      <div class="rounded bg-neutral-light-4 p-3 mt-4">
        <div class="grid grid-cols-3 gap-2">
          <span>
            {{ `${Translator.trans('export.xlsx.scheduled.interval')}:` }}
          </span>
          <span class="font-semibold col-span-2">
            {{ frequencyLabel }}
          </span>
          <span>
            {{ `${Translator.trans('export.xlsx.scheduled.next')}:` }}
          </span>
          <span class="font-semibold col-span-2">
            {{ nextExportLabel }}
          </span>
        </div>
      </div>
    </fieldset>
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

type DependentSelectName = 'weekday' | 'monthDay'

interface DependentSelect {
  name: DependentSelectName
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

const dependentSelect = computed<DependentSelect | null>(() => {
  if (selectedFrequency.value === 'weekly') {
    return {
      name: 'weekday',
      label: Translator.trans('export.xlsx.scheduled.interval.weekday'),
      options: weekdayOptions,
      selected: selectedWeekday.value,
    }
  }

  if (selectedFrequency.value === 'monthly') {
    return {
      name: 'monthDay',
      label: Translator.trans('export.xlsx.scheduled.interval.monthDay'),
      options: monthDayOptions,
      selected: selectedMonthDay.value,
    }
  }

  return null
})

const { getNextExportDate, formatExportDate } = useScheduledExportDate()

const frequencyLabel = computed(() => {
  if (selectedFrequency.value === 'monthly') {
    return `Am ${selectedMonthDay.value}. Tag jedes Monats`
  }

  return frequencyOptions.find(({ value }) => value === selectedFrequency.value)?.label ?? ''
})

const nextExportLabel = computed(() => {
  if (!selectedFrequency.value) {
    return ''
  }

  if (selectedFrequency.value === 'daily') {
    const nextDate = getNextExportDate({ interval: 'daily' })

    return formatExportDate(nextDate)
  }

  if (selectedFrequency.value === 'weekly') {
    const nextDate = getNextExportDate({ interval: 'weekly', weekday: selectedWeekday.value })

    return formatExportDate(nextDate)
  }

  if (selectedFrequency.value === 'monthly') {
    const nextDate = getNextExportDate({ interval: 'monthly', dayOfMonth: selectedMonthDay.value })

    return formatExportDate(nextDate)
  }

  return ''
})

const handleFrequencySelect = (value: string) => {
  selectedFrequency.value = value
  selectedWeekday.value = 1
  selectedMonthDay.value = 5
}

const handleSelect = (name: DependentSelectName, value: string | number) => {
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
