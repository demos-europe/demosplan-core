<template>
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

      <div class="overflow-hidden">
        <div v-if="isInsertVisible" class="o-toggle__target u-mv-0_25 flex space-inline-s" :data-toggle-id="`insert-${topic.id}`">
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

      <div class="overflow-hidden">
        <div v-if="isRenameVisible" class="o-toggle__target u-mv-0_25 flex space-inline-s" :data-toggle-id="`rename-${topic.id}`">
          <input
            data-cy="renameTopicField"
            v-if="isVisible.rename"
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
        tag-type="topic"
        @toggleInputs="evt => handleControlEvent(evt)" />
    </td>
    <td>
      <span class="w-full h-full flex justify-center items-center">
        <input
          id="addonCheckbox"
          type="checkbox">
      </span>
    </td>
  </tr>
</template>
<script>
import TagsListControls from './TagsListControls'

export default {
  name: 'TagListTopicRow',
  components: {
    TagsListControls
  },

  props: {
    topic: {
      type: Object,
      required: true
    }
  },

  data () {
    return {
      isVisible: {
        insert: false,
        rename: false
      }
    }
  },

  methods: {
    handleControlEvent (event) {
      console.log(event)
      if (event.type === 'topic' && event.action === 'insert') {
        this.isVisible.insert = !this.isVisible.insert
        this.isVisible.rename = false
      } else if (event.action === 'rename') {
        this.isVisible.insert = false
        this.isVisible.rename = !this.isVisible.rename
      }
    }
  },

  computed: {
    isInsertVisible () {
      return this.isVisible?.insert ?? false
    },
    isRenameVisible () {
      return this.isVisible?.rename ?? false
    }
  },
}
</script>
