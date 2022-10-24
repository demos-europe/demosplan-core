<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <multi-select
      id="searchSelect"
      class="cursor--pointer"
      :options="options"
      :close-on-select="false"
      :clear-on-select="false"
      :preserve-search="true"
      label="title"
      track-by="title"
      :placeholder="placeHolder">
      <template v-slot:option="props">
        <input
          type="checkbox"
          :id="`tag_${props.option.id}`"
          :checked="typeof selected.find(el => el.id === props.option.id || el.attributes.title === props.option.title) !== 'undefined'"
          @input="updateSelected"
          :value="props.option.id">
        <label
          class="cursor--pointer"
          :for="`tag_${props.option.id}`">
          {{ props.option.title }}
        </label>
      </template>
      <template v-slot:beforeList>
        <button
          @click="$emit('open-create-form')"
          class="btn--blank o-link--default weight--bold u-ph-0_5 u-pv-0_5 text--left u-1-of-1 whitespace--nowrap">
          {{ Translator.trans('tag.topic.new') }}
        </button>
      </template>
    </multi-select>
  </div>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import MultiSelect from './MultiSelect'

export default {
  name: 'SearchSelect',

  components: {
    MultiSelect
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

  computed: {
    ...mapGetters('splitstatement', {
      availableTags: 'availableTags',
      currentSegment: 'editingSegment',
      tagById: 'tagById',
      categorizedTags: 'categorizedTags'
    })
  },

  methods: {
    ...mapActions('splitstatement', [
      'updateCurrentTags'
    ]),

    updateSelected (e) {
      const id = e.target.value
      const tag = this.tagById(id)
      this.updateCurrentTags({ id: id, tagName: tag.attributes.title })
    }
  }
}
</script>
