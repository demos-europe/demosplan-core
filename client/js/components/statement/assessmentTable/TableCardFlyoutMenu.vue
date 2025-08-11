<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This is the component used in the Assessment Table as a menu for statement actions (original view, detail view, create fragment, move statement)
        and fragment actions (delete, assign to new user, fragment history). Before the refactor and new layout in AT the links were placed in table card footer. -->
</documentation>

<template>
  <dp-flyout
    :class="{'u-mr-0_5': entity === 'fragment'}"
    data-cy="flyoutMenu">
    <!-- Original statement view (statement entity only) -->
    <a
      v-if="entity === 'statement' && statementOriginalId"
      class="block leading-[2] whitespace-nowrap"
      :href="Routing.generate('dplan_assessmenttable_view_original_table', { procedureId: procedureId, fragment: `itemdisplay_${$parent.statement.originalId}` })"
      rel="noopener">
      {{ Translator.trans('statement.original') }}
    </a>

    <!-- Statement detail view (statement entity only) -->
    <a
      v-if="entity === 'statement'"
      class="block leading-[2] whitespace-nowrap"
      data-cy="detailView"
      :href="statementDetailPath"
      rel="noopener">
      {{ Translator.trans('detail.view') }}
    </a>

    <!-- Version history view -->
    <button
      type="button"
      class="btn--blank o-link--default leading-[2] whitespace-nowrap"
      v-if="hasPermission('feature_statement_content_changes_view')"
      @click.prevent="showVersionHistory"
      data-cy="versionHistory">
      {{ Translator.trans('history') }}
    </button>

    <!-- Heading to separate actions from views - show only if at least one of the menu items below is visible -->
    <h4
      v-if="hasActions"
      class="color--grey u-mb-0 u-mt-0_25 font-size-small">
      {{ Translator.trans('actions') }}
    </h4>

    <!-- Assign statement to other user (statement entity only) -->
    <button
      v-if="entity === 'statement' && hasPermission('feature_statement_assignment')"
      type="button"
      class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
      @click.prevent="toggleAssignEntityModal('statement', $parent.statement.assignee.id)">
      {{ Translator.trans('assignment.generic.assign.to.other') }}
    </button>

    <!-- Create fragments from statement (statement entity only) -->
    <a
      v-if="entity === 'statement' && hasPermission('feature_statements_fragment_add')"
      :aria-disabled="editable === false"
      class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
      :class="{'is-disabled': editable === false}"
      data-cy="createFragments"
      :href="editable ? Routing.generate('DemosPlan_statement_fragment',{ statementId: entityId, procedure: procedureId }) : false"
      rel="noopener"
      role="button">
      {{ Translator.trans('split.in.fragments') }}
    </a>

    <!-- Copy statement into other procedure (statement entity only) -->
    <button
      v-if="entity === 'statement' && hasPermission('feature_statement_copy_to_procedure')"
      type="button"
      class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
      :disabled="editable === false"
      @click.prevent="$emit('statement:copy', entityId)">
      {{ Translator.trans('copy.to.procedure') }}
    </button>

    <!-- Move statement to other procedure (statement entity only) -->
    <button
      v-if="entity === 'statement' && hasPermission('feature_statement_move_to_procedure') && isCluster === false"
      type="button"
      class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
      :disabled="editable === false"
      @click.prevent="$emit('statement:move', entityId)">
      {{ Translator.trans('move.to.procedure') }}
    </button>

    <!-- Delete fragment (fragment entity only) -->
    <button
      type="button"
      class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
      v-if="entity === 'fragment'"
      :disabled="fragmentAssigneeId !== currentUserId"
      :title="fragmentAssigneeId === currentUserId ? false : Translator.trans('locked.title')"
      v-on="fragmentAssigneeId === currentUserId ? { click: () => $emit('fragment-delete', entityId) } : {}">
      {{ Translator.trans('delete') }}
    </button>

    <!-- Assign fragment to other user (fragment entity only) -->
    <button
      v-if="entity === 'fragment' && hasPermission('feature_statement_assignment')"
      type="button"
      class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
      @click.prevent="toggleAssignEntityModal('fragment', fragmentAssigneeId)">
      {{ Translator.trans('assignment.generic.assign.to.other', { entity: Translator.trans('fragment') }) }}
    </button>
  </dp-flyout>
</template>

<script>
import { DpFlyout } from '@demos-europe/demosplan-ui'
import { mapMutations } from 'vuex'

export default {
  components: {
    DpFlyout
  },

  props: {
    currentUserId: {
      required: false,
      type: String,
      default: ''
    },

    editable: {
      required: false,
      type: Boolean,
      default: true
    },

    // Type of the entity (statement or fragment)
    entity: {
      required: true,
      type: String
    },

    // Id of the entity that the menu belongs to (statement or fragment)
    entityId: {
      required: true,
      type: String
    },

    // Needed if entity is a fragment
    externId: {
      required: false,
      type: String,
      default: ''
    },

    fragmentAssigneeId: {
      required: false,
      type: String,
      default: ''
    },

    isCluster: {
      required: false,
      type: [Boolean, String],
      default: true
    },

    statementDetailPath: {
      required: false,
      type: String,
      default: ''
    },

    // Needed if entity is a fragment
    statementId: {
      required: false,
      type: String,
      default: ''
    },

    statementOriginalId: {
      required: false,
      type: String,
      default: ''
    },

    /*
     * Needed for statement history view
     * we don't want to use current procedure id but the procedureId of the statement
     */
    statementProcedureId: {
      required: false,
      type: String,
      default: ''
    }
  },

  emits: [
    'fragment-delete',
    'show-slidebar',
    'statement:copy',
    'statement:move',
    'version:history'
  ],

  data () {
    return {
      procedureId: this.$store.state.Statement.procedureId
    }
  },

  computed: {
    hasActions () {
      return hasPermission('feature_statement_assignment') || hasPermission('feature_statements_fragment_add') || (hasPermission('feature_statement_move_to_procedure') && this.isCluster === false)
    }
  },

  methods: {
    ...mapMutations('AssessmentTable', [
      'setModalProperty'
    ]),

    showVersionHistory () {
      this.$root.$emit('version:history', this.entityId, this.entity, this.externId)
      this.$root.$emit('show-slidebar')
    },

    toggleAssignEntityModal (entity, assigneeId) {
      this.setModalProperty({
        prop: 'assignEntityModal',
        val: {
          entityId: this.entityId,
          entityType: entity,
          initialAssigneeId: assigneeId,
          parentStatementId: this.statementId,
          show: true
        }
      })
    }
  }
}
</script>
