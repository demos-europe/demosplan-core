<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="mb-3">
    <dp-input
      id="searchinput"
      v-model="searchTerm"
      class="inline-block align-top u-1-of-3"
      :label="{
        hide: true,
        text: Translator.trans('search.submitter')
      }"
      @enter="search"
    />
    <dp-button
      class="ml-1 mb-3"
      :text="Translator.trans('search')"
      @click="search"
    />
    <p
      v-if="noResults"
      v-cleanhtml="Translator.trans('search.no.results', { searchterm: lastSearchedTerm })"
    />
    <dp-accordion
      v-for="group in procedureGroups"
      :key="group.procedureId"
      :font-weight="isGroupOpen(group.procedureId) ? 'bold' : 'normal'"
      :is-open="isGroupOpen(group.procedureId)"
      :title="group.procedureName"
      compressed
      padded
      @item:toggle="toggleGroup(group.procedureId)"
    >
      <dp-data-table
        :header-fields="headerFields"
        :items="group.statements"
        density="spacious"
        is-expandable
        track-by="id"
      >
        <template v-slot:status="{ status }">
          <status-badge
            :status="status"
            class="mt-0.5"
          />
        </template>
        <template v-slot:submitter="{ authorName, initialOrganisationName, submitName }">
          <ul class="o-list">
            <li class="o-list__item o-hellip--nowrap">
              {{ authorName || submitName || Translator.trans('citizen') }}
            </li>
            <li
              v-if="initialOrganisationName"
              class="o-list__item o-hellip--nowrap"
            >
              {{ initialOrganisationName }}
            </li>
          </ul>
        </template>
        <template v-slot:delete="rowData">
          <div class="text-right">
            <dp-button
              :text="Translator.trans('remove')"
              color="warning"
              data-cy="searchSubmitters:deleteStatement"
              icon="delete"
              icon-size="medium"
              variant="subtle"
              hide-text
              @click="deleteStatement(group.procedureId, rowData.id)"
            />
          </div>
        </template>
        <template v-slot:expandedContent="{ id }">
          <statement-meta-data :statement="statementsObject[id]">
            <template
              v-slot:default="{
                formattedAuthoredDate,
                formattedSubmitDate,
                initialOrganisationDepartmentName,
                initialOrganisationName,
                internId,
                location,
                submitName,
                submitType
              }"
            >
              <div class="grid grid-cols-2 gap-4">
                <dl class="description-list-inline">
                  <dt>{{ Translator.trans('submitter') }}:</dt>
                  <dd>{{ submitName }}</dd>
                  <dt>{{ Translator.trans('organisation') }}:</dt>
                  <dd>{{ initialOrganisationName }}</dd>
                  <dt>{{ Translator.trans('department') }}:</dt>
                  <dd>{{ initialOrganisationDepartmentName }}</dd>
                  <dt>{{ Translator.trans('address') }}:</dt>
                  <dd>{{ location }}</dd>
                </dl>
                <dl class="description-list-inline">
                  <dt>{{ Translator.trans('internId') }}:</dt>
                  <dd>{{ internId }}</dd>
                  <dt>{{ Translator.trans('statement.date.authored') }}:</dt>
                  <dd>{{ formattedAuthoredDate }}</dd>
                  <dt>{{ Translator.trans('statement.date.submitted') }}:</dt>
                  <dd>{{ formattedSubmitDate }}</dd>
                  <dt>{{ Translator.trans('submit.type') }}:</dt>
                  <dd>{{ submitType }}</dd>
                </dl>
              </div>
            </template>
          </statement-meta-data>
        </template>
      </dp-data-table>
    </dp-accordion>
  </div>
</template>

<script setup>
import { CleanHtml, DpAccordion, dpApi, DpButton, DpDataTable, DpInput } from '@demos-europe/demosplan-ui'
import { ref } from 'vue'
import StatementMetaData from '@DpJs/components/statement/StatementMetaData'
import StatusBadge from '@DpJs/components/procedure/Shared/StatusBadge'

const vCleanhtml = CleanHtml

const headerFields = [
  { field: 'externId', label: Translator.trans('id'), colWidth: '120px', initialMinWidth: 120 },
  { field: 'status', label: Translator.trans('statement.status'), colWidth: '180px', initialMinWidth: 180 },
  { field: 'submitter', label: Translator.trans('submitter'), colWidth: '240px', initialMinWidth: 240 },
  { field: 'delete', label: '' },
]
const lastSearchedTerm = ref('')
const openProcedureIds = ref([])
const procedureGroups = ref([])
const searchTerm = ref('')
const noResults = ref(false)

/*
 * The table needs flat items, while StatementMetaData reads `statement.attributes.*`.
 * Keep the unflattened resources by id so the expanded row can hand them over.
 */
const statementsObject = ref({})

const isGroupOpen = procedureId => openProcedureIds.value.includes(procedureId)

const toggleGroup = procedureId => {
  openProcedureIds.value = isGroupOpen(procedureId) ?
    openProcedureIds.value.filter(id => id !== procedureId) :
    [...openProcedureIds.value, procedureId]
}

const buildProcedureGroups = (results, included) => {
  const nameById = included.reduce((acc, resource) => {
    acc[resource.id] = resource.attributes.name

    return acc
  }, {})

  const groups = results.reduce((acc, result) => {
    const procedureId = result.relationships.procedure.data.id

    if (!acc[procedureId]) {
      acc[procedureId] = {
        procedureId,
        procedureName: nameById[procedureId],
        statements: [],
      }
    }

    acc[procedureId].statements.push({ id: result.id, ...result.attributes })

    return acc
  }, {})

  return Object.values(groups)
}

const search = () => {
  console.log(searchTerm.value)

  if (searchTerm.value.length === 0) {
    return
  }

  lastSearchedTerm.value = searchTerm.value
  const url = Routing.generate('api_resource_list', { resourceType: 'AdminStatementCrossProcedureSearch' })
  const params = {
    filter: {
      authorOrSubmitter: {
        group: {
          conjunction: 'OR',
        },
      },
      withAuthor: {
        condition: {
          path: 'authorName',
          value: searchTerm.value,
          operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
          memberOf: 'authorOrSubmitter',
        },
      },
      withSubmitter: {
        condition: {
          path: 'submitName',
          value: searchTerm.value,
          operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
          memberOf: 'authorOrSubmitter',
        },
      },
    },
    include: 'procedure',
    fields: {
      AdminStatementCrossProcedureSearch: [
        'externId',
        'status',
        'authorName',
        'submitName',
        'initialOrganisationName',
        'procedure',
      ].join(),
      Procedure: ['name'].join(),
    },
  }

  dpApi.get(url, params)
    .then(response => {
      console.log(response.data)
      noResults.value = response.data.data.length === 0
      statementsObject.value = response.data.data.reduce((acc, statement) => {
        acc[statement.id] = statement

        return acc
      }, {})
      procedureGroups.value = buildProcedureGroups(response.data.data, response.data.included || [])
      console.log(procedureGroups.value)
    })
    .catch(error => console.log(error))
}

const deleteStatement = (procedureId, statementId) => {
  const url = Routing.generate('api_resource_delete', {
    resourceType: 'AdminStatementCrossProcedureSearch',
    resourceId: statementId,
  })

  dpApi.delete(url)
    .then(() => {
      const group = procedureGroups.value.find(group => group.procedureId === procedureId)

      if (!group) {
        return
      }

      group.statements = group.statements.filter(statement => statement.id !== statementId)

      if (group.statements.length === 0) {
        procedureGroups.value = procedureGroups.value.filter(group => group.procedureId !== procedureId)
      }

      if (procedureGroups.value.length === 0) {
        noResults.value = true
      }
    })
    .catch(() => {
      dplan.notify.error(Translator.trans('error.api.generic'))
    })
}
</script>
