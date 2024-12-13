<template>
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

    <td>
      <a class="u-1-of-1 block o-hellip">{{ tag.title }}</a>
      <template v-if="isMoveVisible">
        <div class="overflow-hidden">
          <div class="o-toggle__target u-mv-0_25 flex space-inline-s" :data-toggle-id="`move-tag-${tag.id}`">
            <select
              data-cy="moveTagSelect"
              class="o-form__control-select u-2-of-5"
              :name="`${tag.id}:r_moveto`">
              <template v-for="topic in topics">
                <option
                  :value="topic.id"
                  :selected="tag.topic_id === topic.id"
                  @click="handleSelect(topic.id)">
                  {{ topic.title }}
                </option>
              </template>
            </select>
            <button
              class="btn btn--primary"
              data-cy="moveTagSubmitBtn"
              name="r_move"
              :value="`${selectedTopicId}`">
              {{  Translator.trans('tag.move.toTopic') }}
            </button>
          </div>
        </div>
      </template>

      <template v-if="isRenameVisible">
        <div class="overflow-hidden">
          <div class="o-toggle__target u-mv-0_25 flex space-inline-s" :data-toggle-id="`rename-${tag.id}`">
            <input
              data-cy="renameTopicField"
              v-if="isVisible.rename"
              :data-form-actions-submit-target="`#topicRenameBtn-${tag.id}`"
              class="o-form__control-input u-2-of-5"
              type="text"
              :value="tag.title"
              :name="`${tag.id}:r_rename`">

            <button
              class="btn btn--primary"
              name="r_renametopic"
              data-cy="renameTopicSave"
              :value="tag.id"
              :id="`topicRenameBtn-${tag.id}`">
              {{ Translator.trans('tag.rename') }}
            </button>
          </div>
        </div>
      </template>
    </td>
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
        tag-type="tag"
        @toggleInputs="evt => handleControlEvent(evt)" />
    </td>
  </tr>
</template>

<script>
import TagsListControls from "./TagsListControls.vue";
import { DpContextualHelp } from '@demos-europe/demosplan-ui/';

export default {
  name: 'TagListTagRow',
  components: {
    DpContextualHelp,
    TagsListControls
  },

  props: {
    tag: {
      type: Object,
      required: true
    },

    topics: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      isVisible: {
        move: false,
        rename: false
      },
      selectedTopicId: ''
    }
  },

  methods: {
    handleControlEvent (event) {
      console.log(event)
      if (event.action === 'move') {
        this.isVisible.move = !this.isVisible.move
        this.isVisible.rename = false
      } else if (event.action === 'rename') {
        this.isVisible.move = false
        this.isVisible.rename = !this.isVisible.rename
      }
    },

    handleSelect (id) {
      console.log('clicked')
      this.selectedTopicId = id
      console.log('selected')
    }
  },

  computed: {
    isMoveVisible () {
      return this.isVisible?.move ?? false
    },
    isRenameVisible () {
      return this.isVisible?.rename ?? false
    }
  }
}
</script>
