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
      @end="handleTagReorder"
    >
      <template v-slot:header>
        <div class="flex">
          <div class="ml-4 flex-1">
            {{ Translator.trans('topic.or.tag') }}
          </div>

          <addon-wrapper hook-name="tag.extend.form" />

          <div
            v-if="hasPermission('feature_tag_default_assignee')"
            class="ml-1 flex-none w-10"
          >
            {{ Translator.trans('assignee') }}
          </div>

          <div class="ml-1 flex-none w-9">
            {{ Translator.trans('boilerplates') }}
          </div>

          <div class="ml-1 flex-none w-8 text-right">
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
              const defaultAssignee = relationships?.defaultAssignee?.get ? relationships.defaultAssignee.get() : null

              return {
                attributes,
                id,
                relationships: { boilerplate, defaultAssignee },
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

    // Apply tagList.reorder RPC response — sync sortIndex in the Tag store for every changed tag
    applyReorderResponse (response) {
      const result = response.data[0].result

      for (const [id, { sortIndex }] of Object.entries(result)) {
        const tag = this.Tag[id]

        if (tag) {
          this.updateTag({
            ...tag,
            attributes: { ...tag.attributes, sortIndex },
          })
        }
      }

      dplan.notify.confirm(Translator.trans('confirm.saved'))
    },

    closeEditForm () {
      this.isInEditState = ''
    },

    // Persist cross-topic move via tagList.reorder RPC — optimistic update on both topics + rollback
    crossTopicReorder (tagId, newIndex, targetTopicId) {
      const newParent = this.TagTopic[targetTopicId]

      if (!newParent) {
        return
      }

      const oldParent = Object.values(this.TagTopic).find(topic =>
        topic.relationships?.tags.data.some(tag => tag.id === tagId),
      )

      if (!oldParent) {
        return
      }

      const oldSourceData = [...oldParent.relationships.tags.data]
      const oldTargetData = [...(newParent.relationships?.tags?.data || [])]

      const newSourceData = oldSourceData.filter(tag => tag.id !== tagId)
      const newTargetData = [...oldTargetData]

      newTargetData.splice(newIndex, 0, { type: 'Tag', id: tagId })

      this.updateTagTopic({
        id: oldParent.id,
        type: 'TagTopic',
        attributes: oldParent.attributes,
        relationships: { ...oldParent.relationships, tags: { data: newSourceData } },
      })

      this.updateTagTopic({
        id: newParent.id,
        type: 'TagTopic',
        attributes: newParent.attributes,
        relationships: { ...newParent.relationships, tags: { data: newTargetData } },
      })

      dpRpc('tagList.reorder', { tagId, topicId: targetTopicId, newIndex })
        .then((response) => this.applyReorderResponse(response))
        .catch(error => {
          console.error(error)
          this.updateTagTopic({
            id: oldParent.id,
            type: 'TagTopic',
            attributes: oldParent.attributes,
            relationships: { ...oldParent.relationships, tags: { data: oldSourceData } },
          })
          this.updateTagTopic({
            id: newParent.id,
            type: 'TagTopic',
            attributes: newParent.attributes,
            relationships: { ...newParent.relationships, tags: { data: oldTargetData } },
          })
          dplan.notify.error(Translator.trans('error.changes.not.saved'))
        })
    },

    deleteItem (item) {
      dpRpc('bulk.delete.tags.and.topics', { ids: [item] })
        .then(() => {
          this.loadTagsAndTopics()
        })
    },

    handleTagReorder (event, item, parentId) {
      if (event.oldIndex === event.newIndex && event.from === event.to) {
        return
      }

      const isCrossTopic = event.from !== event.to

      if (isCrossTopic) {
        this.crossTopicReorder(item.id, event.newIndex, event.to.id)
      } else {
        this.reorderTagInTopic(parentId, item.id, event.newIndex)
      }
    },

    isBranch ({ node }) {
      return node.type === 'TagTopic'
    },

    loadTagsAndTopics () {
      if (this.dataIsRequested) {
        return
      }

      this.dataIsRequested = true
      const hasDefaultAssignee = hasPermission('feature_tag_default_assignee')
      const topicAttributes = [
        'title',
        'tags',
      ]
      const tagAttributes = hasDefaultAssignee ?
        ['boilerplate', 'defaultAssignee', 'sortIndex', 'title'] :
        ['boilerplate', 'sortIndex', 'title']
      const include = hasDefaultAssignee ?
        'tags,tags.boilerplate,tags.defaultAssignee' :
        'tags,tags.boilerplate'
      const fields = {
        Tag: tagAttributes.join(),
        TagTopic: topicAttributes.join(),
        Boilerplate: [
          'title',
        ].join(),
      }

      if (hasDefaultAssignee) {
        fields.AssignableUser = ['firstname', 'lastname'].join()
      }

      this.listTagTopics({
        fields,
        include,
        sort: 'title',
      }).then(() => {
        this.dataIsRequested = false
      })
    },

    // Persist new tag order within a topic — optimistic update + rollback on failure
    reorderTagInTopic (parentId, tagId, newIndex) {
      const topic = this.TagTopic[parentId]

      if (!topic) {
        return
      }

      const oldTagsData = [...(topic.relationships?.tags?.data || [])]
      const newTagsData = oldTagsData.filter(tag => tag.id !== tagId)

      newTagsData.splice(newIndex, 0, { type: 'Tag', id: tagId })

      // Optimistic UI update
      this.updateTagTopic({
        id: topic.id,
        type: 'TagTopic',
        attributes: topic.attributes,
        relationships: {
          ...topic.relationships,
          tags: {
            data: newTagsData,
          },
        },
      })

      dpRpc('tagList.reorder', { tagId, topicId: parentId, newIndex })
        .then((response) => this.applyReorderResponse(response))
        .catch(error => {
          console.error(error)
          // Rollback
          this.updateTagTopic({
            id: topic.id,
            type: 'TagTopic',
            attributes: topic.attributes,
            relationships: { ...topic.relationships, tags: { data: oldTagsData } },
          })
          dplan.notify.error(Translator.trans('error.changes.not.saved'))
        })
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
