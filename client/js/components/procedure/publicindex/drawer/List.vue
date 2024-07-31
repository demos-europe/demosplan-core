<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    v-if="procedures.length"
    class="u-mv-0_25"
    v-scroller>
    <ul
      class="u-m-0 u-pb-0_25"
      data-cy="procedureList">
      <dp-list-item
        v-for="procedure in procedures"
        :key="procedure.id"
        :procedure="procedure" />
    </ul>
  </div>
</template>

<script>
import DpListItem from './ListItem'
import Scroller from '@DpJs/directives/scroller'

export default {
  name: 'DpList',

  components: {
    DpListItem
  },

  directives: {
    /*
     * The "v-scroller" directive sets max-height on an element so that it does not exceed the viewport,
     * then it applies a utility class that makes that container scrollable.
     */
    scroller: Scroller
  },

  computed: {
    procedures () {
      return this.$store.state.Procedure.procedures
    }
  }
}
</script>
