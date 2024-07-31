<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<!--
this component makes use of the fragment store: we get the initial data on mounted and then we use data directly from
the store (only with getter) to manipulate it in the store, and not in component through data or computed
because of the reactivity issue. Without it the component does not update correctly e.g. after deleteFragment mutation,
so now fragment counts and displayedFragments are set as method, instead of computed.
Hopefully in the future we will be able to refactor this and write it in a correct and readable way.
-->

<template>
  <div class="fragment-list">
    <dp-loading
      v-if="fragmentsLoading"
      class="u-p-0_5" />

    <p
      class="u-ph-0_5 u-pt-0_5"
      v-if="(hasOwnProp(fragmentsByStatement(statementId),'fragments') ? fragmentsByStatement(statementId).fragments.length : initialTotalFragmentsCount) === 0 && !fragmentsLoading ">
      {{ Translator.trans('no.fragments.available') }}
    </p>

    <p
      class="u-ph-0_5 u-pt-0_5"
      v-else-if="isFiltered && displayedFragments.length === 0 && !fragmentsLoading ">
      {{ Translator.trans('autocomplete.noResults') }}
    </p>

    <dp-assessment-fragment
      v-else
      v-for="(fragment, index) in displayedFragments"
      :class="{'border--bottom': displayedFragments.length - 1 > index}"
      :initial-fragment="fragment"
      :fragment-id="fragment.id"
      :key="fragment.displayId"
      :statement="statement"
      :procedure-id="procedureId"
      :current-user-id="currentUserId"
      :current-user-name="currentUserName" />
  </div>
</template>

<script>
import { DpLoading, hasOwnProp } from '@demos-europe/demosplan-ui'
import Fragment from './Fragment'
import { mapGetters } from 'vuex'

export default {
  components: {
    'dp-assessment-fragment': Fragment,
    DpLoading
  },

  props: {
    isFiltered: {
      type: Boolean,
      required: false,
      default: false
    },

    procedureId: {
      type: String,
      required: true
    },

    statementId: {
      type: String,
      required: true
    },

    currentUserId: {
      type: String,
      required: true
    },

    currentUserName: {
      type: String,
      required: true
    },

    lastClaimedUserId: {
      type: String,
      required: false,
      default: null
    },

    initialTotalFragmentsCount: {
      type: Number,
      required: false,
      default: 0
    },

    initialFilteredFragmentsCount: {
      type: Number,
      required: false,
      default: 0
    },

    fragmentsLoading: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  data () {
    return {
      showAll: this.isFiltered === false
    }
  },

  computed: {
    ...mapGetters('Fragment', ['fragmentsByStatement']),

    statementFragmentsFromStore () {
      return this.fragmentsByStatement(this.statementId) || {}
    },

    totalFragmentsCount () {
      return hasOwnProp(this.statementFragmentsFromStore, 'fragments') ? this.statementFragmentsFromStore.fragments.length : this.initialTotalFragmentsCount
    },

    filteredFragmentsCount () {
      return hasOwnProp(this.statementFragmentsFromStore, 'filteredFragments') ? this.statementFragmentsFromStore.filteredFragments.length : this.initialFilteredFragmentsCount
    },

    displayedFragments () {
      let displayedFragments = []
      if (this.isFiltered && this.filteredFragmentsCount === 0 && this.showAll === false) { // Filter is on but records are only in STN - then we show empty fragment list
        displayedFragments = []
      } else if (this.isFiltered && this.filteredFragmentsCount < this.totalFragmentsCount && this.showAll === false) { // Show only filtered fragments (search/filter records)
        const filteredFragmentsIds = this.statementFragmentsFromStore.filteredFragments.map(elem => elem.id)
        displayedFragments = this.statementFragmentsFromStore.fragments.filter(fragment => filteredFragmentsIds.includes(fragment.id))
      } else { // In all other cases show all fragments
        displayedFragments = this.statementFragmentsFromStore.fragments
      }

      return displayedFragments
    },

    statement () {
      return this.fragmentsByStatement(this.statementId).statement
    }
  },

  methods: {
    hasOwnProp (obj, prop) {
      return hasOwnProp(obj, prop)
    }
  },

  created () {
    //  Sync contents of child components on save
    this.$root.$on('fragment-saved', data => {
      this.$refs.considerationAdvice.content = data.considerationAdvice
      this.$refs.history.load()
    })
  }
}
</script>
