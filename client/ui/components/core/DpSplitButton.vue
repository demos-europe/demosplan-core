<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    v-click-outside="close"
    class="c-splitbutton">
    <slot>
      <button
        class="btn btn--primary"
        type="button">
        Primary Action Button
      </button>
    </slot><!--
 --><button
      v-if="hasDropdownContent"
      class="c-splitbutton__trigger"
      :class="{'is-open': isOpen}"
      type="button"
      aria-haspopup="true"
      :aria-expanded="isOpen"
      @click="toggleDropdown"
      @keyup.esc.prevent="isOpen ? isOpen = !isOpen : ''">
      <i class="fa fa-caret-down c-splitbutton__trigger-icon" />
      <span class="hide-visually">{{ Translator.trans(isOpen ? 'dropdown.close' : 'dropdown.open') }}</span>
    </button>
    <div
      v-if="hasDropdownContent"
      class="c-splitbutton__dropdown"
      role="menu"
      :class="{'is-open': isOpen}">
      <slot name="dropdown">
        Dropdown Actions
      </slot>
    </div>
  </div>
</template>

<script>
import ClickOutside from 'vue-click-outside'

export default {
  name: 'DpSplitButton',

  directives: {
    ClickOutside
  },

  data () {
    return {
      isOpen: false,
      hasDropdownContent: false
    }
  },

  methods: {
    close () {
      this.isOpen = false
    },

    toggleDropdown () {
      this.isOpen = (this.isOpen === false)
    }
  },

  mounted () {
    this.popupItem = this.$el
    this.hasDropdownContent = (typeof this.$slots.dropdown !== 'undefined')
  }
}
</script>
