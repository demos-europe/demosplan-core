<template>
  <div>
    <div
      v-if="currentForm === 'tag'"
      id="new-tag-form"
      class="border rounded-sm p-4 my-4"
      data-dp-validate="addNewTagForm"
    >
      <dp-label
        class="mb-4"
        for="new-tag-form"
        :text="Translator.trans('entity.create', { entity: Translator.trans('tag') })"
        bold
      />
      <dp-input
        id="new-tag-title"
        v-model="newTag.title"
        class="mb-4"
        data-cy="tagsList:newTag:title"
        :label="{
          text: Translator.trans('title')
        }"
        maxlength="250"
        required
      />
      <dp-select
        v-model="newTag.topic"
        class="mb-4"
        data-cy="tagsList:newTag:topic"
        :label="{
          text: Translator.trans('entity.choose', { entity: Translator.trans('tag.category') })
        }"
        :options="topicsAsOptions"
        required
      />
      <addon-wrapper
        class="block mb-4"
        hook-name="tag.create.form"
        @input="updateForm"
        @change="updateForm"
      />
      <dp-button-row
        data-cy="tagsList:addNewTag"
        primary
        secondary
        @primary-action="dpValidateAction('addNewTagForm', () => saveNewTag(), false)"
        @secondary-action="closeForm"
      />
    </div>

    <div
      v-else-if="currentForm === 'topic'"
      id="new-topic-form"
      class="border rounded-sm p-4 my-4"
      data-dp-validate="addNewTopicForm"
    >
      <dp-label
        class="mb-4"
        for="new-topic-form"
        :text="Translator.trans('entity.create', { entity: Translator.trans('tag.category')})"
        bold
      />
      <dp-input
        id="new-topic-title"
        v-model="newTopic.title"
        class="mb-4"
        :label="{
          text: Translator.trans('title')
        }"
        maxlength="250"
        required
      />
      <div class="flex justify-end mt-2">
        <dp-button-row
          data-cy="tagsList:addNewTopic"
          primary
          secondary
          @primary-action="dpValidateAction('addNewTopicForm', () => saveNewTopic(), false)"
          @secondary-action="closeForm"
        />
      </div>
    </div>

    <div
      v-else
      class="flex gap-2 justify-end my-4"
    >
      <dp-button
        data-cy="tagsList:openTagForm"
        :text="Translator.trans('entity.create', { entity: Translator.trans('tag') })"
        @click="() => openForm('tag')"
      />
      <dp-button
        data-cy="tagsList:openTopicForm"
        :text="Translator.trans('entity.create', { entity: Translator.trans('tag.category') })"
        variant="outline"
        @click="() => openForm('topic')"
      />
    </div>
  </div>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpInput,
  DpLabel,
  DpSelect,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import { useTagsCreateForm } from '@DpJs/composables/useTagsCreateForm'

export default {
  name: 'TagsCreateForm',

  components: {
    AddonWrapper,
    DpButton,
    DpButtonRow,
    DpInput,
    DpLabel,
    DpSelect,
  },

  mixins: [dpValidateMixin],

  props: {
    isMasterProcedure: {
      type: Boolean,
      required: false,
      default: false,
    },

    procedureId: {
      type: String,
      required: true,
    },
  },

  setup (props) {
    return useTagsCreateForm(props)
  },
}
</script>
