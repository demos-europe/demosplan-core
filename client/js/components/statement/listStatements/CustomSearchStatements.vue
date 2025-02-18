<template>
  <div>
    <dp-search-field
      data-cy="customSearchStatements:search"
      :placeholder="Translator.trans('searchterm')"
      @search="term => handleSearch(term)"
      @reset="$emit('reset')">
      <template v-slot:default>
        <dp-flyout
          align="left"
          data-cy="customSearch:searchCustomLimitFields"
          class="top-px right-0 absolute"
          :has-menu="false"
          :padded="false">
          <template v-slot:trigger>
            <dp-icon
              icon="settings" />
          </template>
          <h1>Test</h1>
        </dp-flyout>
      </template>
    </dp-search-field>
  </div>
</template>
<script>

import {
  DpCheckbox,
  DpDetails,
  DpFlyout,
  DpIcon,
  DpSearchField
} from '@demos-europe/demosplan-ui'

export default {
  name: 'CustomSearchStatements',
  components: {
    DpCheckbox,
    DpDetails,
    DpFlyout,
    DpIcon,
    DpSearchField
  },
  data: () => ({
    currentSearchTerm: ''
  }),
  methods: {
    handleSearch (term) {
      this.currentSearchTerm = term
      this.$emit('search', this.currentSearchTerm)
    },
    reset () {
      this.currentSearchTerm = ''
      this.$emit('reset')
      // this.availableFilterFields.forEach(checkbox => {
      //   checkbox.checked = false
      // })
      // localStorage.removeItem('selectedCheckboxes')
    }
  }
}
</script>


