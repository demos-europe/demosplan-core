<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- Binds DpSlidebar to the SegmentSlidebar store.
    The procedure-wide segment list renders its slidebar from the twig template, where no parent
    component exists to hold the open state and no store value can be bound to a prop. This wrapper
    provides that parent, so the list and the panels inside the slidebar communicate through the
    store instead of root events.
   -->
</documentation>

<template>
  <dp-slidebar
    :open="slidebar.isOpen"
    @close="closeSlidebar"
  >
    <slot />
  </dp-slidebar>
</template>

<script>
import { mapMutations, mapState } from 'vuex'
import { DpSlidebar } from '@demos-europe/demosplan-ui'

export default {
  name: 'SegmentSlidebar',

  components: {
    DpSlidebar,
  },

  computed: {
    ...mapState('SegmentSlidebar', [
      'slidebar',
    ]),
  },

  methods: {
    ...mapMutations('SegmentSlidebar', [
      'setContent',
    ]),

    /**
     * Also triggered by the close button inside DpSlidebar, so the store does not keep a panel
     * marked as open after the user dismissed it.
     */
    closeSlidebar () {
      this.setContent({ prop: 'slidebar', val: { externId: '', isOpen: false, segmentId: '', showTab: '' } })
    },
  },
}
</script>
