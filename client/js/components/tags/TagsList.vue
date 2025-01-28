<template>
  <div>
    <tag-list-header />

    <dp-modal
      content-classes="w-2/3"
      ref="createTagModal"
      :aria-label="Translator.trans('tag.new.create')"
      aria-modal="true">
      <template v-slot:header>
        Create Tag
      </template>

      <template>
        <dp-input
          v-model="newTag.title"
          id="new-tag-title"
          class="mb-1"
          :label="{
            text: Translator.trans('tag.new.create')
          }"
          :placeholder="Translator.trans('title')" />

        <div class="mb-1">
          <dp-select :options="topicsAsOptions" v-model="newTag.topic" />
        </div>

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
      ref="createTagTopicModal"
      aria-label="Create Topic"
      aria-modal="true">
      <template v-slot:header>
        {{ Translator.trans('topic.create') }}
      </template>

      <dp-input
        v-model="newTopic.title"
        id="new-topic-title"
        class="mt-2"
        :placeholder="Translator.trans('title')" />
      <div class="flex justify-end mt-2">
        <dp-button
          :text="Translator.trans('topic.create.short')"
          @click="saveNewTopic" />
      </div>
    </dp-modal>

    <div class="flex justify-end mb-2">
      <dp-button
        :text="Translator.trans('tag.create')"
        @click="() => toggleCreateModal({ type: 'Tag' })" />
      <dp-button
        class="ml-1"
        color="secondary"
        :text="Translator.trans('topic.create.short')"
        variant="outline"
        @click="() => toggleCreateModal({ type: 'TagTopic' })" />
    </div>

    <dp-tree-list
      v-if="transformedCategories"
      class="mb-4"
      :tree-data="transformedCategories"
      :options="{
        branchesSelectable: false,
        dragAcrossBranches: true,
        dragLeaves: true,
        leavesSelectable: false,
        selectOn: {
          parentSelect: true,
          childDeselect: true
        }
      }"
      :branch-identifier="branchFunc()"
      @draggable:change="changeTopic">
      <template v-slot:header>
        <div class="flex">
          <div class="ml-4 flex-1">
            {{ Translator.trans('topic.or.tag') }}
          </div>
          <div class="ml-1 flex-0 w-9">
            {{ Translator.trans('boilerplates') }}
          </div>

          <addon-wrapper hook-name="tag.extend.form" />

          <div class="ml-1 flex-0 w-8 text-right">
            {{ Translator.trans('actions') }}
          </div>
        </div>
      </template>
      <template v-slot:branch="{ nodeElement }">
        <tag-list-edit-form
          class="font-bold"
          :node-element="nodeElement"
          :is-in-edit-state="isInEditState"
          :procedure-id="procedureId"
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
          :procedure-id="procedureId"
          type="Tag"
          @abort="abort"
          @delete="deleteItem"
          @edit="setEditState"
          @save="save" />
      </template>
    </dp-tree-list>

    <dp-loading v-else />

    <tags-import-form
      class="mb-1"
      :procedure-id="procedureId" />
  </div>
</template>

<script>
import {
  checkResponse,
  DpButton,
  DpCheckbox,
  DpIcon,
  DpInput,
  DpLoading,
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
    AddonWrapper,
    DpButton,
    DpCheckbox,
    DpIcon,
    DpInput,
    DpLoading,
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
        const { attributes, id } = { ...category }

        return {
          label: attributes.title,
          value: id
        }
      })
    },

    transformedCategories () {
      return Object.values(this.TagTopic).map(category => {
        const { attributes, id, relationships, type } = category
        const tags = category.relationships?.tags?.data.length > 0 ? category.relationships.tags.list() : []

        return {
          id,
          attributes,
          children: Object.values(tags).map(tag => {
            const { attributes, id, relationships, type } = tag
            const boilerplate = relationships?.boilerplate?.get ? relationships.boilerplate.get() : null

            return {
              attributes,
              id,
              relationships: { boilerplate },
              type
            }
          }),
          relationships,
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

    changeTopic ({ elementId, parentId }) {
      console.log('changeTopic', { elementId, parentId })

      if (!parentId) {
        console.error('No parentId provided:', { elementId, parentId })

        return
      }

      const hasNewParent = !this.TagTopic[parentId].relationships.tags.data.find(tag => tag.id === elementId)

      if (hasNewParent) {
        const parentTopic = { ...this.TagTopic[parentId] }
        const oldParent = Object.values(this.TagTopic).find(topic => topic.relationships.tags.data.find(tag => tag.id === elementId))

        // Add tag to new topic
        this.updateTagTopic({
          id: parentTopic.id,
          type: 'TagTopic',
          attributes: parentTopic.attributes,
          relationships: {
            tags: {
              data: [
                ...parentTopic.relationships.tags.data,
                {
                  id: elementId,
                  type: 'Tag'
                }
              ]
            }
          }
        })

        // remove Tag from old topic
        const oldParentTags = [...oldParent.relationships?.tags?.data || []]
        const indexToBeRemoved = oldParentTags.findIndex(el => el.id === elementId)
        oldParentTags.splice(indexToBeRemoved, 1)

        this.updateTagTopic({
          id: oldParent.id,
          attributes: oldParent.attributes,
          type: 'TagTopic',
          relationships: {
            tags: {
              data: oldParentTags
            }
          }
        })

        this.saveTagTopic(parentTopic.id)
        this.saveTagTopic(oldParent.id)
          .then(payload => {
            console.log('tagtopic saved', payload)
          })
      } else {
        dplan.notify.notify('warning', Translator.trans('tags.can.only.be.moved.to.topics'))
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
          TagTopic: topicAttributes.join(),
          Boilerplate: [
            'title'
          ].join()
        },
        include: 'tags,tags.boilerplate',
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

      console.log('save', { id, attributes, type })

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
          const parentTopic = this.TagTopic[this.newTag.topic]

          if (!response.data.Tag || !parentTopic) {
            return
          }

          const newTagId = Object.keys(response.data.Tag)[0]
          const topicRelations = parentTopic.relationships

          this.updateTagTopic({
            id: this.newTag.topic,
            type: 'TagTopic',
            attributes: parentTopic.attributes,
            relationships: {
              tags: {
                data: [
                  ...topicRelations.tags.data,
                  {
                    type: 'Tag',
                    id: newTagId
                  }
                ]
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
