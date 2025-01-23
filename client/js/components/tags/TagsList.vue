<template>
  <div>
    <tags-list-header />

    <tags-list-form :procedure-id="procedureId" />

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
  DpIcon,
  DpInput,
  DpModal,
  dpRpc,
  DpTreeList,
  DpUpload
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import TagsImportForm from './TagsImportForm'
import TagListBulkControls from './TagListBulkControls'
import TagListEditForm from './TagListEditForm'
import TagsListForm from './TagsListForm.vue'
import TagsListHeader from './TagsListHeader'
export default {
  name: 'TagsList',

  components: {
    AddonWrapper,
    DpButton,
    DpIcon,
    DpInput,
    DpModal,
    DpUpload,
    DpTreeList,
    TagsImportForm,
    TagListBulkControls,
    TagListEditForm,
    TagsListHeader,
    TagsListForm
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data() {
    return {
      dataIsRequested: false,
      isInEditState: '',
      // This is necessary to allow extending the Tags-Resource
      tagAttributes: {
        boilerplate: '',
        title: ''
      }
    }
  },

  computed: {
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
    ...mapActions('TagTopic', {
      listTagTopics: 'list'
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

    setEditState ({ id }) {
      this.isInEditState = id
    }
  },

  mounted () {
    this.loadTagsAndTopics()
  }
}
</script>
