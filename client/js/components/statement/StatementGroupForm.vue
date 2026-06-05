<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <action-stepper
      :busy="isBusy"
      :return-link="returnLink"
      :selected-elements="selectedElementsCount"
      :step="step"
      :total-steps="3"
      :translations="translations"
      :valid="isValid"
      @confirm="step = 2"
      @edit="step = 1"
      @apply="handleApply"
    >
      <template v-slot:step-1>
        <div class="mt-5 mb-6">
          <dp-radio
            id="actionCreate"
            class="mb-3"
            name="groupAction"
            value="createGroup"
            :checked="selectedAction === 'createGroup'"
            :label="{
              text: Translator.trans('statement.cluster.create'),
              hint: Translator.trans('statement.cluster.create.hint'),
            }"
            @change="selectedAction = 'createGroup'"
          />
          <dp-radio
            id="addToGroup"
            name="groupAction"
            :checked="selectedAction === 'addToGroup'"
            :label="{
              text: Translator.trans('statement.cluster.add'),
              hint: Translator.trans('statement.cluster.add.hint'),
            }"
            value="addToGroup"
            @change="selectedAction = 'addToGroup'"
          />
        </div>
        <div v-if="isLoading">
          <dp-loading />
        </div>
        <div v-else>
          <h4 class="font-semibold mb-0.5">
            {{ Translator.trans('statements.selected', { count: selectedElementsCount }) }}
          </h4>
          <p class="mb-3">
            {{ Translator.trans('statements.selected.adjust.hint') }}
          </p>
          <selected-statements-list
            :statements="statements"
            @remove="removeStatement"
          />
        </div>
      </template>
      <template v-slot:step-2>
        <div data-dp-validate="groupForm">
          <template v-if="selectedAction === 'createGroup'">
            <dp-input
              id="groupName"
              v-model="groupName"
              :label="{
              text: Translator.trans('statement.cluster.name'),
              hint: Translator.trans('statement.cluster.name.hint'),
            }"
              class="mb-5"
              required
            />
            <dp-label
              for="mainStatement"
              bold
              :text="Translator.trans('statement.main')"
              :hint="Translator.trans('statement.cluster.create.help')"
            />
            <dp-multiselect
              id="mainStatement"
              v-model="mainStatementId"
              :custom-label="stmt => stmt.attributes.externId"
              :options="statements"
              required
              track-by="id"
              searchable
            />
          </template>
          <template v-else-if="selectedAction === 'addToGroup'">
            <dp-label
              for="targetGroup"
              bold
              :text="Translator.trans('cluster.choose')"
              :hint="Translator.trans('cluster.choose.hint')"
            />
            <!-- TODO(DPLAN-17748): load this procedure's existing groups into `groups`; backend endpoint not available yet, using statements as placeholder -->
            <dp-multiselect
              id="targetGroup"
              v-model="targetGroupId"
              class="mb-5"
              :custom-label="stmt => stmt.attributes.externId"
              :options="statements"
              required
              track-by="id"
              searchable
            />
            <h4 class="font-semibold mb-0.5">
              {{ Translator.trans('statements.selected.no.count') }}
            </h4>
            <selected-statements-list
              :statements="statements"
              @remove="removeStatement"
            />
          </template>
        </div>
      </template>
      <template v-slot:step-3>
        <action-stepper-response
          :description-error="Translator.trans('error.statement.cluster.created')"
          :description-success="Translator.trans('statement.cluster.grouped.success')"
          :success="success"
        />
      </template>
    </action-stepper>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { dpApi, DpIcon, DpInput, DpLabel, DpLoading, DpMultiselect, DpRadio, validateForm } from '@demos-europe/demosplan-ui'
import ActionStepper from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepper'
import ActionStepperResponse from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepperResponse'
import SelectedStatementsList from '@DpJs/components/statement/SelectedStatementsList'
import lscache from 'lscache'

const props = defineProps({
  procedureId: {
    type: String,
    required: true,
  },
})

const isBusy = ref(false)
const isLoading = ref(true)
const mainStatementId = ref(null)
const targetGroupId = ref(null)
const groupName = ref('')
const returnLink = ref(Routing.generate('dplan_procedure_statement_list', { procedureId: props.procedureId }))
const selectedAction = ref('createGroup')
const statements = ref([])
const selectionCriteria = ref(null)
const step = ref(1)
const success = ref(true)

const isValid = computed(() => statements.value.length > 0)
const selectedElementsCount = computed(() => statements.value.length)
const translations = computed(() => ({
  apply: Translator.trans('edit.confirm'),
  back: Translator.trans('statement.list.back'),
  backToList: Translator.trans('statement.list.back'),
  confirm: Translator.trans('continue.to.edit'),
  edit: Translator.trans('back.to.action.selection'),
  stepTitles: [
    Translator.trans('bulk.edit.title.actions.choose', { count: selectedElementsCount.value }),
    selectedAction.value === 'addToGroup'
      ? Translator.trans('statement.cluster.add')
      : Translator.trans('statement.cluster.create'),
    Translator.trans('confirm.saved.plural'),
  ],
}))

function handleApply () {
  console.log('apply clicked, statements:', statements.value)

  const { valid } = validateForm(document.querySelector('[data-dp-validate=groupForm]'))

  if (!valid) {
    dplan.notify.notify('error', Translator.trans('error.mandatoryfields'))

    return
  }

  step.value = 3
}

async function fetchStatements () {
  if (!selectionCriteria.value) {
    return
  }

  const fields = { Statement: 'externId,authorName,initialOrganisationName,isSubmittedByCitizen' }
  const size = 100
  const collected = []
  let number = 1
  let totalPages = 1

  // Page through the whole selected set so "select all" covers every matching statement.
  do {
    const response = await dpApi.get(
      Routing.generate('api_resource_list', { resourceType: 'Statement' }),
      { ...selectionCriteria.value, fields, page: { number, size } },
    )

    collected.push(...response.data.data)
    totalPages = response.data.meta?.pagination?.totalPages ?? 1
    number++
  } while (number <= totalPages)

  statements.value = collected
}

function removeStatement (id) {
  statements.value = statements.value.filter(stmt => stmt.id !== id)
}

function setStatements () {
  selectionCriteria.value = lscache.get(`${props.procedureId}:toggledStatements`)
}

onMounted(async () => {
  setStatements()
  await fetchStatements()
  isLoading.value = false
})

</script>

