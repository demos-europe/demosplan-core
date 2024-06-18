<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<!--
This is the switcher between statements/fragments in the assessment table card.
Parent: DpAssessmentTableCard.vue
Children: this component is the wrapper for the generic DpSwitcher.

DpSwitcher has two slots (option1 and option2) which need to be defined here.

This component emits an event on switch (emitted by generic dp-switch), and the whole logic of toggling between
statements and fragments is now in DpAssessmentTableCard.vue
-->

<template>
  <div>
    <div class="o-switcher">
      <input
        type="checkbox"
        :id="'switcher' + statementId"
        @change="toggle">
      <label :for="'switcher' + statementId">
        <span
          class="o-switcher__option float-left"
          :class="{'o-switcher__option--checked': statementTabVisible}">
          {{ Translator.trans('statement') }}
        </span>
        <span
          class="o-switcher__option float-right"
          :class="{'o-switcher__option--checked': !statementTabVisible}"
          data-cy="fragmentTab">
          {{ fragmentsButtonText }}
        </span>
      </label>
    </div>
    <div
      v-if="isFiltered && showFragmentResults"
      class="inline-block align-top u-pv-0_25 float-right">
      <p class="inline-block u-mb-0 u-mr">
        {{ Translator.trans('found.fragments', {hits: filteredFragmentsLength, sum: totalFragmentsLength}) }}
      </p>
      <label
        :for="'show-all-fragments' + statementId"
        class="inline-block u-mb-0">
        <input
          type="checkbox"
          name="show-all-fragments"
          :id="'show-all-fragments' + statementId"
          @click="showAllFragments">
        {{ Translator.trans('show.all.fragments', { sum: totalFragmentsLength }) }}
      </label>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'

export default {
  name: 'DpFragmentsSwitcher',

  props: {
    statementId: {
      required: true,
      type: String
    },

    statementFragmentsTotal: {
      required: false,
      type: Number,
      default: 0
    },

    statementFragmentsLength: {
      required: false,
      type: Number,
      default: 0
    },

    isFiltered: {
      required: false,
      type: Boolean,
      default: false
    },

    statementTabVisible: { // Shows if tab === 'statement' in tableCard.vue
      required: false,
      type: Boolean,
      default: true
    }
  },

  data () {
    return {
      allFragmentsShown: false
    }
  },

  computed: {
    ...mapGetters('Fragment', ['fragmentsByStatement', 'selectedFragments']),

    ...mapGetters('Filter', ['selectedFilterOptions']),

    ...mapState('AssessmentTable', ['assessmentBase']),

    ...mapState('Filter', ['currentSearch']),

    ...mapState('Fragment', ['fragments']),

    filteredFragmentsLength () {
      if (!this.fragments[this.statementId]) {
        return this.statementFragmentsLength
      } else if (this.fragments[this.statementId] && this.fragments[this.statementId].filteredFragments) {
        return this.fragments[this.statementId].filteredFragments.length
      } else if (this.fragments[this.statementId] && !this.fragments[this.statementId].filteredFragments) {
        return 0
      }
      return 0
    },

    /**
     * Generate label text for fragments-tab, including selected and filtered fragments
     */
    fragmentsButtonText () {
      let text = Translator.trans('fragments')
      const selectedFragmentsLength = Object.values(this.selectedFragments).filter(frag => frag.statementId === this.statementId).length
      text += ' ('
      if (this.isFiltered && this.showFragmentResults) {
        text += `${this.filteredFragmentsLength}/`
      }
      text += `${this.totalFragmentsLength}`
      if (Object.values(this.selectedFragments).filter(frag => frag.statementId === this.statementId).length > 0) {
        text += `, ${selectedFragmentsLength} ${Translator.trans('chosen.lowercase')}`
      }
      text += ')'
      return text
    },

    /**
     * Determine whether to show the "Die Suche ergab X Treffer in Y Datensätzen" UI.
     * This should happen if results are filtered, or a search term is applied.
     * @type {boolean}
     */
    showFragmentResults () {
      return this.hasFragmentFilters(this.assessmentBase.appliedFilters) || this.currentSearch.length > 0
    },

    totalFragmentsLength () {
      if (!this.fragments[this.statementId]) {
        return this.statementFragmentsTotal
      } else if (this.fragments[this.statementId] && this.fragments[this.statementId].fragments) {
        return this.fragments[this.statementId].fragments.length
      }

      return 0
    }
  },

  methods: {
    hasFragmentFilters (filters) {
      return filters.length ? filters.some(filter => filter.type === 'fragment') : false
    },

    showAllFragments () {
      this.allFragmentsShown = !this.allFragmentsShown
      this.$emit('fragments:showall', this.allFragmentsShown)
    },

    toggle () {
      this.$emit('toggletabs', (this.statementTabVisible === false))
    }
  }
}
</script>
