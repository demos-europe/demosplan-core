<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-multiselect
      id="searchSelect"
      class="cursor-pointer"
      :clear-on-select="false"
      :close-on-select="false"
      label="title"
      :options="filteredOptions"
      :placeholder="placeHolder"
      :preserve-search="true"
      selection-controls
      track-by="title"
      :use-default-search="false"
      @input="updateSelected"
      @search-change="updateSearchValue"
    >
      <template v-slot:option="{ props }">
        <input
          :id="`tag_${props.option.id}`"
          type="checkbox"
          :checked="typeof selected.find(el => el.id === props.option.id || el.attributes.title === props.option.title) !== 'undefined'"
          :value="props.option.id"
        >
        <label
          class="pointer-events-none"
          :for="`tag_${props.option.id}`"
        >
          {{ props.option.title }}
        </label>
      </template>
      <template v-slot:beforeList>
        <button
          class="btn--blank o-link--default weight--bold u-ph-0_5 u-pv-0_5 text-left u-1-of-1 whitespace-nowrap"
          @click="$emit('openCreateForm')"
        >
          {{ Translator.trans('tag.topic.new') }}
        </button>
      </template>
    </dp-multiselect>
  </div>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import { DpMultiselect } from '@demos-europe/demosplan-ui'

export default {
  name: 'SearchSelect',

  components: {
    DpMultiselect,
  },

  props: {
    options: {
      type: Array,
      required: true,
    },

    placeHolder: {
      type: String,
      default: '',
    },

    selected: {
      type: Array,
      default: () => ([]),
    },
  },

  data () {
    return {
      search: '',
    }
  },

  emits: [
    'openCreateForm',
  ],

  computed: {
    ...mapGetters('SplitStatement', {
      availableTags: 'availableTags',
      currentSegment: 'editingSegment',
      tagById: 'tagById',
      categorizedTags: 'categorizedTags',
    }),

    filteredOptions () {
      const searchValue = this.search.toLowerCase()

      if (!searchValue) {
        return this.options
      }

      const matches = this.options.filter(option =>
        option.title.toLowerCase().includes(searchValue),
      )

      /**
       * Sorting:
       * - prioritize items whose titles START with the search value
       * - followed by items that only CONTAIN the search value
       */
      return matches.sort((a, b) => {
        const aTitle = a.title.toLowerCase()
        const bTitle = b.title.toLowerCase()

        const aStarts = aTitle.startsWith(searchValue)
        const bStarts = bTitle.startsWith(searchValue)

        if (aStarts && !bStarts) {
          return -1
        }

        if (!aStarts && bStarts) {
          return 1
        }

        /* If both items match equally well, sort them alphabetically */
        return aTitle.localeCompare(bTitle, 'de')
      })
    },
  },

  methods: {
    ...mapActions('SplitStatement', [
      'updateCurrentTags',
    ]),

    updateSearchValue (value) {
      this.search = value.toLowerCase()
    },

    /**
     * @typedef {Object} Tag
     * @property {string} id
     * @property {string} title
     */

    /**
     * Update tags assigned to the segment currently being edited.
     * @param {Tag} tag
     */
    updateSelected (tag) {
      this.updateCurrentTags({ id: tag.id, tagName: tag.title })
    },
  },
}
</script>
