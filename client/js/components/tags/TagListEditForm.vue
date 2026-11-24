<template>
  <div class="flex items-center">
    <dp-input
      v-if="isInEditState === nodeElement.id"
      :id="`edit-${type}-${nodeElement.id}`"
      v-model="unsavedItem.title"
      aria-labelledby="categoryOrTagLabel"
      class="flex-1"
    />
    <div
      v-else
      class="flex-1 break-words"
      v-text="nodeElement.attributes.title"
    />
    <addon-wrapper
      :addon-props="{ tag: nodeElement }"
      hook-name="tag.edit.form"
    />
    <div class="text-center w-9">
      <dp-contextual-help
        v-if="nodeElement.relationships?.boilerplate"
        icon="file"
        :text="nodeElement.relationships.boilerplate.attributes.title"
      />
    </div>
    <div class="flex-0 justify-center w-8 flex">
      <button
        v-if="isInEditState !== nodeElement.id && nodeElement.type !== 'Tag'"
        :aria-label="Translator.trans('item.edit')"
        class="btn--blank o-link--default"
        :data-cy="`tags:edit${type}`"
        @click="editItem"
      >
        <dp-icon
          aria-hidden="true"
          icon="edit"
        />
      </button>
      <a
        v-if="isInEditState !== nodeElement.id && nodeElement.type === 'Tag'"
        :aria-label="Translator.trans('item.edit')"
        class="btn--blank o-link--default"
        :data-cy="`tags:edit${type}`"
        :href="Routing.generate('DemosPlan_statement_administration_tag', { tag: nodeElement.id, procedure: procedureId })"
      >
        <dp-icon
          aria-hidden="true"
          icon="edit"
        />
      </a>
      <button
        v-if="isInEditState !== nodeElement.id"
        :aria-label="Translator.trans('delete')"
        class="btn--blank o-link--default"
        :data-cy="`tags:abortEdit${type}`"
        @click="deleteItem"
      >
        <dp-icon
          aria-hidden="true"
          icon="delete"
        />
      </button>
      <div
        v-else
        class="flex"
      >
        <button
          :aria-label="Translator.trans('save')"
          class="btn--blank o-link--default u-mr-0_25"
          :data-cy="`tags:save${type}`"
          @click="saveItem"
        >
          <dp-icon
            aria-hidden="true"
            icon="check"
          />
        </button>
        <button
          class="btn--blank o-link--default"
          :data-cy="`tags:abortEdit${type}`"
          :aria-label="Translator.trans('abort')"
          @click="abort"
        >
          <dp-icon
            aria-hidden="true"
            icon="xmark"
          />
        </button>
      </div>
      <dp-confirm-dialog
        ref="confirmDialog"
        data-cy="removeTagOrTopic"
        :message="confirmMessage"
      />
    </div>
  </div>
</template>

<script>
import {
  DpConfirmDialog,
  DpContextualHelp,
  DpIcon,
  DpInput,
} from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import { useTagListEditForm } from '@DpJs/composables/useTagListEditForm'

export default {
  name: 'TagListEditForm',

  components: {
    AddonWrapper,
    DpConfirmDialog,
    DpContextualHelp,
    DpIcon,
    DpInput,
  },

  props: {
    isInEditState: {
      type: String,
      required: true,
    },

    nodeElement: {
      type: Object,
      required: true,
    },

    procedureId: {
      type: String,
      required: true,
    },

    type: {
      type: String,
      required: true,
    },
  },

  emits: [
    'abort',
    'delete',
    'edit',
    'save',
  ],

  setup (props, { emit }) {
    return useTagListEditForm(props, emit)
  },

  methods: {
    async deleteItem () {
      if (!this.$refs.confirmDialog?.open) {
        return
      }

      const isConfirmed = await this.$refs.confirmDialog.open()

      if (isConfirmed) {
        this.confirmDelete()
      }
    },
  },
}
</script>
