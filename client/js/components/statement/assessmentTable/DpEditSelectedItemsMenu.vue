<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <!-- second template is needed because we have two root-elements-->
  <div>
    <div
      v-if="areElementsSelected"
      role="menu"
      class="layout__item u-12-of-12 u-p-0_5 bg-color-selection line-height--1_6">
      <span class="u-mr">
        {{ selectedItemsText }}
      </span>

      <component
        :is="selectedItemsComponent"
        :procedure-id="procedureId"
        :current-user-id="currentUserId"
        :current-user-name="currentUserName"
        @consolidateStatements="$root.$emit('consolidateStatements')"
      />

      <dp-button
        class="float-right"
        data-cy="editSelectedItemsMenu:unselect"
        :text="Translator.trans('unselect')"
        variant="outline"
        @click="resetSelection" />
    </div>
    <!--this slot is needed for the search field and filter modal etc.-->
    <div v-show="!areElementsSelected">
      <slot />
    </div>
  </div>
</template>

<script>
import { mapGetters, mapMutations, mapState } from 'vuex'
import { capitalizeFirstLetter, DpButton } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpEditSelectedItemsMenu',

  components: {
    DpButton,
    DpSelectedItemsStatements: () => import(/* webpackChunkName: "dp-selected-items-statements" */ './DpSelectedItemsStatements'),
    DpSelectedItemsFragments: () => import(/* webpackChunkName: "dp-selected-items-fragments" */ './DpSelectedItemsFragments')
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
    ...mapGetters('Statement', {
      selectedStatementsLength: 'selectedElementsLength'
    }),

    ...mapGetters('Fragment', [
      'selectedFragmentsLength'
    ]),

    ...mapState('Statement', [
      'statements'
    ]),

    areElementsSelected () {
      return this.selectedStatementsLength > 0 || this.areFragmentsSelected
    },

    areFragmentsSelected () {
      return this.selectedFragmentsLength > 0
    },

    selectedItemsComponent () {
      if (hasPermission('area_statements_fragment') && this.areFragmentsSelected) {
        return 'dpSelectedItemsFragments'
      }

      return 'dpSelectedItemsStatements'
    },

    selectedItemsText () {
      if (this.visibleEntityType === 'statement') {
        return Translator.trans('statements.selected', { count: this.selectedStatementsLength })
      } else {
        // We have to use the length of selectedFragments from sessionStorage to indicate that also fragments that are not loaded at this point (so from other STN) may be checked
        const selectedFragmentsInSessionStorage = JSON.parse(sessionStorage.getItem('selectedFragments'))
        return Translator.trans('fragments.selected', { count: Object.keys(selectedFragmentsInSessionStorage[this.procedureId]).length })
      }
    },

    visibleEntityType () {
      if (this.selectedStatementsLength > 0) {
        return 'statement'
      } else if (this.areFragmentsSelected) {
        return 'fragment'
      } else {
        return 'statement'
      }
    }
  },

  methods: {
    resetSelection () {
      this.$store.dispatch(`${capitalizeFirstLetter(this.visibleEntityType)}/resetSelection`)
    },
    ...mapMutations('Statement', ['updateStatement'])
  }
}
</script>
