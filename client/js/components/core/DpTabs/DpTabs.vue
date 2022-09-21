<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <ul
      class="flex space-inline-m list-style-none border--bottom"
      role="tablist">
      <li
        v-for="(tab, idx) in tabs"
        :key="`tab:${idx}`"
        :class="{ 'is-active': tab.isActive }"
        style="margin-bottom: -1px;">
        <button
          role="tab"
          class="btn--blank o-link--default u-pb-0_5 border--bottom"
          :class="[
            tab.isActive ? 'color-ui-interactive border-interactive' : 'border--none color-ui-dimmed-text',
            tabSize === 'large' ? 'font-size-larger' : 'font-size-large'
          ]"
          :aria-selected="tab.isActive"
          :aria-controls="tab.id"
          :data-cy="tab.id"
          @click.prevent="handleTabClick(tab.id)">
          {{ tab.label }}
          <span
            v-if="tab.suffix"
            v-cleanhtml="tab.suffix" />
        </button>
      </li>
    </ul>
    <div>
      <slot />
    </div>
  </div>
</template>

<script>
import { CleanHtml } from 'demosplan-ui/directives'

export default {
  name: 'DpTabs',

  directives: {
    cleanhtml: CleanHtml
  },

  model: {
    prop: 'activeId',
    event: 'change'
  },

  props: {
    activeId: {
      type: String,
      required: false,
      default: null
    },

    tabSize: {
      type: String,
      required: false,
      default: 'large',
      validator: (prop) => ['medium', 'large'].includes(prop)
    },

    /**
     * Active tab state may be persisted via an Url fragment. Also, ab clicks are pushed
     * to the browser history to enable state change when browser navigation is used.
     */
    useUrlFragment: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  data: () => ({
    tabs: []
  }),

  computed: {
    tabIds () {
      return this.tabs.map(tab => tab.id)
    }
  },

  methods: {
    /**
     * When users click the back button, the tabs should behave accordingly.
     */
    handleHashChange () {
      const hash = window.location.hash.slice(1)

      if (this.isTabId(hash)) {
        this.setActiveTab(hash)
      }
    },

    handleTabClick (id) {
      this.$emit('change', id)
      this.setActiveTab(id)

      if (this.useUrlFragment) {
        history.pushState(null, null, `#${id}`)
      }
    },

    /**
     * Check if a given string is a valid tab id.
     * @param id
     * @return {boolean}
     */
    isTabId (id) {
      return this.tabs.map(tab => tab.id).includes(id)
    },

    setActiveTab (id) {
      this.tabs.forEach(tab => {
        tab.isActive = (tab.id === id)
      })
    },

    /**
     * If a hash specifies a tab, activate it. If a tab is activated
     * via prop, activate that tab. Fallback to the first tab.
     */
    setInitialTab () {
      const hash = window.location.hash.slice(1)

      if (this.useUrlFragment && this.isTabId(hash)) {
        this.setActiveTab(hash)
      } else if (this.activeId) {
        this.setActiveTab(this.activeId)
      } else {
        this.tabs[0].isActive = true
      }
    }
  },

  created () {
    this.tabs = this.$children
  },

  mounted () {
    this.setInitialTab()

    if (this.useUrlFragment) {
      window.addEventListener('hashchange', this.handleHashChange)
    }
  },

  beforeDestroy () {
    if (this.useUrlFragment) {
      window.removeEventListener('hashchange', this.handleHashChange)
    }
  }
}
</script>
