<template>
  <table>
    <col class="w-6">
    <col class="">
    <col class="w-6">
    <col class="w-6">

    <col class="w-6">

    <thead>
    <tr class="border-b">
      <th class="checkbox text-left">
        <i class="fa fa-check-square-o"></i>
      </th>
      <th class="text-left">
        {{ Translator.trans('topic.or.tag') }}
      </th>
      <th class="text-left -mr-2">
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
      <tag-list-topic-row :topic="topic" />
      <template v-for="tag in topic.tags">
        <tag-list-tag-row
          :tag="tag"
          :topics="topics" />
      </template>
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

  inject: ['topics']
}
</script>
