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
        <div class="grid grid-cols-2 gap-2 w-3/5">
          <span>
            {{ `${Translator.trans('export.xlsx.scheduled.interval')}:` }}
          </span>
          <span class="font-semibold">
            {{ selectedFrequencyLabel }}
          </span>
          <span>
          {{ `${Translator.trans('export.xlsx.scheduled.next')}:` }}
          </span>
          <span class="font-semibold">
            {{ nextExport }}
          </span>
        </div>
      </div>
    </fieldset>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { DpSelect } from '@demos-europe/demosplan-ui'

interface Props {
  procedureId: string
}

interface SelectOption {
  label: string
  value: string
}

type DependentSelectName = 'weekday' | 'monthDay'

interface DependentSelect {
  name: DependentSelectName
  label: string
  options: SelectOption[]
  selected: string
}

defineProps<Props>()

defineEmits<{
  'schedule-created': []
  cancel: []
}>()

const selectedFrequency = ref('')
const selectedWeekday = ref('')
const selectedMonthDay = ref('')

const frequencyOptions: SelectOption[] = [
  {
    label: Translator.trans('export.xlsx.scheduled.interval.daily'),
    value: 'daily',
  },
  {
    label: Translator.trans('export.xlsx.scheduled.interval.weekly'),
    value: 'weekly',
  },
  {
    label: Translator.trans('export.xlsx.scheduled.interval.monthly'),
    value: 'monthly',
  },
]

const weekdayOptions: SelectOption[] = [
  {
    label: Translator.trans('weekday.monday'),
    value: 'mo',
  },
  {
    label: Translator.trans('weekday.tuesday'),
    value: 'tu',
  },
  {
    label: Translator.trans('weekday.wednesday'),
    value: 'we',
  },
  {
    label: Translator.trans('weekday.thursday'),
    value: 'th',
  },
  {
    label: Translator.trans('weekday.friday'),
    value: 'fr',
  },
]

const monthDayOptions: SelectOption[] = ['5', '10', '15', '20', '25', '30'].map(value => ({
  label: `${value}.`,
  value,
}))

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

const selectedFrequencyLabel = computed(() =>
  frequencyOptions.find(({ value }) => value === selectedFrequency.value)?.label ?? ''
)

const nextExport = computed(() => {
  if (selectedFrequency.value === 'weekly') {
    return weekdayOptions.find(({ value }) => value === selectedWeekday.value)?.label ?? ''
  }

  return selectedMonthDay.value
})

const handleFrequencySelect = (value: string) => {
  selectedFrequency.value = value
  selectedWeekday.value = ''
  selectedMonthDay.value = ''
}

const handleSelect = (name: DependentSelectName, value: string) => {
  if (name === 'weekday') {
    selectedWeekday.value = value
  } else {
    selectedMonthDay.value = value
  }
}
</script>
