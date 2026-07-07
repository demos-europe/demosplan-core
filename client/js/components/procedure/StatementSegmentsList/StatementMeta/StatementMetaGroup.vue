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

      <dp-button-row
        v-if="editable"
        class="mt-2 w-full"
        primary
        secondary
        @primary-action="saveGroupName"
        @secondary-action="reset"
      />
    </template>

    <span
      v-if="isCluster"
      class="font-semibold block mb-0.5"
    >
      {{ Translator.trans('statement.cluster.main') }}
    </span>

    <!-- Submitter of the main statement -->
    <slot />

    <!-- Other statements in this group — shown only for cluster heads -->
    <dp-accordion
      v-if="isCluster"
      :title="Translator.trans('statement.cluster.further', { count: groupStatements.length })"
      is-open
    >
      <!--
        TODO(DPLAN-17748): the detail link is correct but its target page cannot load cluster members yet.
        The StatementResourceType access condition (`headStatement IS NULL`) hides members, so the statement
        detail page returns 400 for them. Works once the backend exposes cluster members for read access.
      -->
      <selected-statements-list
        :procedure-id="procedureId"
        :statements="paginatedStatements"
        show-detail-link
        @remove="removeGroupStatement"
      />
      <dp-sliding-pagination
        v-if="totalPages > 1"
        :current="currentPage"
        :non-sliding-size="10"
        :total="totalPages"
        @page-change="currentPage = $event"
      />
    </dp-accordion>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { DpAccordion, dpApi, DpButtonRow, DpInput, DpSlidingPagination } from '@demos-europe/demosplan-ui'
import SelectedStatementsList from '@DpJs/components/statement/SelectedStatementsList'

const props = defineProps({
  editable: {
    type: Boolean,
    default: false,
  },
  procedureId: {
    type: String,
    default: '',
  },
  statement: {
    type: Object,
    required: true,
  },
})

const PAGE_SIZE = 15

const currentPage = ref(1)
const initialGroupName = ref('')
const isCluster = computed(() => props.statement.attributes.isCluster)
const groupName = ref('')
const groupStatements = ref([])
const totalPages = computed(() => Math.ceil(groupStatements.value.length / PAGE_SIZE))
const paginatedStatements = computed(
  () => groupStatements.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE),
)

async function fetchGroup () {
  try {
    const response = await dpApi.get(`${Routing.getBaseUrl()}/api/3.0/StatementGroup/${props.statement.id}`)

    console.log('StatementGroup response', response.data)

    groupName.value = response.data.data.attributes.groupName
    initialGroupName.value = response.data.data.attributes.groupName

    /*
     * TODO(DPLAN-17748): interim id-only rendering. The StatementGroup response carries member IDs only,
     * and cluster members are not retrievable via any frontend endpoint (the Statement resource hides them
     * via the `headStatement IS NULL` access condition; the Headstatement resource has GET/LIST disabled).
     * Once the backend populates member externId/submitter in StatementGroupResource::fromStatement
     * (or supports ?include=statements), replace the empty attributes with the real member data.
     */
    groupStatements.value = response.data.data.relationships.statements.data.map(
      member => ({ id: member.id, attributes: {} }),
    )
  } catch (error) {
    console.error('Failed to load statement group:', error)
  }
}

async function saveGroupName () {
  try {
    await dpApi.patch(`${Routing.getBaseUrl()}/api/3.0/StatementGroup/${props.statement.id}`, {}, {
      data: {
        type: 'StatementGroup',
        id: props.statement.id,
        attributes: { groupName: groupName.value },
      },
    })
    dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
    initialGroupName.value = groupName.value
  } catch (error) {
    console.error('Failed to save group name:', error)
    dplan.notify.notify('error', Translator.trans('error.api.generic'))
  }
}

async function removeGroupStatement (id) {
  // Snapshot for rollback if the request fails, so UI and backend stay in sync.
  const previous = [...groupStatements.value]
  const removed = previous.find(stmt => stmt.id === id)

  groupStatements.value = groupStatements.value.filter(stmt => stmt.id !== id)

  /*
   * TODO(DPLAN-17748): remove the `?.attributes?.externId ||` fallback once the backend delivers
   * member externId (StatementGroupResource::fromStatement). Until then the toast shows the member UUID.
   */
  const removedLabel = removed?.attributes?.externId || removed?.id

  try {
    /*
     * Detach a single member via the JSON:API relationship endpoint. The backend applies an
     * idempotent delta, so we send only the removed member — not the remaining set. PATCH no
     * longer changes membership; it renames the group only.
     */
    await dpApi.delete(`${Routing.getBaseUrl()}/api/3.0/StatementGroup/${props.statement.id}/relationships/statements`, {}, {}, {
      data: [{ type: 'Statement', id }],
    })
    dplan.notify.notify('confirm', Translator.trans('confirm.statement.detach.cluster.element', {
      statementId: removedLabel,
      clusterId: props.statement.attributes.externId,
    }))
  } catch (error) {
    console.error('Failed to remove statement from group:', error)
    groupStatements.value = previous
    dplan.notify.notify('error', Translator.trans('error.statement.detach.cluster.element', {
      statementId: removedLabel,
    }))
  }
}

function reset () {
  groupName.value = initialGroupName.value
}

onMounted(() => {
  if (isCluster.value) {
    fetchGroup()
  }
})
</script>
