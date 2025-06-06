<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="c-publicindex__search c-search">
    <input
      v-model="searchTerm"
      :aria-label="Translator.trans('searchby.location.postalcode.phrase')"
      class="c-search__input"
      data-cy="searchZipCodeKeyword"
      :placeholder="Translator.trans('searchby.location.postalcode.phrase')"
      type="search"
      @blur="onBlur"
      @focus="onFocus"
      @keyup="search">

    <dp-loading
      v-if="isLoading"
      class="c-search__loading"
      hide-label />

    <div
      v-show="showSuggestions && locations.length && changed && !selected && focused"
      class="c-search__content-wrapper">
      <ul class="c-search__content">
        <li
          v-for="(location, idx) in locations"
          :key="idx"
          class="c-search__option"
          :value="location"
          @click="submit(location)">
          {{ location }}
        </li>
      </ul>
    </div>

    <button
      :aria-label="Translator.trans('search')"
      class="c-search__icon btn--blank absolute"
      :class="{ 'hidden': !changed }"
      data-cy="search:searchButton"
      type="button"
      @click="submit">
      <i
        aria-hidden="true"
        class="fa fa-search" />
    </button>

    <button
      :aria-label="Translator.trans('search.reset')"
      class="c-search__icon c-search__icon--reset btn--blank absolute"
      :class="{ 'hidden': !searchedAndNotChanged }"
      type="button"
      @click="reset">
      <svg
        fill=""
        height="24"
        viewBox="0 0 24 24"
        width="24"
        xmlns="http://www.w3.org/2000/svg">
        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
        <path
          d="M0 0h24v24H0z"
          fill="none" />
      </svg>
    </button>
  </div>
</template>

<script>
import { debounce, DpLoading } from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'

export default {
  name: 'DpSearch',

  components: {
    DpLoading
  },

  props: {
    showSuggestions: {
      type: Boolean,
      required: false,
      default: true
    }
  },

  emits: [
    'procedureSearch:focused'
  ],

  data () {
    return {
      focused: false,
      isLoading: false,
      query: '',
      searched: false,
      searchTerm: '',
      selected: false,
      value: ''
    }
  },

  computed: {
    ...mapState('Location', [
      'locations'
    ]),

    changed () {
      return this.searchTerm !== this.searched
    },

    searchedAndNotChanged () {
      return this.searched && !this.changed
    }
  },

  methods: {
    ...mapActions('Location', {
      getLocationSuggestions: 'get'
    }),

    ...mapActions('Procedure', {
      getProcedures: 'get'
    }),

    asyncFind: debounce(function (query) {
      if (this.query === query) {
        return false
      }

      this.selected = false
      this.query = query
      this.isLoading = true
      this.getLocationSuggestions({ query })
        .then(() => { this.isLoading = false })
    }, 500),

    /*
     * We need to wait for click event on location suggestions,
     * otherwise - in case the explicitOriginalTarget is an autosuggestion item - 'asyncFind' wouldn't get triggered
     */
    onBlur () {
      setTimeout(() => { this.focused = false }, 300)
    },

    onFocus () {
      this.focused = true
      this.$emit('procedureSearch:focused')
    },

    reset () {
      this.searchTerm = ''
      this.submit('')
    },

    search (e) {
      const key = e.key
      const value = e.target.value
      if (key === 'Enter') {
        this.submit(value)
      } else if (this.showSuggestions) {
        this.asyncFind(value)
      }
    },

    submit (value) {
      if (typeof value === 'string') {
        this.searchTerm = value
      }

      this.selected = true
      this.focused = false
      const searchTerm = this.searchTerm

      if (!this.changed) {
        return false
      }

      this.getProcedures({ search: searchTerm })
        .then(() => {
          this.searched = searchTerm
        })
    }
  }
}
</script>
