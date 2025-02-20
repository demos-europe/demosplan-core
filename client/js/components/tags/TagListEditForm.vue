
<template>
  <div class="flex items-center">
    <dp-input
      v-if="isInEditState === nodeElement.id"
      :id="`edit-${type}-${nodeElement.id}`"
      class="flex-1"
      v-model="unsavedItem.title" />
    <template v-else>
      <div
        class="flex-1"
        v-text="nodeElement.attributes.title" />
      <div class="text-center w-9">
        <dp-contextual-help
          v-if="nodeElement.relationships?.boilerplate"
          icon="file"
          :text="nodeElement.relationships.boilerplate.attributes.title" />
      </div>
      <addon-wrapper
        hook-name="tag.edit.form"
        :addon-props="{
          tag: nodeElement
        }" />
    </template>
    <div class="flex-0 pl-2 w-8">
      <template v-if="isInEditState !== nodeElement.id">
        <button
          :aria-label="Translator.trans('item.edit')"
          class="btn--blank o-link--default"
          :data-cy="`tags:edit${type}`"
          @click="editItem">
          <dp-icon
            icon="edit"
            aria-hidden="true" />
        </button>
        <button
          class="btn--blank o-link--default"
          :data-cy="`tags:abortEdit${type}`"
          @click="deleteItem"
          :aria-label="Translator.trans('delete')">
          <dp-icon
            icon="delete"
            aria-hidden="true" />
        </button>
        <dp-flyout
          v-if="nodeElement.type === 'Tag'"
          data-cy="tagItem:flyoutEditMenu">
          <a
            :href="Routing.generate('DemosPlan_statement_administration_tag', {
              tag: nodeElement.id,
              procedure: procedureId
            })"
            data-cy="tag:editPage"
            rel="noopener">
            {{ Translator.trans('edit') }}
          </a>
        </dp-flyout>
      </template>
      <template v-else>
        <button
          :aria-label="Translator.trans('save')"
          class="btn--blank o-link--default u-mr-0_25"
          :data-cy="`tags:save${type}`"
          @click="saveItem">
          <dp-icon
            icon="check"
            aria-hidden="true" />
        </button>
        <button
          class="btn--blank o-link--default"
          :data-cy="`tags:abortEdit${type}`"
          @click="abort"
          :aria-label="Translator.trans('abort')">
          <dp-icon
            icon="xmark"
            aria-hidden="true" />
        </button>
      </template>
    </div>
  </div>
</template>

<script>
import {
  DpContextualHelp,
  DpFlyout,
  DpIcon,
  DpInput
} from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'

export default {
  name: 'TagListEditForm',

  components: {
    AddonWrapper,
    DpContextualHelp,
    DpFlyout,
    DpIcon,
    DpInput
  },

  props: {
    isInEditState: {
      type: String,
      required: true
    },

    nodeElement: {
      type: Object,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    },

    type: {
      type: String,
      required: true
    }
  },

  data() {
    return {
      unsavedItem: {
        title: ''
      }
    }
  },

  methods: {
    abort () {
      this.$emit('abort')
    },

    deleteItem () {
      const { type, attributes, children, id } = this.nodeElement
      const isTagTopic = type === 'TagTopic'
      const topicConfirmMessage = isTagTopic && children?.length === 0 ? 'check.topic.delete' : 'check.topic.delete.tags'

      const confirmMessage = isTagTopic
        ? Translator.trans(topicConfirmMessage, { topic: attributes.title })
        : Translator.trans('check.tag.delete', { tag: attributes.title })

      if (window.dpconfirm(confirmMessage)) {
        this.$emit('delete', { id, type })
      }
    },

    editItem () {
      this.unsavedItem = { ...this.nodeElement.attributes }
      this.$emit('edit', { id: this.nodeElement.id, type: this.nodeElement.type })
    },

    saveItem () {
      this.$emit('save', { id: this.nodeElement.id, attributes: this.unsavedItem, type: this.nodeElement.type })
    }
  }
}
</script>
