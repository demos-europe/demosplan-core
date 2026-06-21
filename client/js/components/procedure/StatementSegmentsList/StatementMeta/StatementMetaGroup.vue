<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <!-- Group name — shown only for cluster (group) heads; editable in edit mode -->
    <template v-if="isCluster">
      <dp-input
        id="groupName"
        v-model="groupName"
        :disabled="!editable"
        :label="{ text: Translator.trans('statement.cluster.name') }"
        class="mb-2"
      />
      <dp-button
        v-if="editable"
        :text="Translator.trans('save')"
        class="mb-5"
        @click="saveGroupName"
      />
    </template>

    <span
      v-if="isCluster"
      class="font-semibold mb-0.5"
    >
      {{ Translator.trans('statement.cluster.main') }}
    </span>

    <!-- Submitter of the main statement -->
    <slot />

    <!-- Other statements in this group — shown only for cluster heads -->
    <template v-if="isCluster">
      <span class="font-semibold mb-0.5">
        {{ Translator.trans('statement.cluster.further', { count: groupStatements.length }) }}
      </span>
      <!-- TODO(DPLAN-17748): replace placeholder data with the actual grouped statements once the backend provides them -->
      <selected-statements-list
        :statements="groupStatements"
        @remove="removeGroupStatement"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { dpApi, DpButton, DpInput } from '@demos-europe/demosplan-ui'
import SelectedStatementsList from '@DpJs/components/statement/SelectedStatementsList'

const props = defineProps({
  editable: {
    type: Boolean,
    default: false,
  },
  statement: {
    type: Object,
    required: true,
  },
})

const isCluster = computed(() => props.statement.attributes.isCluster)
const groupName = ref('')
// TODO(DPLAN-17748): populate with the backend's grouped statements
const groupStatements = ref([])

async function fetchGroup () {
  try {
    const response = await dpApi.get(`/api/3.0/StatementGroup/${props.statement.id}`)

    console.log('StatementGroup response', response.data)

    groupName.value = response.data.data.attributes.groupName

    // The StatementGroup response only carries member IDs, so load the member details separately.
    const memberIds = response.data.data.relationships.statements.data.map(member => member.id)

    await fetchGroupMembers(memberIds)
  } catch (error) {
    console.error('Failed to load statement group:', error)
  }
}

async function fetchGroupMembers (ids) {
  if (0 === ids.length) {
    groupStatements.value = []

    return
  }

  const response = await dpApi.get(
    Routing.generate('api_resource_list', { resourceType: 'Statement' }),
    {
      fields: { Statement: 'externId,authorName,initialOrganisationName,isSubmittedByCitizen' },
      filter: {
        idIsOneOf: {
          condition: { path: 'id', value: ids, operator: 'IN' },
        },
      },
    },
  )

  groupStatements.value = response.data.data
}

async function saveGroupName () {
  // TODO(DPLAN-17748): backend PATCH operation not built yet — StatementGroupResource only exposes Get + Post.
  // Frontend is ahead of backend; this call will work once a Patch operation + update logic exist.
  try {
    await dpApi.patch(`/api/3.0/StatementGroup/${props.statement.id}`, {}, {
      data: {
        type: 'StatementGroup',
        id: props.statement.id,
        attributes: { groupName: groupName.value },
      },
    })
    dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
  } catch (error) {
    console.error('Failed to save group name:', error)
    dplan.notify.notify('error', Translator.trans('error.api.generic'))
  }
}

function removeGroupStatement (id) {
  groupStatements.value = groupStatements.value.filter(stmt => stmt.id !== id)
}

onMounted(() => {
  if (isCluster.value) {
    fetchGroup()
  }
})
</script>
