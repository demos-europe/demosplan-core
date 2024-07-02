<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <!-- assessment table search and sorting header -->
  <div
    class="layout--flush u-1-of-1"
    v-cloak>
    <!-- info-box when elements are selected -->
    <dp-edit-selected-items-menu
      :procedure-id="procedureId"
      :current-user-id="currentUserId"
      :current-user-name="currentUserName"
      ref="editSelectedItemsMenu">
      <div class="flex items-center space-inline-m">
        <!-- Search field and advanced search button -->
        <search-modal
          ref="searchModal"
          :preselected-exact-search="exactSearch"
          :preselected-fields="searchFields"
          :table-search="searchTerm"
          is-form
          @close="setProperty({ prop: 'showSearchModal', val: false })" />

        <dp-filter-modal
          ref="filterModal"
          :applied-filter-options="appliedFilters"
          :filter-hash="initFilterHash"
          :procedure-id="procedureId"
          @close="setProperty({ prop: 'showFilterModal', val: false })" />

        <!-- Reset filters -->
        <div
          v-if="Object.keys(filterSet).length || searchFields.length || searchTerm.length"
          class="ml-auto">
          <dp-button
            :href="Routing.generate('dplan_assessmenttable_view_table', { procedureId: procedureId })"
            :text="Translator.trans('reset')"
            data-cy="reset"
            variant="outline" />
        </div>
      </div>
    </dp-edit-selected-items-menu>
  </div>
</template>

<script>
import { mapGetters, mapMutations, mapState } from 'vuex'
import { DpButton } from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'
import DpEditSelectedItemsMenu from '@DpJs/components/statement/assessmentTable/DpEditSelectedItemsMenu'

export default {
  name: 'SearchAndSorting',

  components: {
    DpButton,
    DpEditSelectedItemsMenu,
    DpFilterModal: defineAsyncComponent(() => import('@DpJs/components/statement/assessmentTable/DpFilterModal')),
    SearchModal: defineAsyncComponent(() => import('@DpJs/components/statement/assessmentTable/SearchModal/SearchModal'))
  },

  computed: {
    ...mapGetters('AssessmentTable', [
      'appliedFilters',
      'initFilterHash',
      'procedureId',
      'searchFields'
    ]),

    ...mapState('AssessmentTable', [
      'currentUserId',
      'currentUserName',
      'exactSearch',
      'filterSet',
      'searchTerm',
      'showFilterModal',
      'showSearchModal'
    ])
  },

  watch: {
    showFilterModal (val) {
      if (val) {
        this.$refs.filterModal.openModal()
      }
    },

    showSearchModal (val) {
      if (val) {
        this.$refs.searchModal.toggleModal()
      }
    }
  },

  methods: {
    ...mapMutations('AssessmentTable', [
      'setProperty'
    ])
  }
}
</script>
