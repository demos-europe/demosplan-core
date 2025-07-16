<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="c-statement-meta-tooltip flow-root">
    <button
      v-if="toggleButton"
      class="btn--blank o-link--default float-right"
      data-cy="pinInformation"
      @click="$emit('toggle')">
      {{ Translator.trans('information.pin') }}
    </button>

    <div v-if="isSegmentWithPlace || isSegmentWithAssignee">
      <div class="weight--bold u-pb-0_5 u-pl-0">
        {{ Translator.trans('segment') }} {{ segment.attributes.externId }}
      </div>
      <dl class="description-list-inline u-mb-0_5">
        <template v-if="isSegmentWithPlace">
          <dt>{{ Translator.trans('workflow.place') }}:</dt>
          <dd>{{ segmentPlace.name }}</dd>
        </template>
        <template v-if="isSegmentWithAssignee">
          <dt>{{ Translator.trans('assigned.to') }}:</dt>
          <dd>{{ segmentAssignee.name }}</dd>
        </template>
      </dl>
    </div>

    <div class="weight--bold u-mt u-pb-0_5 u-pl-0">
      {{ Translator.trans('statement') }} {{ statement.attributes.externId }}
    </div>

    <statement-meta-data
      :statement="statement"
      :submit-type-options="submitTypeOptions">
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
        }">
        <dl class="description-list-inline u-mb-0_5">
          <dt>{{ Translator.trans('submitter') }}:</dt>
          <dd>{{ submitName }}</dd>
          <template v-if="!isSubmittedByCitizen">
            <dt>{{ Translator.trans('organisation') }}:</dt>
            <dd>{{ initialOrganisationName }}</dd>
            <dt>{{ Translator.trans('department') }}:</dt>
            <dd>{{ initialOrganisationDepartmentName }}</dd>
          </template>
          <dt>{{ Translator.trans('address') }}:</dt>
          <dd>{{ location }}</dd>
        </dl>

        <dl class="description-list-inline u-mb-0_5">
          <dt>{{ Translator.trans('internId') }}:</dt>
          <dd>{{ internId }}</dd>
          <dt>{{ Translator.trans('statement.date.authored') }}:</dt>
          <dd>{{ formattedAuthoredDate }}</dd>
          <dt>{{ Translator.trans('statement.date.submitted') }}:</dt>
          <dd>{{ formattedSubmitDate }}</dd>
          <dt>{{ Translator.trans('submit.type') }}:</dt>
          <dd>{{ submitType }}</dd>
        </dl>

        <dl
          class="description-list"
          v-if="hasPermission('field_statement_memo')">
          <dt>{{ Translator.trans('memo') }}:</dt>
          <dd class="max-h-12 overflow-auto">
            {{ memo }}
          </dd>
        </dl>
      </template>
    </statement-meta-data>
  </div>
</template>

<script>
import StatementMetaData from '@DpJs/components/statement/StatementMetaData'

export default {
  name: 'StatementMetaTooltip',

  components: {
    StatementMetaData
  },

  props: {
    // Array of objects with id and name
    assignableUsers: {
      type: Array,
      default: () => ([])
    },

    // Array of objects with id and name
    places: {
      type: Array,
      default: () => ([])
    },

    segment: {
      type: Object,
      default: () => ({})
    },

    statement: {
      type: Object,
      required: true
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    /**
     * Should the tooltip contain a small button to toggle content outside?
     */
    toggleButton: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  emits: [
    'toggle'
  ],

  computed: {
    isSegmentWithAssignee () {
      return Object.keys(this.segment).length && Object.keys(this.segmentAssignee).length
    },

    isSegmentWithPlace () {
      return Object.keys(this.segment).length && Object.keys(this.segmentPlace).length
    },

    segmentAssignee () {
      return this.segment?.relationships?.assignee?.data
        ? this.assignableUsers.find(user => user.id === this.segment.relationships.assignee.data.id)
        : {}
    },

    // Object with id and name
    segmentPlace () {
      return Object.keys(this.segment).length && this.segment.relationships.place
        ? this.places.find(place => place.id === this.segment.relationships.place.data.id)
        : {}
    }
  }
}
</script>
