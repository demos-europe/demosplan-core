<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div data-cy="boilerplateUsageList">
    <h2
      class="text-base font-bold mb-4"
      data-cy="boilerplateUsageList:headline"
    >
      {{ headline }}
    </h2>
    <dp-checkbox
      v-if="hasLockFeature && rows.length > 0"
      id="boilerplateUsageOnlyLocked"
      :checked="onlyLocked"
      :disabled="lockedCount === 0"
      :label="{ text: Translator.trans('boilerplate.usage.filter.locked') }"
      class="mb-4"
      data-cy="boilerplateUsageList:onlyLocked"
      @change="(checked: boolean) => onlyLocked = checked"
    />
    <dp-data-table
      v-if="rows.length > 0"
      :header-fields="headerFields"
      :items="visibleRows"
      data-cy="boilerplateUsageList:table"
      track-by="id"
      is-expandable
    >
      <template v-slot:header-externId>
        <span class="pl-[26px]">{{ Translator.trans('id') }}</span>
      </template>
      <template v-slot:externId="rowData">
        <!-- The lock icon sits in a fixed gutter so the IDs of locked and unlocked rows stay aligned -->
        <span class="relative inline-flex items-center pl-[26px]">
          <span
            v-if="rowData.locked"
            class="absolute left-0 top-1/2 -translate-y-1/2 flex items-center"
          >
            <dp-button
              v-if="canUnlock"
              :text="Translator.trans('segment.unlock.click.hint')"
              class="text-interactive bg-transparent! border-transparent! hover:bg-interactive-subtle-hover!"
              data-cy="boilerplateUsageList:unlock"
              icon="prohibit"
              icon-size="small"
              icon-weight="fill"
              variant="subtle"
              hide-text
              @click="openUnlock(rowData)"
            />
            <dp-tooltip
              v-else
              :text="Translator.trans('segment.lock.hint')"
            >
              <dp-icon
                class="text-interactive"
                data-cy="boilerplateUsageList:lockIcon"
                icon="prohibit"
                size="small"
                weight="fill"
              />
            </dp-tooltip>
          </span>
          <a
            :href="segmentUrl(rowData)"
            data-cy="boilerplateUsageList:link"
            rel="noopener"
            target="_blank"
          >
            {{ rowData.externId }}
          </a>
        </span>
      </template>
      <template v-slot:assigneeName="rowData">
        {{ rowData.assigneeName ?? '—' }}
      </template>
      <template v-slot:placeName="rowData">
        {{ rowData.placeName ?? '—' }}
      </template>
      <template v-slot:expandedContent="rowData">
        <p class="font-bold mb-1">
          {{ Translator.trans('recommendation.text') }}:
        </p>
        <div
          v-cleanhtml="rowData.recommendation"
          class="c-styled-html"
          data-cy="boilerplateUsageList:recommendation"
        />
      </template>
    </dp-data-table>
    <dp-inline-notification
      v-else
      :message="Translator.trans('boilerplate.usage.none')"
      data-cy="boilerplateUsageList:empty"
      type="info"
    />
    <segment-unlock-modal
      v-if="canUnlock && unlockOptionsLoaded"
      ref="unlockModal"
      :assignable-users="unlockAssignableUsers"
      :places="places"
      @unlock="onUnlock"
    />
  </div>
</template>

<script setup lang="ts">
import { CleanHtml, DpButton, DpCheckbox, DpDataTable, DpIcon, DpInlineNotification, DpTooltip } from '@demos-europe/demosplan-ui'
import { computed, onMounted, ref } from 'vue'
// eslint-disable-next-line import-x/extensions -- vue-tsc can't resolve an extensionless .vue import (see eslint.config.js:286-288)
import SegmentUnlockModal from '@DpJs/components/procedure/StatementSegmentsList/SegmentUnlockModal.vue'
import { useSegmentUnlock } from '@DpJs/composables/useSegmentUnlock'
import { useStore } from 'vuex'

/** One row as built by ProcedureService::getBoilerplateUsagesForDisplay() */
interface UsageRow {
  id: string
  type: 'segment' | 'statement'
  externId: string
  statementId: string
  assigneeName: string | null
  placeId: string | null
  placeName: string | null
  locked: boolean
  recommendation: string
}

interface SelectOption {
  id: string
  name: string
}

interface PlaceOption extends SelectOption {
  locked: boolean
}

/** Payload of SegmentUnlockModal's `unlock` event */
interface UnlockPayload {
  assignee: SelectOption
  place: PlaceOption
}

interface JsonApiResource {
  id: string
  attributes: Record<string, any>
}

const props = defineProps<{
  procedureId: string
  usages: UsageRow[]
}>()

const vCleanhtml = CleanHtml

const store = useStore()
const { unlockModal, openUnlockModal, unlockSegment } = useSegmentUnlock()

const hasLockFeature = hasPermission('feature_segment_lock_by_workflow_place')
const canUnlock = hasLockFeature && hasPermission('feature_administrate_segment_lock')

// Local copy so an unlock can update the affected row without reloading the page
const rows = ref<UsageRow[]>(props.usages.map(usage => ({ ...usage })))
const rowIdToUnlock = ref<string | null>(null)
const onlyLocked = ref(false)
const unlockOptionsLoaded = ref(false)

const headerFields = [
  { field: 'externId', label: Translator.trans('id') },
  { field: 'assigneeName', label: Translator.trans('assignee') },
  { field: 'placeName', label: Translator.trans('place') },
]

const lockedCount = computed(() => rows.value.filter(row => row.locked).length)

const visibleRows = computed(() => onlyLocked.value ? rows.value.filter(row => row.locked) : rows.value)

const headline = computed(() => {
  const count = String(rows.value.length)

  return lockedCount.value > 0 ?
    Translator.trans('boilerplate.usage.headline.locked', { count, lockedCount: String(lockedCount.value) }) :
    Translator.trans('boilerplate.usage.headline', { count })
})

const places = computed<PlaceOption[]>(() => Object.values<JsonApiResource>(store.state.Place?.items ?? {})
  .map(place => ({ id: place.id, name: place.attributes.name, locked: !!place.attributes.locked })))

const unlockAssignableUsers = computed<SelectOption[]>(() => [
  { id: 'noAssigneeId', name: Translator.trans('not.assigned') },
  ...Object.values<JsonApiResource>(store.state.AssignableUser?.items ?? {})
    .map(user => ({ id: user.id, name: `${user.attributes.firstname} ${user.attributes.lastname}` })),
])

const segmentUrl = (row: UsageRow): string => {
  const url = Routing.generate('dplan_statement_segments_list', {
    procedureId: props.procedureId,
    statementId: row.statementId,
    segment: row.id,
  })

  return `${url}#recommendation`
}

const openUnlock = (row: UsageRow) => {
  rowIdToUnlock.value = row.id
  openUnlockModal(row)
}

// The modal closes before the PATCH request resolves, so row id is stored here
const onUnlock = (payload: UnlockPayload) => {
  const rowId = rowIdToUnlock.value

  rowIdToUnlock.value = null
  unlockSegment(payload, () => applyUnlock(rowId, payload))
}

/*
 * Runs after the PATCH succeeded; mirrors the place/assignee chosen in the modal onto the row.
 * Looked up by id because the table hands slot scopes a copy of the row, not the reactive object.
 */
const applyUnlock = (rowId: string | null, { assignee, place }: UnlockPayload) => {
  const row = rows.value.find(candidate => candidate.id === rowId)

  if (!row) {
    return
  }

  row.placeId = place.id
  row.placeName = place.name
  row.locked = place.locked
  row.assigneeName = assignee.id === 'noAssigneeId' ? null : assignee.name

  // The filter would otherwise leave an empty table behind
  if (lockedCount.value === 0) {
    onlyLocked.value = false
  }
}

onMounted(() => {
  if (!canUnlock) {
    return
  }

  Promise.all([
    store.dispatch('Place/list', { fields: { Place: 'name,locked' } }),
    store.dispatch('AssignableUser/list', { sort: 'lastname' }),
  ]).then(() => {
    unlockOptionsLoaded.value = true
  })
})
</script>
