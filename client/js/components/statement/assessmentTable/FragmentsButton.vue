<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <a
    class="c-at-item__tab-trigger o-link--icon inline-block u-ph"
    :class="{
      'u-pv-0_5': !showFragmentsCount,
      'c-at-item__fragment-hits': showFragmentsCount,
      'pointer-events-none': !assessmentBaseLoaded,
      'is-active-toggle': active
    }"
    :href="'#' + statementId + '_fragments'"
    @click="loadFragments(statementId)">
    <i
      class="fa fa-sitemap"
      aria-hidden="true" />
    {{ Translator.trans(title) }}
    <span
      v-if="showFragmentsCount"
      class="font-size-smaller text-center block"
      style="margin-top: -4px;">
      ({{ filteredFragmentsLength }} {{ Translator.trans('hits') }})
    </span>
  </a>
</template>

<script>
import { hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapGetters } from 'vuex'

export default {
  name: 'DpFragmentsButton',

  props: {
    active: {
      required: true,
      type: Boolean
    },

    statementId: {
      required: true,
      type: String
    },

    statementFragmentsTotal: {
      required: false,
      type: String,
      default: '0'
    },

    statementFragmentsLength: {
      required: false,
      default: '0',
      type: String
    },

    title: {
      required: false,
      default: 'fragments',
      type: String
    }
  },

  emits: [
    'fragments:show',
    'fragments:load'
  ],

  computed: {
    ...mapGetters('Fragment', ['fragmentsByStatement']),

    assessmentBaseLoaded () {
      if (hasOwnProp(this.$store.state, 'assessmentTable')) {
        return this.$store.state.AssessmentTable.assessmentBaseLoaded
      }
      return true
    },

    showFragmentsCount () { return (this.statementFragmentsTotal !== '0') },

    filteredFragmentsLength () {
      if (!this.$store.state.Fragment.fragments[this.statementId]) {
        return this.statementFragmentsLength
      } else if (this.$store.state.Fragment.fragments[this.statementId] && this.$store.state.Fragment.fragments[this.statementId].filteredFragments) {
        return this.$store.state.Fragment.fragments[this.statementId].filteredFragments.length
      } else {
        return 0
      }
    }
  },

  methods: {
    loadFragments (statementId) {
      /*
       *  If first argument is an Object, we assume the function is called without arguments
       *  and the event becomes the first argument (when called from actionmenu)
       */
      statementId = typeof statementId === 'object' ? false : statementId
      if (!this.$store.state.Fragment.fragments[this.statementId]) {
        // Load statements only if the button is clicked for the first time, because then they are stored in fragment store
        this.$root.$emit('fragments:load', statementId)
      }

      this.$emit('fragments:show', statementId)
    }
  }
}
</script>
