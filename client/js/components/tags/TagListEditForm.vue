
<template>
  <div class="flex">
    <dp-input
      v-if="isInEditState === nodeElement.id"
      :id="`edit-${type}-${nodeElement.id}`"
      class="flex-1"
      v-model="unsavedItem.title" />
    <span
      v-else
      class="flex-1"
      v-text="nodeElement.attributes.title" />

    <addon-wrapper
      hook-name="tag.edit.form"
      :addon-props="{
        tag: nodeElement
      }"
      @addons:loaded="initialiseDataFetching"
      @loaded="extendForm" />

    <div class="flex-0 pl-2">
      <template  v-if="isInEditState !== nodeElement.id">
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
        <button
          v-if="hasCreateButton"
          class="btn--blank o-link--default"
          :data-cy="`tags:create${type}`"
          @click="createItem"
          :aria-label="Translator.trans('create')">
          <dp-icon
            icon="tag"
            aria-hidden="true" />
        </button>
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
import { DpIcon, DpInput } from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'

export default {
  name: 'TagListEditForm',

  components: {
    AddonWrapper,
    DpIcon,
    DpInput
  },

  props: {
    hasCreateButton: {
      type: Boolean,
      default: false
    },

    isInEditState: {
      type: String,
      required: true
    },

    nodeElement: {
      type: Object,
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

    createItem () {
      this.$emit('create', { id: this.nodeElement.id, type: this.nodeElement.type })
    },

    deleteItem () {
      this.$emit('delete', { id: this.nodeElement.id, type: this.nodeElement.type })
    },

    editItem () {
      this.unsavedItem = { ...this.nodeElement.attributes }
      this.$emit('edit', { id: this.nodeElement.id, type: this.nodeElement.type })
    },

    extendForm (newAttr) {
      console.log('extendForm')
      this.$emit('extend', newAttr)
    },

    initialiseDataFetching (addons) {
      if (addons.length === 0) {
        this.$emit('initFetch', addons)
      }
    },

    saveItem () {
      this.$emit('save', { id: this.nodeElement.id, attributes: this.unsavedItem, type: this.nodeElement.type })
    }
  }
}
</script>
