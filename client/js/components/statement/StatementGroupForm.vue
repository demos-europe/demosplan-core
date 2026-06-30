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
      @apply="handleApply"
      @confirm="handleConfirmStep1"
      @edit="step = 1"
    >
      <template v-slot:step-1>
        <div class="mt-5 mb-6">
          <dp-radio
            id="action-create"
            :checked="selectedAction === 'createGroup'"
            :label="{
              text: Translator.trans('statement.cluster.create'),
              hint: Translator.trans('statement.cluster.create.hint'),
            }"
            class="mb-3"
            name="groupAction"
            value="createGroup"
            @change="selectedAction = 'createGroup'"
          />
          <dp-radio
            id="addToGroup"
            :checked="selectedAction === 'addToGroup'"
            :label="{
              text: Translator.trans('statement.cluster.add'),
              hint: Translator.trans('statement.cluster.add.hint'),
            }"
            name="groupAction"
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
              :hint="Translator.trans('statement.cluster.create.help')"
              :text="Translator.trans('statement.main')"
              for="headStatement"
              bold
              required
            />
            <dp-multiselect
              id="headStatement"
              v-model="headStatement"
              :custom-label="stmt => stmt.attributes.externId"
              :options="statements"
              track-by="id"
              required
              searchable
            />
          </template>
          <template v-else-if="selectedAction === 'addToGroup'">
            <dp-label
              :hint="Translator.trans('cluster.choose.hint')"
              :text="Translator.trans('cluster.choose')"
              for="targetGroup"
              bold
              required
            />
            <dp-multiselect
              id="targetGroup"
              v-model="targetGroupId"
              :custom-label="stmt => stmt.attributes.groupName"
              :options="groups"
              class="mb-5"
              track-by="id"
              required
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
import { dpApi, DpInput, DpLabel, DpLoading, DpMultiselect, DpRadio, validateForm } from '@demos-europe/demosplan-ui'
import ActionStepper from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepper'
import ActionStepperResponse from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepperResponse'
import lscache from 'lscache'
import SelectedStatementsList from '@DpJs/components/statement/SelectedStatementsList'

const props = defineProps({
  procedureId: {
    type: String,
    required: true,
  },
})

const groupName = ref('')
const groups = ref([])
const headStatement = ref(null)
const isBusy = ref(false)
const isLoading = ref(true)
const returnLink = ref(Routing.generate('dplan_procedure_statement_list', { procedureId: props.procedureId }))
const selectedAction = ref('createGroup')
const selectionCriteria = ref(null)
const statements = ref([])
const step = ref(1)
const success = ref(true)
const targetGroupId = ref(null)

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
    selectedAction.value === 'addToGroup' ?
      Translator.trans('statement.cluster.add') :
      Translator.trans('statement.cluster.create'),
    Translator.trans('confirm.saved.plural'),
  ],
}))

const handleConfirmStep1 = () => {
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

const handleApply = async () => {
  const { valid } = validateForm(document.querySelector('[data-dp-validate=groupForm]'))

  if (!valid) {
    dplan.notify.notify('error', Translator.trans('error.mandatoryfields'))

    return
  }

  if (selectedAction.value === 'createGroup' && !headStatement.value) {
    dplan.notify.notify('error', Translator.trans('error.mandatoryfields'))

    return
  }

  isBusy.value = true

  if (selectedAction.value === 'createGroup') {
    const payload = {
      type: 'StatementGroup',
      attributes: {
        groupName: groupName.value,
        headStatementId: headStatement.value.id,
      },
      relationships: {
        statements: {
          data: statements.value.map(stmt => ({ id: `${stmt.id}`, type: 'Statement' })),
        },
      },
    }

  isBusy.value = true

  try {
    await dpApi.post(`${Routing.getBaseUrl()}/api/3.0/StatementGroup`, {}, { data: payload })
    success.value = true
  } catch (error) {
    console.error('StatementGroup POST failed:', error)
    success.value = false
  } finally {
    /*
 * Grouping shrinks the statement list, so a persisted page may no longer exist.
 * Tell the list to reopen on page 1 and skip an out-of-range fetch.
 */
    lscache.set(`${props.procedureId}:statementListResetPage`, true)
      isBusy.value = false
      step.value = 3
    }
  } else {
    const payload = {
      type: 'StatementGroup',
      relationships: {
        statements: {
          data: statements.value.map(stmt => ({ id: `${stmt.id}`, type: 'Statement' })),
        },
      },
    }

    try {
      /*
       * TODO(DPLAN-17748): backend Patch operation not built yet — StatementGroupResource exposes only Get + Post.
       * Frontend is ahead of backend; this call will work once a Patch operation + update logic exist.
       */
      await dpApi.patch(`${Routing.getBaseUrl()}/api/3.0/StatementGroup/${targetGroupId.value.id}`, {}, { data: payload })
      success.value = true
    } catch (error) {
      console.error('StatementGroup PATCH failed:', error)
      success.value = false
    } finally {
      lscache.remove(`${props.procedureId}:toggledStatements`)
      isBusy.value = false
      step.value = 3
    }
  }
}

const fetchGroups = async () => {
  try {
    /*
     * TODO(DPLAN-17748): backend GetCollection not built yet — StatementGroupResource exposes only Get(/{id}) + Post,
     * and the provider has no collection logic. The group list stays empty until the backend adds it.
     */
    const response = await dpApi.get(`${Routing.getBaseUrl()}/api/3.0/StatementGroup`)

    groups.value = response.data.data
  } catch (error) {
    console.error('Failed to load statement groups:', error)
  }
}

const fetchStatements = async () => {
  if (!selectionCriteria.value) {
    isLoading.value = false

    return
  }

  const fields = { Statement: 'externId,authorName,initialOrganisationName,isSubmittedByCitizen,assignee,isCluster' }
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

  try {
    // Page through the whole selected set so "select all" covers every matching statement.
    do {
      const response = await dpApi.get(
        Routing.generate('api_resource_list', { resourceType: 'Statement' }),
        { ...selectionCriteria.value, filter, fields, include: 'assignee', page: { number, size } },
      )

      collected.push(...response.data.data)
      totalPages = response.data.meta?.pagination?.totalPages ?? 1
      number++
    } while (number <= totalPages)

    /*
     * "Select all" resolves criteria server-side and bypasses the list's checkbox locks,
     * so exclude group heads here. (Synchronized statements only exist in coupled procedures,
     * where `synchronized` is readable — not requested here to avoid faulty fieldset errors.)
     */
    statements.value = collected.filter(stmt => !stmt.attributes.isCluster)
  } catch (error) {
    console.error('Failed to load selected statements for grouping:', error)
    dplan.notify.notify('error', Translator.trans('error.api.generic'))
  } finally {
    isLoading.value = false
  }
}

const removeStatement = (id) => {
  statements.value = statements.value.filter(stmt => stmt.id !== id)
  // Reset head selection if the removed statement was the chosen head.
  if (headStatement.value?.id === id) {
    headStatement.value = null
  }
}

const setStatements = () => {
  selectionCriteria.value = lscache.get(`${props.procedureId}:toggledStatements`)
}

onMounted(() => {
  setStatements()
  fetchStatements()
  fetchGroups()
})

</script>

