<template>
  <div>
    <tag-list-header />

    <dp-modal
      content-classes="w-2/3"
      ref="createTagModal"
      aria-label="Create Tag"
      aria-modal>
      <template v-slot:header>
        Create Tag
      </template>

      <template>
        <dp-input
          v-model="newTag.title"
          id="new-tag-title"
          :label="{
            text: 'Create Tag'
          }"
          placeholder="Title" />

        <addon-wrapper
          class="inline-block"
          hook-name="tag.create.form"
          @input="updateForm"
          @change="updateForm" />

        <dp-button
          text="Create Tag"
          @click="saveNewTag" />
      </template>
    </dp-modal>

    <dp-modal
      content-classes="w-2/3"
      ref="createTopicModal"
      aria-label="Create Topic"
      aria-modal>
      <template v-slot:header>
        Create Tag
      </template>

      <template>
        <dp-input
          v-model="newTopic.title"
          id="new-topic-title"
          :label="{
            text: 'Create Topic'
          }"
          placeholder="Title" />

        <dp-button
          text="Create Topic"
          @click="saveNewTopic" />
      </template>
    </dp-modal>

    <dp-button
      text="Create Topic"
      @click="() => toggleCreateModal({ type: 'Topic' })" />

    <dp-tree-list
      :tree-data="transformedCategories"
      :options="{
        branchesSelectable: true,
        leavesSelectable: true
      }"
      :branch-identifier="branchFunc()">
      <template v-slot:branch="{ nodeElement }">
        <tag-list-edit-form
          :node-element="nodeElement"
          :is-in-edit-state="isInEditState"
          has-create-button
          type="Topic"
          @abort="abort"
          @create="() => toggleCreateModal({ type: 'Tag' })"
          @delete="deleteItem"
          @edit="setEditState"
          @exted="extendForm"
          @initFetch="loadTagsAndTopics"
          @save="save"
        />
      </template>
      <template v-slot:leaf="{ nodeElement }">
        <tag-list-edit-form
          :node-element="nodeElement"
          :is-in-edit-state="isInEditState"
          type="Tag"
          @abort="abort"
          @delete="deleteItem"
          @edit="setEditState"
          @save="save"
        />
      </template>
    </dp-tree-list>

    <tags-import-form />
  </div>
</template>

<script>
import { DpButton, DpCheckbox, DpIcon, DpInput, DpModal, DpTreeList, DpUpload, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations } from 'vuex'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import TagListBulkControls from './TagListBulkControls'
import TagListEditForm from './TagListEditForm'
import TagsImportForm from './TagsImportForm'
import TagListHeader from './TagListHeader'
import TagListTable from './TagListTable'

export default {
  name: 'TagsList',

  components: {
    AddonWrapper,
    DpButton,
    DpCheckbox,
    DpIcon,
    DpInput,
    DpModal,
    DpUpload,
    DpTreeList,
    TagsImportForm,
    TagListBulkControls,
    TagListEditForm,
    TagListHeader,
    TagListTable
  },

  props: {
    procedureId: {
      type: String,
      required: true
    },

    topics: {
      type: Array,
      required: true
    }
  },

  provide() {
    return {
      topics: this.topics,
      procedureId: this.procedureId
    }
  },

  mixins: [dpValidateMixin],

  data() {
    return {
      dataIsRequested: false,
      isInEditState: '',
      newTag: {
        title: ''
      },
      newTopic: {
        title: ''
      },
      unsavedTitle: '',
      Tag: {}, // @TODO will be moved to state when the data comes from store
      tagAttributes: { title: '' },
      transformedCategories: this.transformTagsAndCategories()
    }
  },

  computed: {
    tagAttributeKeys () {
      return Object.keys(this.tagAttributes)
    }
  },

  methods: {
    ...mapMutations('Tags', {
      updateTag: 'update'
    }),

    ...mapMutations('TagTopics', {
      updateTopic: 'update'
    }),

    ...mapActions('Tags', {
      createTag: 'create',
      listTags: 'list',
      saveTag: 'save'
    }),

    ...mapActions('TagTopics', {
      createTopic: 'create',
      listTopics: 'list',
      saveTopic: 'save'
    }),

    abort () {
      this.isInEditState = ''
    },

    branchFunc () {
      return function ({ node }) {
        return node.type === 'Topic'
      }
    },

    deleteItem (id) {
      this.deleteTag(id)
    },

    /**
     * To allow addons extending the form, this method cann add any attributes
     * to the tag resource request
     *
     * @param attribute
     * @param defaultValue
     */
    extendForm ({ attribute, defaultValue }) {
      if (this.tagAttributeKeys.includes(attribute)) return

      this.newTag[attribute] = defaultValue
      this.tagAttributes[attribute] = defaultValue

      this.loadTagsAndTopics()
    },

    // @TODO will be removed when the data comes from store
    getTags () {
      this.topics.forEach(topic => {
        topic.tags.forEach(tag => {
          this.Tag[tag.id] = tag
        })
      })
    },

    loadTagsAndTopics () {
      if (this.dataIsRequested) return

      this.dataIsRequested = true

      this.listTags({
        fields: {
          Tag: this.tagAttributeKeys.join()
        }
      })

      this.listTopics({
        fields: {
          TagTopic: [
            'title'
          ].join()
        }
      })

      this.getTags()
    },

    toggleCreateModal ({ type = 'Tag' }) {
      this.$refs[`create${type}Modal`].toggle()
    },

    save ({ id, title, type }) {
      return // @TODO will be removed when the data comes from store

      if (id === '') {
        return
      }

      this[`update${type}`]({ id, title })
      this[`save${type}`](id)
    },

    saveNewTag () {
      this.createTag({
        type: 'Tag',
        attributes: {
          ...this.newTag
        }
      })

      this.isInEditState = ''
      this.newTag = this.tagAttributes

      this.toggleCreateModal({ type: 'Tag' })
    },

    saveNewTopic () {
      this.createTopic({
        type: 'TagTopic',
        attributes: {
          title: this.newTag.title
        }
      })

      this.isInEditState = ''
      this.newTopic = {
        title: ''
      }
      this.toggleCreateModal({ type: 'Topic' })
    },

    setEditState ({ id }) {
      this.isInEditState = id
    },

    // @TODO will be removed when the data comes from store
    transformTagsAndCategories () {
      return Object.values(this.topics).map(category => {
        const { id, title, tags = [] } = category

        return {
          id,
          title,
          type: 'Topic',
          children: tags
        }
      })
    },

    updateForm (value) {
      console.log('updateForm', value)
      this.newTag[value.key] = value.value
    }
  }
}
</script>
