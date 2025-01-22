<template>
  <div>
    <tag-list-header />

    <div
      v-if="addNew === 'tag'"
      class="border rounded p-4 my-4"
      data-dp-validate="addNewTagForm">
      <dp-input
        v-model="newTag.title"
        id="new-tag-title"
        class="mb-4"
        :label="{
           text: Translator.trans('title')
        }"
        maxlength="250"
        required />

      <dp-select
        class="mb-4"
        data-cy=""
        :label="{
          text: Translator.trans('topic.insertTag')
        }"
        :options="topicsAsOptions"
        v-model="newTag.topic" />

      <addon-wrapper
        class="block mb-4"
        hook-name="tag.create.form"
        @input="updateForm"
        @change="updateForm" />

      <dp-button-row
        primary
        secondary
        data-cy="toggleForm"
        @primary-action="dpValidateAction('addNewTagForm', () => saveNewTag(), false)"
        @secondary-action="addNew = ''" />
    </div>

    <div
      v-if="addNew === 'tagTopic'"
      class="border rounded p-4 my-4">
      <dp-input
        v-model="newTopic.title"
        id="new-topic-title"
        class="mb-2"
        :label="{
           text: Translator.trans('title')
        }" />
      <div class="flex justify-end mt-2">
        <dp-button-row
          primary
          secondary
          data-cy="toggleForm"
          @primary-action="saveNewTopic"
          @secondary-action="addNew = ''" />
      </div>
    </div>

    <div
      v-if="!addNew"
      class="flex justify-end mb-1">
      <dp-button
        :text="Translator.trans('tag.create')"
        @click="() => addNew = 'tag'" />
      <dp-button
        class="ml-1"
        :text="Translator.trans('category.create')"
        @click="() => addNew = 'tagTopic'" />
    </div>

    <dp-tree-list
      class="mb-4"
      :tree-data="transformedCategories"
      :options="{
        branchesSelectable: true,
        leavesSelectable: true
      }"
      :branch-identifier="branchFunc()">
      <template v-slot:header>
        <div class="flex">
          <div class="flex-1">
            {{ Translator.trans('topic.or.tag') }}
          </div>
          <div class="ml-1 flex-0">
            {{ Translator.trans('boilerplates') }}
          </div>

          <addon-wrapper hook-name="tag.extend.form" />

          <div class="ml-1 flex-0">
            {{ Translator.trans('actions') }}
          </div>
        </div>
      </template>
      <template v-slot:branch="{ nodeElement }">
        <tag-list-edit-form
          class="font-bold"
          :node-element="nodeElement"
          :is-in-edit-state="isInEditState"
          type="TagTopic"
          @abort="abort"
          @delete="deleteItem"
          @edit="setEditState"
          @save="save" />
      </template>
      <template v-slot:leaf="{ nodeElement }">
        <tag-list-edit-form
          :node-element="nodeElement"
          :is-in-edit-state="isInEditState"
          type="Tag"
          @abort="abort"
          @delete="deleteItem"
          @edit="setEditState"
          @save="save" />
      </template>
    </dp-tree-list>

    <tags-import-form
      class="mb-1"
      :procedure-id="procedureId" />
  </div>
</template>

<script>
import {
  checkResponse,
  DpButton,
  DpButtonRow,
  DpCheckbox,
  DpIcon,
  DpInput,
  DpModal,
  dpRpc,
  DpSelect,
  DpTreeList,
  DpUpload,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import TagListBulkControls from './TagListBulkControls'
import TagListEditForm from './TagListEditForm'
import TagsImportForm from './TagsImportForm'
import TagListHeader from './TagListHeader'


export default {
  name: 'TagsList',

  components: {
    DpButtonRow,
    AddonWrapper,
    DpButton,
    DpCheckbox,
    DpIcon,
    DpInput,
    DpModal,
    DpUpload,
    DpSelect,
    DpTreeList,
    TagsImportForm,
    TagListBulkControls,
    TagListEditForm,
    TagListHeader
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  mixins: [dpValidateMixin],

  data() {
    return {
      addNew: '',
      dataIsRequested: false,
      isInEditState: '',
      newTag: {
        title: '',
        topic: ''
      },
      newTopic: {
        title: ''
      },
      // This is necessary to allow extending the Tags-Resource
      tagAttributes: {
        boilerplate: '',
        title: ''
      }
    }
  },

  computed: {
    ...mapState('Tag', {
      Tag: 'items'
    }),
    ...mapState('TagTopic', {
      TagTopic: 'items'
    }),

    tagAttributeKeys () {
      return Object.keys(this.tagAttributes)
    },

    topicsAsOptions () {
      return Object.values(this.TagTopic).map(category => {
        const { attributes, id } = category

        return {
          label: attributes.title,
          value: id
        }
      })
    },

    transformedCategories () {
      return Object.values(this.TagTopic).map(category => {
        const { attributes, id, type } = category
        const tags = category.relationships?.tags?.data.length > 0 ? category.relationships.tags.list() : []

        return {
          id,
          attributes,
          children: Object.values(tags).map(tag => {
            const { id, attributes, type } = tag

            return {
              attributes,
              id,
              type
            }
          }),
          type
        }
      })
    }
  },

  methods: {
    ...mapMutations('Tag', {
      updateTag: 'setItem'
    }),

    ...mapMutations('TagTopic', {
      updateTagTopic: 'setItem'
    }),

    ...mapActions('Tag', {
      createTag: 'create',
      listTags: 'list',
      saveTag: 'save'
    }),

    ...mapActions('TagTopic', {
      createTagTopic: 'create',
      listTagTopics: 'list',
      saveTagTopic: 'save'
    }),

    abort () {
      this.isInEditState = ''
    },

    branchFunc () {
      return function ({ node }) {
        return node.type === 'TagTopic'
      }
    },

    deleteItem (item) {
      console.log(item, 'rpc delete')

      dpRpc('bulk.delete.tags.and.topics', { ids: [item] })
        .then(checkResponse)
        .then(response => {
          console.log(response, 'response delete')
        })
    },

    loadTagsAndTopics () {
      if (this.dataIsRequested) return

      this.dataIsRequested = true
      const topicAttributes = [
        'title',
        'tags'
      ]

      this.listTagTopics({
        fields: {
          Tag: this.tagAttributeKeys.join(),
          TagTopic: topicAttributes.join()
        },
        include: 'tags',
        sort: 'title'
      })
    },

    toggleCreateModal ({ type = 'Tag' }) {
      this.$refs[`create${type}Modal`].toggle()
    },

    save ({ id, attributes, type }) {
      if (id === '') {
        return
      }

      this[`update${type}`]({
        attributes,
        id,
        relationships: this[type][id].relationships,
        type
      })
      this[`save${type}`](id)
        .then(() => {
          this.isInEditState = ''
        })
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
        console.log(response, 'response new tag')
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
        // Close Modal and Reset form data
        this.toggleCreateModal({ type: 'Tag' })
        this.isInEditState = ''
        this.newTag = this.tagAttributes
      })
    },

    saveNewTopic () {
      console.log('saveNewTopic', this.newTopic)
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
          this.isInEditState = ''
          this.newTopic = {
            title: ''
          }
          this.toggleCreateModal({ type: 'TagTopic' })
        })

    },

    setEditState ({ id }) {
      this.isInEditState = id
    },

    updateForm (value) {
      console.log('updateForm', value)
      this.newTag[value.key] = value.value
    }
  },

  mounted () {
    this.loadTagsAndTopics()
  },

}
</script>
