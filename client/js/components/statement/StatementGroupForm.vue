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
      @confirm="handleConfirmStep1"
      @edit="step = 1"
      @apply="handleApply"
    >
      <template v-slot:step-1>
        <div class="mt-5 mb-6">
          <dp-radio
            id="action-create"
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
          <ul
            :class="statements.length > 5 ? 'max-h-[255px] overflow-y-auto' : ''"
            class="border rounded-md pb-2 px-1"
          >
            <li
              v-for="stmt in statements"
              :key="stmt.id"
              class="py-2 border-b border-neutral-light-2"
            >
              <div class="flex items-center gap-2 px-1.5">
                <span>{{ stmt.attributes.externId }}</span>
                <span
                  v-if="stmt.attributes.isSubmittedByCitizen"
                >{{ stmt.attributes.authorName }}
                </span>
                <span
                  v-else
                >{{ stmt.attributes.initialOrganisationName }}
                </span>
                <button
                  type="button"
                  class="btn--blank o-link--default ml-auto"
                  :data-cy="`statementGroupForm:removeStatement:${stmt.id}`"
                  @click="removeStatement(stmt.id)"
                >
                  <dp-icon
                    icon="close"
                    size="small"
                  />
                </button>
              </div>
            </li>
          </ul>
        </div>
      </template>
      <template v-slot:step-2>
        <div data-dp-validate="groupForm">
          <dp-input
            id="groupName"
            v-model="groupName"
            class="mb-5"
            :label="{
              text: Translator.trans('statement.cluster.name'),
              hint: Translator.trans('statement.cluster.name.hint'),
            }"
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
    Translator.trans('statement.cluster.create'),
    Translator.trans('confirm.saved.plural'),
  ],
}))

function handleConfirmStep1 () {
  // Creating a group needs at least two statements; adding to an existing group (action "addToGroup") later allows one.
  if (selectedAction.value === 'createGroup' && statements.value.length < 2) {
    dplan.notify.notify('error', Translator.trans('confirm.consolidation.not.enough.statements'))

    return
  }

  if (statements.value.some(stmt => !stmt.relationships?.assignee?.data?.id)) {
    dplan.notify.notify('error', Translator.trans('confirm.consolidation.not.assigned'))

    return
  }

  step.value = 2
}

async function handleApply () {
  const { valid } = validateForm(document.querySelector('[data-dp-validate=groupForm]'))

  if (!valid) {
    dplan.notify.notify('error', Translator.trans('error.mandatoryfields'))

    return
  }

  if (!mainStatementId.value) {
    dplan.notify.notify('error', Translator.trans('error.mandatoryfields'))

    return
  }

  const payload = {
    type: 'StatementGroup',
    attributes: {
      groupName: groupName.value,
      headStatementId: mainStatementId.value.id,
    },
    relationships: {
      statements: {
        data: statements.value.map(stmt => ({ id: `${stmt.id}`, type: 'Statement' })),
      },
    },
  }

  isBusy.value = true

  try {
    await dpApi.post('/api/3.0/StatementGroup', {}, { data: payload })
    success.value = true
  } catch (error) {
    console.error('StatementGroup POST failed:', error)
    success.value = false
  } finally {
    // Always delete the stored selection so the same statements are not grouped more than once.
    lscache.remove(`${props.procedureId}:toggledStatements`)
    isBusy.value = false
    step.value = 3
  }
}

async function fetchStatements () {
  if (!selectionCriteria.value) {
    return
  }

  const fields = { Statement: 'externId,authorName,initialOrganisationName,isSubmittedByCitizen,assignee,synchronized,isCluster' }
  const size = 100
  const collected = []
  let number = 1
  let totalPages = 1

  /*
   * Exclude statements that have already been split into segments — they cannot be grouped.
   * This also guards the "select all" path, where the stored criteria are resolved server-side.
   */
  const filter = {
    ...selectionCriteria.value.filter,
    notSegmented: {
      condition: { path: 'segments.id', operator: 'IS NULL' },
    },
  }

  // Page through the whole selected set so "select all" covers every matching statement.
  do {
    const response = await dpApi.get(
      Routing.generate('api_resource_list', { resourceType: 'Statement' }),
      { ...selectionCriteria.value, filter, fields, page: { number, size } },
    )

    collected.push(...response.data.data)
    totalPages = response.data.meta?.pagination?.totalPages ?? 1
    number++
  } while (number <= totalPages)

  /*
   * "Select all" resolves criteria server-side and bypasses the list's checkbox locks,
   * so exclude non-groupable statements (synchronized / group heads) here.
   */
  statements.value = collected.filter(stmt => !stmt.attributes.synchronized && !stmt.attributes.isCluster)
}

function removeStatement (id) {
  statements.value = statements.value.filter(stmt => stmt.id !== id)
}

function setStatements () {
  selectionCriteria.value = lscache.get(`${props.procedureId}:toggledStatements`)
}

onMounted(async () => {
  setStatements()

  try {
    await fetchStatements()
  } catch (error) {
    console.error('Failed to load selected statements for grouping:', error)
    dplan.notify.notify('error', Translator.trans('error.api.generic'))
  } finally {
    isLoading.value = false
  }
})

</script>

