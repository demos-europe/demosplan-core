<template>
  <div>
    <div
      v-if="currentForm === 'tag'"
      id="new-tag-form"
      class="border rounded p-4 my-4"
      data-dp-validate="addNewTagForm">
      <dp-label
        class="mb-4"
        for="new-tag-form"
        :text="Translator.trans('entity.create', { entity: Translator.trans('tag') })"
        bold />
      <dp-input
        v-model="newTag.title"
        id="new-tag-title"
        class="mb-4"
        data-cy="tagsList:newTag:title"
        :label="{
           text: Translator.trans('title')
        }"
        maxlength="250"
        required />
      <dp-select
        v-model="newTag.topic"
        class="mb-4"
        data-cy="tagsList:newTag:topic"
        :label="{
          text: Translator.trans('entity.choose', { entity: Translator.trans('tag.category') })
        }"
        :options="topicsAsOptions"
        required />
      <addon-wrapper
        class="block mb-4"
        hook-name="tag.create.form"
        @input="updateForm"
        @change="updateForm" />
      <dp-button-row
        data-cy="tagsList:addNewTag"
        primary
        secondary
        @primary-action="dpValidateAction('addNewTagForm', () => saveNewTag(), false)"
        @secondary-action="closeForm" />
    </div>

    <div
      v-else-if="currentForm === 'topic'"
      id="new-topic-form"
      class="border rounded p-4 my-4"
      data-dp-validate="addNewTopicForm">
      <dp-label
        class="mb-4"
        for="new-topic-form"
        :text="Translator.trans('entity.create', { entity: Translator.trans('tag.category')})"
        bold />
      <dp-input
        v-model="newTopic.title"
        id="new-topic-title"
        class="mb-4"
        :label="{
           text: Translator.trans('title')
        }"
        maxlength="250"
        required />
      <div class="flex justify-end mt-2">
        <dp-button-row
          data-cy="tagsList:addNewTopic"
          primary
          secondary
          @primary-action="dpValidateAction('addNewTopicForm', () => saveNewTopic(), false)"
          @secondary-action="closeForm" />
      </div>
    </div>

    <div
      v-else
      class="flex gap-2 justify-end my-4">
      <dp-button
        data-cy="tagsList:openTagForm"
        :text="Translator.trans('entity.create', { entity: Translator.trans('tag') })"
        @click="() => openForm('tag')" />
      <dp-button
        data-cy="tagsList:openTopicForm"
        :text="Translator.trans('entity.create', { entity: Translator.trans('tag.category') })"
        variant="outline"
        @click="() => openForm('topic')" />
    </div>
  </div>
</template>

<script>
import { mapActions, mapMutations, mapState } from 'vuex'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import {
  DpButton,
  DpButtonRow,
  DpInput,
  DpLabel,
  DpSelect,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'

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
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      currentForm: '',
      newTag: {
        title: '',
        topic: ''
      },
      newTopic: {
        title: ''
      }
    }
  },

  computed: {
    ...mapState('TagTopic', {
      TagTopic: 'items'
    }),

    topicsAsOptions () {
      return Object.values(this.TagTopic).map(category => {
        const { attributes, id } = category

        return {
          label: attributes.title,
          value: id
        }
      })
    }
  },

  methods: {
    ...mapMutations('TagTopic', {
      updateTagTopic: 'setItem'
    }),

    ...mapActions('Tag', {
      createTag: 'create'
    }),

    ...mapActions('TagTopic', {
      createTagTopic: 'create'
    }),

    closeForm () {
      this.currentForm = ''
      this.resetFormData()
    },

    openForm (form) {
      this.currentForm = form
    },

    resetFormData () {
      this.newTopic.title = ''
      this.newTag = {
        title: '',
        topic: ''
      }
    },

    saveNewTag () {
      this.createTag({
        type: 'Tag',
        attributes: {
          title: this.newTag.title
        },
        relationships: {
          topic: {
            data: {
              type: 'TagTopic',
              id: this.newTag.topic
            }
          }
        }
      })
        .then(response => {
          if (!response.data.Tag || !this.TagTopic[this.newTag.topic]) {
            return
          }

          const parentTopic = this.TagTopic[this.newTag.topic]
          const newTagId = Object.keys(response.data.Tag)[0]

          this.updateTagTopic({
            id: this.newTag.topic,
            type: 'TagTopic',
            attributes: parentTopic.attributes,
            relationships: {
              ...parentTopic.relationships,
              tags: {
                data: parentTopic.relationships.tags.data.concat({
                  type: 'Tag',
                  id: newTagId
                })
              }
            }
          })

          this.$root.$emit('tag:created', newTagId)
          this.closeForm()
        })
    },

    saveNewTopic () {
      this.createTagTopic({
        type: 'TagTopic',
        attributes: {
          title: this.newTopic.title
        },
        relationships: {
          procedure: {
            data: {
              type: 'Procedure',
              id: this.procedureId
            }
          }
        }
      })
        .then(() => {
          this.closeForm()
        })

    },

    updateForm (value) {
      this.newTag[value.key] = value.value
    }
  }
}
</script>
