<template>
  <li
    :class="{'is-expanded': isOpen}"
    v-cloak>
    <!--  item header  -->
    <div
      data-add-animation>
      <slot name="header" />
    </div>
    <!--  item content - hidden with table-cards:toggle-view 'collapsed' (List view)  -->
    <transition name="fading">
      <div
        v-show="isOpen"
        class="u-p-0_5">
        <slot />
      </div>
    </transition>
  </li>
</template>

<script>

export default {
  name: 'DpTableCard',

  props: {
    open: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  data () {
    return {
      isOpen: this.open
    }
  },

  watch: {
    open: {
      handler (newVal) {
        this.isOpen = newVal
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    }
  },

  methods: {
    toggle (val) {
      this.isOpen = (typeof val !== 'undefined') ? val : (this.isOpen === false)
    }
  }
}
</script>
