<template>
  <div>
    <!--
      preload addons which extend the form
      to ensure that the additional data is available
    -->
    <addon-wrapper
      hook-name="tag.edit.form"
      :addon-props="{
        tag: { id: 'fake', type: 'irrevelvant'}
      }"
      @addons:loaded="loadTagsAndTopics"
      @loaded="extendForm" />

    <tag-list-header />

    <dp-modal
      content-classes="w-2/3"
      ref="createTagModal"
      :aria-label="Translator.trans('tag.create')"
      aria-modal>
      <template v-slot:header>
        Create Tag
      </template>

      <template>
        <dp-input
          v-model="newTag.title"
          id="new-tag-title"
          class="mb-1"
          :label="{
            text: Translator.trans('tag.create')
          }"
          :placeholder="Translator.trans('title')" />

        <addon-wrapper
          class="block mb-1"
          hook-name="tag.create.form"
          @input="updateForm"
          @change="updateForm" />

        <dp-button
          :text="Translator.trans('tag.create')"
          @click="saveNewTag" />
      </template>
    </dp-modal>

    <dp-modal
      content-classes="w-2/3"
      ref="createTopicModal"
      aria-label="Create Topic"
      aria-modal>
      <template v-slot:header>
        {{ Translator.trans('category.create') }}
      </template>

      <dp-input
        v-model="newTopic.title"
        id="new-topic-title"
        class="mt-2"
        :placeholder="Translator.trans('title')" />
      <div class="flex justify-end mt-2">
        <dp-button
          :text="Translator.trans('category.create')"
          @click="saveNewTopic" />
      </div>
    </dp-modal>

    <div class="flex justify-end">
      <dp-button
        :text="Translator.trans('category.create')"
        @click="() => toggleCreateModal({ type: 'Topic' })" />
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
          <div class="flex-0">
            {{ Translator.trans('boilerplates') }}
          </div>
          <div class="flex-0">
            {{ Translator.trans('actions') }}
          </div>
        </div>
      </template>
      <template v-slot:branch="{ nodeElement }">
        <tag-list-edit-form
          class="font-bold"
          :node-element="nodeElement"
          :is-in-edit-state="isInEditState"
          has-create-button
          type="TagTopic"
          @abort="abort"
          @create="() => toggleCreateModal({ type: 'Tag' })"
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
      procedure-id="procedureId" />
  </div>
</template>

<script>
import { DpButton, DpCheckbox, DpIcon, DpInput, DpModal, DpTreeList, DpUpload, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import TagListBulkControls from './TagListBulkControls'
import TagListEditForm from './TagListEditForm'
import TagsImportForm from './TagsImportForm'
import TagListHeader from './TagListHeader'

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
      dataIsRequested: false,
      isInEditState: '',
      newTag: {
        title: ''
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

    loadTagsAndTopics () {
      if (this.dataIsRequested) return

      this.dataIsRequested = true
      const topicAttributes = [
        'title',
        'tags'
      ].join()

      this.listTagTopics({
        fields: {
          Tag: this.tagAttributeKeys.join(),
          TagTopic: topicAttributes
        },
        include: 'tags'
      })
    },

    toggleCreateModal ({ type = 'Tag' }) {
      this.$refs[`create${type}Modal`].toggle()
    },

    save ({ id, attributes, type }) {
      if (id === '') {
        return
      }

      console.log('save', id, attributes, type)
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
          ...this.newTag
        }
      }).then(response => {
        console.log(response, 'response new tag')
        this.isInEditState = ''
        this.newTag = this.tagAttributes

        this.toggleCreateModal({ type: 'Tag' })
        this.updateTagTopic({
          id: '',
          type: 'TagTopic',
          relationships: {
            tags: {
              data: {
                type: 'Tag',
                id: this.Tag[this.Tag.length - 1].id
              }
            }
          }
        })
      })
    },

    saveNewTopic () {
      console.log('saveNewTopic', this.newTopic)
      this.createTagTopic({
        type: 'TagTopic',
        attributes: {
          title: this.newTopic.title
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
  }
}
</script>
