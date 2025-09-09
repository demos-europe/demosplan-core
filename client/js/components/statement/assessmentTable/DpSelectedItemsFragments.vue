<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <!-- second template is needed because we have two root-elements-->
  <div class="inline-block">
    <!--if all items are claimed and at least one statement in this procedure is chosen, go to group edit or if claiming is not enabled in project -->
    <a
      class="o-link--default u-mr-0_5"
      role="button"
      :href="editable ? Routing.generate('dplan_assessment_table_assessment_table_statement_fragment_bulk_edit', { procedureId: procedureId}) : false"
      :title="false === editable ? Translator.trans('locked.title') : false"
      :aria-disabled="false === editable">
      <i
        aria-hidden="true"
        class="fa fa-pencil u-mr-0_125" />
      {{ Translator.trans('edit') }}
    </a>
    <button
      class="btn--blank o-link--default u-mr-0_5"
      @click.prevent="$emit('exportModal:toggle', 'docx')">
      <i
        aria-hidden="true"
        class="fa fa-share-square u-mr-0_125" />
      {{ Translator.trans('export') }}
    </button>
  </div>
</template>

<script>
import { mapGetters, mapMutations, mapState } from 'vuex'

export default {
  name: 'DpSelectedItemsFragments',

  components: {
  },

  props: {
    procedureId: {
      required: true,
      type: String
    },

    currentUserId: {
      required: false,
      type: String,
      default: ''
    },

    currentUserName: {
      required: false,
      type: String,
      default: ''
    }
  },

  data () {
    return {
      loading: false
    }
  },

  computed: {
    ...mapGetters('Fragment', ['selectedFragments']),
    ...mapState('Statement', ['statements']),

    editable () {
      return hasPermission('feature_statement_assignment') ? Object.values(this.selectedFragments).every(fragment => fragment.assignee.id === this.currentUserId) : true
    }
  },

  methods: {
    ...mapMutations('Statement', ['updateStatement'])
  }
}
</script>
