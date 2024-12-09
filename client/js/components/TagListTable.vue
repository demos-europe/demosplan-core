<template>
  <table>
    <col class="w-6">
    <col class="">
    <col class="w-9">
    <col class="w-9">

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
    </tr>
    </thead>

    <tbody>
    <template v-for="topic in topics">
      <tr class="border-b">
        <td class="checkbox u-ph-0 u-pb-0_25 u-pt-0_5 text-left">
          <input type="hidden" :name="`${topic.id}:r_itemtype`" value="topic">

          <label class="u-m-0">
            <input
              type="checkbox"
              :name="`${topic.id}:r_selected`"
              data-cy="listTags:selectTopic"
              data-checkable-item>
          </label>
        </td>
        <td class="weight--bold">
          {{topic.name }}

          <div class="overflow-hidden o-toggle">
            <div class="o-toggle__target u-mv-0_25 flex space-inline-s" :data-toggle-id="`insert-${topic.id}`">
              <input
                :data-form-actions-submit-target="`#topicInsertBtn-${topic.id}`"
                class="o-form__control-input u-2-of-5"
                type="text"
                data-cy="nameNewTag"
                :name="`${topic.id}:r_newtags`"
                :placeholder="Translator.trans('tag.name')"
                :aria-label="Translator.trans('tag.name')">

              <button
                class="btn btn--primary"
                name="r_createtags"
                data-cy="createNewTag"
                :value="topic.id"
                :id="`topicInsertBtn-${topic.id}`">
                {{ Translator.trans('topic.insertTag') }}
              </button>
            </div>
          </div>

          <div class="overflow-hidden o-toggle">
            <div class="o-toggle__target u-mv-0_25 flex space-inline-s" :data-toggle-id="`rename-{topic.id}`">
              <input
                data-cy="renameTopicField"
                :data-form-actions-submit-target="`#topicRenameBtn-${topic.id}`"
                class="o-form__control-input u-2-of-5"
                type="text"
                :value="topic.title"
                :name="`${topic.id}:r_rename`">

              <button
                class="btn btn--primary"
                name="r_renametopic"
                data-cy="renameTopicSave"
                :value="topic.id"
                :id="`topicRenameBtn-${topic.id}`">
                {{ Translator.trans('topic.rename') }}
              </button>
            </div>
          </div>
        </td>

        <td />
        <td class="text-right">
          <tags-list-controls
            :tag="topic"
            tag-type="topic" />
        </td>
      </tr>
      <template v-for="tag in topic.tags">
        <tr class="border-b">
          <td class="checkbox u-ph-0 u-pb-0_25 u-pt-0_5 text-left">
            <input type="hidden" :name="`${tag.id}:r_itemtype`" value="tag">

            <label class="u-m-0">
              <input
                type="checkbox"
                :name="`${tag.id}:r_selected`"
                data-cy="listTags:selectTopic"
                data-checkable-item>
            </label>
          </td>
          <td><a class="u-1-of-1 block o-hellip">{{ tag.title }}</a></td>
          <td class="text-center relative">
            <div class="relative">
              <dp-contextual-help
                v-if="tag.boilerplate"
                class="color--grey block"
                icon="file"
                :text="tag.boilerplate">
              </dp-contextual-help>
            </div>
          </td>

          <td class="text-right">
            <tags-list-controls
              :tag="tag"
              tag-type="tag" />
          </td>
        </tr>
      </template>
    </template>
    </tbody>
  </table>
</template>

<script>
import { DpContextualHelp } from '@demos-europe/demosplan-ui'
import TagsListControls from './TagsListControls.vue'
export default {
  name: 'TagListTable',

  components: {
    DpContextualHelp,
    TagsListControls
  },

  inject: ['topics']
}
</script>
