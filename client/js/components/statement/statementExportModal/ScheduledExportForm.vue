<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <template v-if="currentView === 'manage'">
      <div class="flex justify-between items-center pb-4">
        <span class="text-sm inline-block">
          {{ Translator.trans('export.xlsx.scheduled.active', { number: String(planedExports.length) }) }}
        </span>
        <dp-button
          data-cy="exportModal:openScheduledView:form"
          icon="calendar-blank"
          icon-size="medium"
          :text="Translator.trans('export.xlsx.scheduled.add')"
          variant="outline"
          @click="currentView = 'form'"
        />
      </div>

      <div class="flex flex-col gap-2">
        <div
          v-for="exp in planedExports"
          class="border border-neutral p-2">
          <div class="flex flex-row justify-between">
            <div class="flex gap-2">
              <dp-icon
                icon="file"
                size="xlarge"
              />
              <div>
                <span class="font-semibold block">XLSX-Datei</span>
                <span class="text-sm">Wöchentlich, am Montag</span>
              </div>
            </div>
            <div class="flex justify-between">
              <dp-button
                data-cy=""
                icon="edit"
                icon-size="large"
                hide-text
                :text="Translator.trans('export.xlsx.scheduled.add')"
                variant="transparent"
                @click="currentView = 'form'"
              />
              <dp-button
                data-cy=""
                icon="delete"
                icon-size="large"
                hide-text
                :text="Translator.trans('export.xlsx.scheduled.add')"
                variant="transparent"
                @click=""
              />
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Add/Edit Form -->
    <template v-if="currentView === 'form'">
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
    </template>

  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { DpButton, DpIcon, DpSelect } from '@demos-europe/demosplan-ui'

interface Props {
  procedureId: string,
  view: string,
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

const props = defineProps<Props>()

defineEmits<{
  'schedule-created': []
  cancel: []
}>()

const selectedFrequency = ref('')
const selectedWeekday = ref('')
const selectedMonthDay = ref('')
const currentView = ref(props.view)

const planedExports = ref([ // Mock data for now
  {
    id: 'export-1',
    planedExport: 'weekly',
    day: 'mo'
  },
  {
    id: 'export-2',
    planedExport: 'monthly',
    day: '15',
  },
])


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
