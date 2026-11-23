<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="w-[480px] max-w-full">
    <div
      v-if="hasSegmentSection"
      class="p-3"
    >
      <div class="mb-1 flex items-start justify-between gap-2">
        <p class="mb-0 font-semibold">
          {{ Translator.trans('segment') }} {{ segment.attributes.externId }}
        </p>
        <button
          v-if="toggleButton"
          class="btn--blank o-link--default"
          data-cy="pinInformation"
          @click="$emit('toggle')"
        >
          {{ Translator.trans('information.pin') }}
        </button>
      </div>
      <dl class="mb-0 grid [&>dd]:ml-0 grid-cols-[2fr_3fr] gap-x-3 gap-y-0.5">
        <template v-if="isSegmentWithPlace">
          <dt class="text-muted">
            {{ Translator.trans('workflow.place') }}
          </dt>
          <dd>{{ segmentPlace.name }}</dd>
        </template>
        <template v-if="isSegmentWithAssignee">
          <dt class="text-muted">
            {{ Translator.trans('assignee') }}
          </dt>
          <dd>{{ segmentAssignee.name }}</dd>
        </template>
      </dl>
    </div>

    <div
      class="p-3"
      :class="{ 'border-t border-neutral-light-3': hasSegmentSection }"
    >
      <div class="mb-1 flex items-start justify-between gap-2">
        <p class="mb-0 font-semibold">
          {{ Translator.trans('statement') }}
        </p>
        <button
          v-if="toggleButton && !hasSegmentSection"
          class="btn--blank o-link--default"
          data-cy="pinInformation"
          @click="$emit('toggle')"
        >
          {{ Translator.trans('information.pin') }}
        </button>
      </div>

      <statement-meta-data
        :statement="statement"
        :submit-type-options="submitTypeOptions"
      >
        <template
          v-slot:default="{
            formattedAuthoredDate,
            formattedSubmitDate,
            isSubmittedByCitizen,
            initialOrganisationDepartmentName,
            initialOrganisationName,
            internId,
            memo,
            submitName,
            submitType,
            location
          }"
        >
          <dl class="mb-0 grid [&>dd]:ml-0 grid-cols-[2fr_3fr] gap-x-3 gap-y-0.5">
            <dt class="text-muted">
              {{ Translator.trans('submitter') }}
            </dt>
            <dd>{{ valueOrDash(submitName) }}</dd>
            <template v-if="!isSubmittedByCitizen">
              <dt class="text-muted">
                {{ Translator.trans('institution') }}
              </dt>
              <dd>{{ valueOrDash(initialOrganisationName) }}</dd>
              <dt class="text-muted">
                {{ Translator.trans('department') }}
              </dt>
              <dd>{{ valueOrDash(initialOrganisationDepartmentName) }}</dd>
            </template>
            <dt class="text-muted">
              {{ Translator.trans('address') }}
            </dt>
            <dd>{{ valueOrDash(location) }}</dd>
            <dt class="text-muted">
              {{ Translator.trans('internId') }}
            </dt>
            <dd>{{ valueOrDash(internId) }}</dd>
            <dt class="text-muted">
              {{ Translator.trans('statement.date.authored') }}
            </dt>
            <dd>{{ valueOrDash(formattedAuthoredDate) }}</dd>
            <dt class="text-muted">
              {{ Translator.trans('statement.date.submitted') }}
            </dt>
            <dd>{{ valueOrDash(formattedSubmitDate) }}</dd>
            <dt class="text-muted">
              {{ Translator.trans('submit.type') }}
            </dt>
            <dd>{{ valueOrDash(submitType) }}</dd>
            <template v-if="hasPermission('field_statement_memo')">
              <dt class="text-muted">
                {{ Translator.trans('memo') }}
              </dt>
              <dd class="max-h-12 overflow-auto">
                {{ valueOrDash(memo) }}
              </dd>
            </template>
          </dl>
        </template>
      </statement-meta-data>
    </div>
  </div>
</template>

<script>
import StatementMetaData from '@DpJs/components/statement/StatementMetaData.vue'

export default {
  name: 'StatementMetaTooltip',

  components: {
    StatementMetaData,
  },

  props: {
    // Array of objects with id and name
    assignableUsers: {
      type: Array,
      default: () => ([]),
    },

    // Array of objects with id and name
    places: {
      type: Array,
      default: () => ([]),
    },

    segment: {
      type: Object,
      default: () => ({}),
    },

    statement: {
      type: Object,
      required: true,
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => [],
    },

    /**
     * Should the tooltip contain a small button to toggle content outside?
     */
    toggleButton: {
      type: Boolean,
      required: false,
      default: false,
    },
  },

  emits: [
    'toggle',
  ],

  computed: {
    hasSegmentSection () {
      return Boolean(this.isSegmentWithPlace || this.isSegmentWithAssignee)
    },

    isSegmentWithAssignee () {
      return Object.keys(this.segment).length && Object.keys(this.segmentAssignee).length
    },

    isSegmentWithPlace () {
      return Object.keys(this.segment).length && Object.keys(this.segmentPlace).length
    },

    segmentAssignee () {
      if (!this.segment?.relationships?.assignee?.data || !Array.isArray(this.assignableUsers)) {
        return {}
      }

      return this.assignableUsers.find(user => user.id === this.segment.relationships.assignee.data.id) || {}
    },

    // Object with id and name
    segmentPlace () {
      if (!this.segment?.relationships?.place?.data || !Array.isArray(this.places)) {
        return {}
      }

      return this.places.find(place => place.id === this.segment.relationships.place.data.id) || {}
    },
  },

  methods: {
    valueOrDash (value) {
      return value || '–'
    },
  },
}
</script>
