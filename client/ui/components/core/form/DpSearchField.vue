<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <span :class="{ 'display--inline-block width-100p': inputWidth !== ''}">
    <dp-resettable-input
      id="searchField"
      data-cy="searchField"
      :class="cssClasses"
      :input-attributes="{ placeholder: Translator.trans('search'), type: 'search' }"
      @reset="handleReset"
      @enter="handleSearch"
      v-model="searchTerm" /><!--

 --><dp-button
      class="u-valign--top"
      data-cy="handleSearch"
      @click="handleSearch"
      :text="Translator.trans('searching')" />
  </span>
</template>

<script>
import { DpButton } from 'demosplan-ui/components'
import DpResettableInput from '../DpResettableInput'

export default {
  name: 'DpSearchField',

  components: {
    DpButton,
    DpResettableInput
  },

  props: {
    initSearchTerm: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Value has to be a css class like 'u-1-of-2'
     */
    inputWidth: {
      type: String,
      default: ''
    },

    placeholder: {
      type: String,
      required: false,
      default: 'search'
    }
  },

  data () {
    return {
      searchTerm: this.initSearchTerm,
      searchTermApplied: ''
    }
  },

  computed: {
    cssClasses () {
      return this.inputWidth !== '' ? `display--inline-block u-mr-0_5 ${this.inputWidth}` : 'display--inline-block u-mr-0_5'
    }
  },

  methods: {
    handleReset () {
      this.searchTerm = ''

      /*
       * Only emit reset if the searchTerm has been changed
       * The empty string is emitted to stick to only one type.
       */
      if (this.searchTermApplied !== this.searchTerm) {
        this.$emit('reset', '')
        this.searchTermApplied = ''
      }
    },

    handleSearch () {
      // Prevent emitting a searchTerm twice
      if (this.searchTermApplied === this.searchTerm) {
        return
      }

      this.searchTermApplied = this.searchTerm
      this.$emit('search', this.searchTerm)
    },

    reset () {
      this.searchTermApplied = ''
      this.searchTerm = ''
    }
  },

  mounted () {
    this.searchTermApplied = this.searchTerm
  }
}
</script>
