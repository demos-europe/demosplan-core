<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <!-- tag category with subtags multiselect -->
  <multi-select
    class="multiselect--dark display--inline-block u-valign--bottom cursor--pointer"
    :class="{'has-selection': selected.length}"
    :placeholder="placeHolder"
    :options="tagsByTopic"
    :close-on-select="false"
    :searchable="false"
    @input="(val) => updateTags(val)"
    multiple>
    <!-- available options -->
    <template v-slot:option="props">
      <input
        type="checkbox"
        :checked="!!selected.find(tag => tag.id === props.option.id || tag.attributes.title === props.option.title)">
      <label>
        {{ props.option.attributes.title }}
      </label>
    </template>
  </multi-select>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import { hasOwnProp } from '@demos-europe/demosplan-ui'
import MultiSelect from '@DpJs/components/procedure/splitStatement/MultiSelect'

export default {
  name: 'TagSelect',

  components: {
    MultiSelect
  },

  props: {
    entity: {
      type: Object,
      default: () => ({})
    },

    selected: {
      type: Array,
      default: () => ([])
    }
  },

  computed: {
    ...mapGetters('splitstatement', {
      // Sub tags (h2)
      tags: 'categorizedTags',
      editingSegment: 'editingSegment',
      uncategorizedTags: 'uncategorizedTags'
    }),

    placeHolder () {
      return this.selected.length === 0 ? this.entity.attributes.title : `${this.entity.attributes.title} (${this.selected.length})`
    },

    tagsByTopic () {
      if (this.entity.id === 'category.none') {
        return this.uncategorizedTags
      } else {
        return this.tags.filter((tag) => {
          let returnValue = false
          if (hasOwnProp(tag, 'relationships') && hasOwnProp(tag.relationships, 'topic')) {
            if (hasOwnProp(tag.relationships.topic, 'data') && typeof tag.relationships.topic.data !== 'undefined') {
              returnValue = tag.relationships.topic.data.id === this.entity.id ? tag : false
            }
          }
          return returnValue
        })
      }
    }
  },

  methods: {
    ...mapActions('splitstatement', [
      'updateCurrentTags'
    ]),

    /**
     * Update tags on segment currently being edited (= editingSegment)
     */
    updateTags (val, isEditing = true) {
      let value
      if (Array.isArray(val)) {
        const [tag] = val
        value = tag
      } else if (typeof val === 'object' && !Array.isArray(val)) {
        value = val
      } else {
        value = this.entity
      }

      this.updateCurrentTags({ tagName: value.attributes.title, id: value.id })
    }
  }
}
</script>
