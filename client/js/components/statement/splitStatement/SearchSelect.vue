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
      :options="options"
      :placeholder="placeHolder"
      :preserve-search="true"
      selection-controls
      track-by="title"
      @input="updateSelected">
      <template v-slot:option="{ props }">
        <input
          type="checkbox"
          :id="`tag_${props.option.id}`"
          :checked="typeof selected.find(el => el.id === props.option.id || el.attributes.title === props.option.title) !== 'undefined'"
          :value="props.option.id">
        <label
          class="pointer-events-none"
          :for="`tag_${props.option.id}`">
          {{ props.option.title }}
        </label>
      </template>
      <template v-slot:beforeList>
        <button
          @click="$emit('open-create-form')"
          class="btn--blank o-link--default weight--bold u-ph-0_5 u-pv-0_5 text-left u-1-of-1 whitespace-nowrap">
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
    DpMultiselect
  },

  props: {
    options: {
      type: Array,
      required: true
    },

    placeHolder: {
      type: String,
      default: ''
    },

    selected: {
      type: Array,
      default: () => ([])
    }
  },

  emits: [
    'open-create-form'
  ],

  computed: {
    ...mapGetters('SplitStatement', {
      availableTags: 'availableTags',
      currentSegment: 'editingSegment',
      tagById: 'tagById',
      categorizedTags: 'categorizedTags'
    })
  },

  methods: {
    ...mapActions('SplitStatement', [
      'updateCurrentTags'
    ]),

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
    }
  }
}
</script>
