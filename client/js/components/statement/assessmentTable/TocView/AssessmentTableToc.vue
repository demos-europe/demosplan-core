<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <assessment-table-toc-group :group="getToc" />

    <!-- Update button -->
    <transition
      name="slide-fade"
      mode="out-in">
      <dp-button
        v-show="isRefreshButtonVisible"
        class="u-ml-0_5 u-mb-0_5"
        icon="refresh"
        :text="Translator.trans('refresh')"
        variant="outline"
        @click="triggerUpdate" />
    </transition>
  </div>
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'
import AssessmentTableTocGroup from './AssessmentTableTocGroup'
import { DpButton } from '@demos-europe/demosplan-ui'

export default {
  name: 'AssessmentTableToc',

  components: {
    AssessmentTableTocGroup,
    DpButton
  },

  props: {
    filterHash: {
      type: String,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  computed: {
    ...mapGetters('AssessmentTable', [
      'isRefreshButtonVisible'
    ]),

    ...mapGetters('Statement', [
      'getToc'
    ])
  },

  methods: {
    ...mapMutations('AssessmentTable', [
      'setRefreshButtonVisibility'
    ]),

    triggerUpdate () {
      this.setRefreshButtonVisibility(false)
      /*
       *  Update-assessment-table is defined in mounted() of DpTable.vue.
       *  Otherwise triggerApiCallForStatements() would not be accessible in AssessmentTableToc which refreshes the assessment list without page reload
       */
      this.$root.$emit('update-assessment-table')
    }
  }
}
</script>
