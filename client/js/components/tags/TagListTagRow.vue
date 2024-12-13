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

      <div v-if="isMoveVisible" class="overflow-hidden">
        <h1>1</h1>
      </div>

      <div v-if="isRenameVisible" class="overflow-hidden">
        <h1>2</h1>
      </div>
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
    }
  },

  data () {
    return {
      isVisible: {
        move: false,
        rename: false
      }
    }
  },

  methods: {
    handleControlEvent (event) {
      console.log(event)
      if (event.type === 'tag' && event.action === 'move') {
        this.isVisible.move = !this.isVisible.move
        this.isVisible.rename = false
      } else if (event.action === 'rename') {
        this.isVisible.move = false
        this.isVisible.rename = !this.isVisible.rename
      }
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
