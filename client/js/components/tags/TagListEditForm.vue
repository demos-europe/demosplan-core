<template>
  <div class="flex items-center">
    <dp-input
      v-if="isInEditState === nodeElement.id"
      class="flex-1"
      :id="`edit-${type}-${nodeElement.id}`"
      v-model="unsavedItem.title" />
    <div
      v-else
      class="flex-1"
      v-text="nodeElement.attributes.title" />
    <div class="text-center w-9">
      <dp-contextual-help
        v-if="nodeElement.relationships?.boilerplate"
        icon="file"
        :text="nodeElement.relationships.boilerplate.attributes.title" />
    </div>
    <addon-wrapper
      :addon-props="{ tag: nodeElement }"
      hook-name="tag.edit.form" />
    <div class="flex-0 text-center w-8">
      <template v-if="isInEditState !== nodeElement.id">
        <button
          v-if="nodeElement.type !== 'Tag'"
          :aria-label="Translator.trans('item.edit')"
          class="btn--blank o-link--default"
          :data-cy="`tags:edit${type}`"
          @click="editItem">
          <dp-icon
            aria-hidden="true"
            icon="edit" />
        </button>
        <a
          v-else
          :aria-label="Translator.trans('item.edit')"
          class="btn--blank o-link--default"
          :data-cy="`tags:edit${type}`"
          :href="Routing.generate('DemosPlan_statement_administration_tag', { tag: nodeElement.id, procedure: procedureId })">
          <dp-icon
            aria-hidden="true"
            icon="edit" />
        </a>
        <button
          :aria-label="Translator.trans('delete')"
          class="btn--blank o-link--default"
          :data-cy="`tags:abortEdit${type}`"
          @click="deleteItem">
          <dp-icon
            aria-hidden="true"
            icon="delete" />
        </button>
      </template>
      <template v-else>
        <button
          :aria-label="Translator.trans('save')"
          class="btn--blank o-link--default u-mr-0_25"
          :data-cy="`tags:save${type}`"
          @click="saveItem">
          <dp-icon
            aria-hidden="true"
            icon="check" />
        </button>
        <button
          class="btn--blank o-link--default"
          :data-cy="`tags:abortEdit${type}`"
          @click="abort"
          :aria-label="Translator.trans('abort')">
          <dp-icon
            aria-hidden="true"
            icon="xmark" />
        </button>
      </template>
    </div>
  </div>
</template>

<script>
import {
  DpContextualHelp,
  DpIcon,
  DpInput
} from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'

export default {
  name: 'TagListEditForm',

  components: {
    AddonWrapper,
    DpContextualHelp,
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

  data () {
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
      this.$emit('delete', { id: this.nodeElement.id, type: this.nodeElement.type })
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
