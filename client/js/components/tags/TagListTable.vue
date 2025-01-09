<template>
  <table>
    <thead>
      <tr class="border-b">
        <th class="checkbox text-left">
          <i class="fa fa-check-square-o"></i>
        </th>
        <th class="text-left">
          {{ Translator.trans('topic.or.tag') }}
        </th>
        <th class="text-center -mr-2">
          {{ Translator.trans('boilerplate') }}
        </th>
        <th class="text-right">
          {{ Translator.trans('actions') }}
        </th>
        <th>
          AI
        </th>
      </tr>
    </thead>

    <tbody>
    <template v-for="topic in topics">
      <tag-list-topic-row
        :topic="topic"
        @toggle:change="data => updateAiTags(data)" />
      <tag-list-tag-row
        v-for="tag in topic.tags"
        :tag="tag"
        :topics="topics"
        @toggle:change="data => updateAiTags(data)" />
    </template>
    </tbody>
  </table>
</template>

<script>
import { DpContextualHelp } from '@demos-europe/demosplan-ui'
import TagsListControls from './TagsListControls.vue'
import TagListTopicRow from './TagListTopicRow.vue'
import TagListTagRow from './TagListTagRow.vue'
export default {
  name: 'TagListTable',

  components: {
    TagListTagRow,
    DpContextualHelp,
    TagsListControls,
    TagListTopicRow
  },

  data () {
    return {
      selectedAiTags: []
    }
  },

  methods: {
    updateAiTags (event) {
      if (event.isAiActive) {
        this.selectedAiTags.push(event.tagId)
      } else {
        this.selectedAiTags = this.selectedAiTags.filter(tagId => tagId !== event.tagId)
      }
    }
  },

  inject: ['topics']
}
</script>
