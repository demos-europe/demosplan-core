<template>
  <div>
    <tags-list-header />

    <tags-create-form
      :is-master-procedure="isMasterProcedure"
      :procedure-id="procedureId"
    />

    <dp-tree-list
      v-if="transformedCategories"
      class="mb-4"
      align-toggle="center"
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
      :branch-identifier="isBranch"
      @end="handleDragEnd"
    >
      <template v-slot:header>
        <div class="flex">
          <div class="ml-4 flex-1">
            {{ Translator.trans('topic.or.tag') }}
          </div>

          <addon-wrapper hook-name="tag.extend.form" />

          <div class="ml-1 flex-0 w-9">
            {{ Translator.trans('boilerplates') }}
          </div>

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
          @abort="closeEditForm"
          @delete="deleteItem"
          @edit="setEditState"
          @save="save"
        />
      </template>
      <template v-slot:leaf="{ nodeElement }">
        <tag-list-edit-form
          :node-element="nodeElement"
          :is-in-edit-state="isInEditState"
          :procedure-id="procedureId"
          type="Tag"
          @abort="closeEditForm"
          @delete="deleteItem"
          @edit="setEditState"
          @save="save"
        />
      </template>
    </dp-tree-list>

    <dp-loading v-else />

    <tags-import-form
      class="mb-1"
      :procedure-id="procedureId"
    />
  </div>
</template>

<script>
import {
  DpLoading,
  dpRpc,
  DpTreeList,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import TagListEditForm from './TagListEditForm'
import TagsCreateForm from './TagsCreateForm'
import TagsImportForm from './TagsImportForm'
import TagsListHeader from './TagsListHeader'
export default {
  name: 'TagsList',

  components: {
    AddonWrapper,
    DpLoading,
    DpTreeList,
    TagsCreateForm,
    TagsImportForm,
    TagListEditForm,
    TagsListHeader,
  },

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

  data () {
    return {
      dataIsRequested: false,
      isInEditState: '',
    }
  },

  computed: {
    ...mapState('Tag', {
      Tag: 'items',
    }),
    ...mapState('TagTopic', {
      TagTopic: 'items',
    }),

    transformedCategories () {
      // Sort topics naturally (handles numbers: "1, 2, 3, 11, 12" instead of "1, 11, 12, 2, 3")
      return Object.values(this.TagTopic)
        .sort((a, b) => a.attributes.title.localeCompare(b.attributes.title, undefined, { numeric: true, sensitivity: 'base' }))
        .map(category => {
          const { attributes, id, relationships, type } = category
          const tags = category.relationships?.tags?.data.length > 0 ? Object.values(category.relationships.tags.list()) : []

          return {
            id,
            attributes,
            children: tags.map(tag => {
              const { attributes, id, relationships, type } = tag
              const boilerplate = relationships?.boilerplate?.get ? relationships.boilerplate.get() : null

              return {
                attributes,
                id,
                relationships: { boilerplate },
                type,
              }
            }),
            relationships,
            type,
          }
        })
    },
  },

  methods: {
    ...mapMutations('Tag', {
      updateTag: 'setItem',
    }),

    ...mapMutations('TagTopic', {
      updateTagTopic: 'setItem',
    }),

    ...mapActions('Tag', {
      createTag: 'create',
      listTags: 'list',
      saveTag: 'save',
    }),

    ...mapActions('TagTopic', {
      createTagTopic: 'create',
      listTagTopics: 'list',
      saveTagTopic: 'save',
    }),

    closeEditForm () {
      this.isInEditState = ''
    },

    addTagToNewTopic (parentTopic, tagId) {
      this.updateTagTopic({
        id: parentTopic.id,
        type: 'TagTopic',
        attributes: parentTopic.attributes,
        relationships: parentTopic.relationships ?
          {
            ...parentTopic.relationships,
            tags: {
              data: parentTopic.relationships.tags.data.concat({
                type: 'Tag',
                id: tagId,
              }),
            },
          } :
          {
            tags: {
              data: [{ type: 'Tag', id: tagId }],
            },
          },
      })

      this.saveTagTopic(parentTopic.id)
    },

    // Handles the @end event from DpTreeList — fires once per drag, on drop instead of multible times like the @draggable:change event from DpTreeListNode
    handleDragEnd (event, item, parentId) {
      console.log('end fired:', { event, item, parentId })

      if (!parentId) {

        return
      }
    },

    deleteItem (item) {
      dpRpc('bulk.delete.tags.and.topics', { ids: [item] })
        .then(() => {
          this.loadTagsAndTopics()
        })
    },

    isBranch ({ node }) {
      return node.type === 'TagTopic'
    },

    loadTagsAndTopics () {
      if (this.dataIsRequested) return

      this.dataIsRequested = true
      const topicAttributes = [
        'title',
        'tags',
      ]

      this.listTagTopics({
        fields: {
          Tag: ['boilerplate', 'title'].join(),
          TagTopic: topicAttributes.join(),
          Boilerplate: [
            'title',
          ].join(),
        },
        include: 'tags,tags.boilerplate',
        sort: 'title',
      }).then(() => {
        this.dataIsRequested = false
      })
    },

    removeTagFromOldTopic (oldParent, tagId) {
      const oldParentTags = [...oldParent.relationships?.tags?.data || []]
      const indexToBeRemoved = oldParentTags.findIndex(el => el.id === tagId)
      oldParentTags.splice(indexToBeRemoved, 1)

      this.updateTagTopic({
        id: oldParent.id,
        attributes: oldParent.attributes,
        type: 'TagTopic',
        relationships: {
          tags: {
            data: oldParentTags,
          },
        },
      })

      this.saveTagTopic(oldParent.id)
    },

    save ({ id, attributes, type, isTitleChanged }) {
      if (!id || !isTitleChanged) {
        this.closeEditForm()

        return
      }

      const updateMethod = `update${type}`
      const saveMethod = `save${type}`

      this[updateMethod]({
        attributes,
        id,
        relationships: this[type][id]?.relationships,
        type,
      })
      this[saveMethod](id)
        .then(() => {
          this.closeEditForm()
        })
    },

    setEditState ({ id }) {
      this.isInEditState = id
    },
  },

  mounted () {
    this.loadTagsAndTopics()
  },
}
</script>
