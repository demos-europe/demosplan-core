<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <!-- Group name — shown only for cluster (group) heads -->
    <dp-input
      v-if="isCluster"
      id="groupName"
      v-model="groupName"
      class="mb-5"
      :label="{ text: Translator.trans('statement.cluster.name') }"
    />
    <!-- TODO(DPLAN-17748): bind/save the group name once the backend exposes it -->

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
import { dpApi, DpInput } from '@demos-europe/demosplan-ui'
import SelectedStatementsList from '@DpJs/components/statement/SelectedStatementsList'

const props = defineProps({
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
  const response = await dpApi.get(
    Routing.generate('_api_/3.0/StatementGroup/{id}_get', { id: props.statement.id })
  )
  console.log('StatementGroup response', response.data)
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
