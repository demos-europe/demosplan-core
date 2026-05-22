<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    Helloooooooo

    <action-stepper
      :step="step"
      :selected-elements="selectedElementsCount"
      total-steps="3"
      :valid="isValid"
      :busy="isBusy"
      :return-link="returnLink"
      :translations="translations"
      @confirm="step = 2"
      @edit="step = 1"
      @apply="handleApply"
    >
      <template v-slot:step-1>
        <dp-radio>

        </dp-radio>
      </template>
      <template v-slot:step-2></template>
      <template v-slot:step-3></template>
    </action-stepper>
  </div>
</template>

<script setup>
import {computed, ref, onMounted} from 'vue'
import ActionStepper from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepper'
import lscache from 'lscache'
import {DpRadio} from '@demos-europe/demosplan-ui'

const props = defineProps({
  procedureId: {
    type: String,
    required: true
  },
})

const statements = ref([])
const step = ref(1)
const isBusy = ref(false)
const returnLink = ref('#')

const isValid = computed(() => statements.value.length > 0)
const selectedElementsCount = computed(() => statements.value.length)
const translations = computed(() => ({
  back: Translator.trans('statement.list.back'),
  confirm: Translator.trans('continue.to.edit'),
  apply: Translator.trans('group.new'),
  stepTitles: [
    Translator.trans('bulk.edit.title.actions.choose', { count: selectedElementsCount.value }),
    Translator.trans('statement.cluster.create'),
    Translator.trans('confirm.saved.plural'),
  ],
}))

function handleApply () {
  console.log('apply clicked, statements:', statements.value)
}

function setStatements () {
  const stored = lscache.get(`${props.procedureId}:toggledStatements`)
  console.log("stored from lscache:", stored)

  if (stored) {
    statements.value = stored
  }
}

onMounted(() => {
  setStatements()
})

</script>

