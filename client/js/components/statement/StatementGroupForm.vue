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
            id="action-create"
            class="mb-3"
            name="groupAction"
            value="createGroup"
            :checked="selectedAction === 'createGroup'"
            :label="{
              text: Translator.trans('statement.cluster.create'),
              hint: Translator.trans('statement.cluster.create.hint'),
              bold: true
            }"
            @change="selectedAction = 'createGroup'"
          />
          <dp-radio
            id="addToGroup"
            name="groupAction"
            value="addToGroup"
            :label="{
              text: Translator.trans('statement.cluster.add'),
              hint: Translator.trans('statement.cluster.add.hint'),
            }"
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
          <ul :class="statements.length > 5 ? 'max-h-[245px] overflow-y-auto' : ''">
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
        <div>
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
            track-by="id"
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
import { dpApi, DpIcon, DpInput, DpLabel, DpLoading, DpMultiselect, DpRadio } from '@demos-europe/demosplan-ui'
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
const selectedAction = ref('') // Default until second story will be implemented (add stmt to group)
const statements = ref([])
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

function handleApply () {
  console.log('apply clicked, statements:', statements.value)
  step.value = 3
}

async function fetchStatements () {
  const ids = statements.value.map(s => s.id)
  if (ids.length === 0) return

  // Filter mit OR-Gruppe aufbauen
  const filter = {
    statementFilterGroup: {
      group: { conjunction: 'OR' },
    },
  }
  ids.forEach((id, idx) => {
    filter['statement_' + idx] = {
      condition: {
        path: 'id',
        value: id,
        memberOf: 'statementFilterGroup',
      },
    }
  })

  const params = {
    filter,
    fields: {
      Statement: 'externId,authorName,initialOrganisationName,isSubmittedByCitizen',
    },
  }

  const response = await dpApi.get(
    Routing.generate('api_resource_list', { resourceType: 'Statement' }),
    params,
  )

  // Response-Daten in statements.value schreiben (ersetzt die )
  statements.value = response.data.data
}

function removeStatement (id) {
  statements.value = statements.value.filter(stmt => stmt.id !== id)
}

function setStatements () {
  const stored = lscache.get(`${props.procedureId}:toggledStatements`)

  if (stored) {
    statements.value = stored
  }
}

onMounted(async () => {
  setStatements()
  await fetchStatements()
  isLoading.value = false
})

</script>

