<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component initializes a SideNav instance and contains a minimal template (mostly for the closing button)
    closing happens via data-slidebar-hide attribute; opening happens via emitted event instead of data-slidebar-show attribute
    because sidenav may be initialized before corresponding DOM elements exist

    Put content inside the default slot
   -->
</documentation>

<template>
  <div
    class="c-slidebar u-pr-0"
    data-slidebar="right">
    <div
      class="c-slidebar__container"
      data-slidebar-container=""
      data-cy="sidebarModal">
      <div class="u-ml-1_5">
        <button
          type="button"
          class="btn--blank o-link--default u-mt-0_5 u-n-ml u-mb u-p-0_25"
          @click="$emit('close')"
          data-slidebar-hide="">
          <dp-icon icon="close" />
        </button>
        <slot />
      </div>
    </div>
  </div>
</template>

<script>
import { hasOwnProp, SideNav } from 'demosplan-utils'
import { DpIcon } from 'demosplan-ui/components'

export default {
  name: 'DpSlidebar',

  components: {
    DpIcon
  },

  data () {
    return {
      sideNav: {}
    }
  },

  methods: {
    hideSlideBar () {
      if (hasOwnProp(this.sideNav, 'hideSideNav')) {
        this.sideNav.hideSideNav()
        this.$emit('close')
      }
    },

    showSlideBar () {
      if (hasOwnProp(this.sideNav, 'showSideNav')) {
        this.sideNav.showSideNav()
      }
    }
  },

  mounted () {
    // Initialize SideNav
    this.sideNav = new SideNav()

    this.$root.$on('hide-slidebar', () => {
      this.hideSlideBar()
    })

    this.$root.$on('show-slidebar', () => {
      this.showSlideBar()
    })
  }
}
</script>
