<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    class="c-publicindex__drawer absolute u-top-0 z-above-zero shadow-md"
    :class="{ 'is-open': isDrawerOpened }">
    <div class="bg-color--grey-light-2 u-p-0_5">
      <dp-search
        @procedureSearch:focused="openDrawer"
        :show-suggestions="false" />
      <template v-if="!isLoading">
        <dp-handle
          data-cy="drawerToggle"
          :is-open="isDrawerOpened"
          @input="val => setProperty({ prop: 'isDrawerOpened', val: val })" />
        <div class="c-publicindex__drawer-nav">
          <strong
            v-if="currentView !== 'DpDetailView'"
            aria-live="assertive"
            class="inline-block"
            data-cy="participationProcedures">
            {{ procedureCount }} {{ Translator.trans('participation.procedures') }}
          </strong>
          <dp-content-toggle
            v-else
            @input="val => setProperty({ prop: 'currentView', val: val })" />
        </div>
      </template>
    </div>

    <div class="bg-color--white u-p-0_5">
      <dp-loading v-if="isLoading" />
      <component
        v-else
        :is="currentView"
        :procedure="procedureInDetailView" />
    </div>
  </div>
</template>

<script>
import { DpLoading, MatchMedia } from '@demos-europe/demosplan-ui'
import { mapGetters, mapMutations, mapState } from 'vuex'
import DpContentToggle from './ContentToggle'
import DpDetailView from './DetailView'
import DpHandle from './Handle'
import DpList from './List'
import DpSearch from './Search'

export default {
  name: 'DpDrawer',

  components: {
    DpSearch,
    DpContentToggle,
    DpHandle,
    DpList,
    DpLoading,
    DpDetailView
  },

  computed: {
    ...mapGetters('Procedure', [
      'currentProcedureId',
      'currentView',
      'isDrawerOpened',
      'isLoading'
    ]),

    ...mapState('Procedure', [
      'procedures'
    ]),

    procedureCount () {
      return this.procedures.length
    },

    procedureInDetailView () {
      if (!this.currentProcedureId) {
        return null
      }
      const curr = this.procedures.find(el => el.id === this.currentProcedureId)
      return curr || null
    }
  },

  methods: {
    ...mapMutations('Procedure', [
      'setProperty'
    ]),

    openDrawer () {
      this.setProperty({ prop: 'isDrawerOpened', val: true })
    },

    toggleList () {
      const val = this.currentView !== 'DpList' ? 'DpList' : ''
      this.setProperty({ prop: 'currentView', val })
    }
  },

  created () {
    const matchMedia = new MatchMedia()
    const currentBreakpoint = matchMedia.getCurrentBreakpoint()
    // Don't show the procedure list on mobile by default
    if (currentBreakpoint === 'palm') {
      this.setProperty({ prop: 'isDrawerOpened', val: false })
    }
  }
}
</script>
