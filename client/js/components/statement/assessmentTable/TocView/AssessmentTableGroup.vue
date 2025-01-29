<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li class="c-at-item">
    <a
      class="o-link--offset"
      :id="`viewMode_${elementId}`" />

    <component
      v-if="depth > 0 && depth < 6"
      :is="headingTag"
      class="u-mt">
      {{ headingText }}
    </component>
    <ul class="o-list o-list--card">
      <dp-assessment-table-card
        :csrf-token="csrfToken"
        v-for="(statement, idx) in statementsInOrder(statementIds)"
        :key="idx"
        class="o-list__item"
        :is-selected="getSelectionStateById(statement.id)"
        :statement-id="statement.id"
        :statement-procedure-id="statement.procedureId"
        @statement:addToSelection="addToSelectionAction"
        @statement:removeFromSelection="removeFromSelectionAction" />
    </ul>

    <ul
      v-if="group.subgroups.length"
      class="o-list o-list--card">
      <assessment-table-group
        v-for="(subgroup, idx) in group.subgroups"
        :key="`subGroup:${idx}`"
        :group="subgroup"
        :parent-id="elementId" />
    </ul>
  </li>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import DpAssessmentTableCard from '../DpAssessmentTableCard'
import tocViewGroupMixin from './mixins/tocViewGroupMixin'

export default {
  name: 'AssessmentTableGroup',

  components: {
    DpAssessmentTableCard
  },

  mixins: [tocViewGroupMixin],

  props: {
    csrfToken: {
      type: String,
      required: true
    }
  },

  computed: {
    ...mapGetters('Statement', [
      'getSelectionStateById',
      'statementsInOrder'
    ]),

    /**
     * Used for the anchor to jump to the group by clicking on a heading in the TOC
     * @return {String}
     */
    elementId () {
      return this.parentId === '' ? this.group.title : `${this.parentId}_${this.group.title}`
    },

    headingTag () {
      return this.depth > 0 && this.depth < 6 ? `h${this.depth + 1}` : ''
    },

    headingText () {
      return this.depth === 1 ? `${this.group.title} (${this.group.total})` : this.group.title
    },

    /**
     * Returns array of statement ids
     * @return {Array<String>}
     */
    statementIds () {
      return this.group ? this.group.entries : []
    }
  },

  methods: {
    ...mapActions('Statement', [
      'addToSelectionAction',
      'removeFromSelectionAction'
    ])
  }
}
</script>
