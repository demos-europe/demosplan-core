<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="u-mt-0_5">
    <div
      v-if="!addNewTag && !addNewCategory"
      class="text-right">
      <dp-button
        data-cy="tagList:newTag"
        :disabled="tagCategoriesWithTags.length === 0"
        :text="Translator.trans('tag.new')"
        @click="handleAddNewTagForm" />
      <dp-button
        :color="tagCategoriesWithTags.length === 0 ? 'primary' : 'secondary'"
        data-cy="tagList:newCategory"
        :text="Translator.trans('tag.category.new')"
        @click="handleAddNewCategoryForm" />
    </div>
    <new-tag-form
      v-if="addNewTag"
      :tag-categories="tagCategoriesWithTags"
      @newTagForm:close="closeNewTagForm"
      @newTag:created="getInstitutionTagCategories" />

    <new-category-form
      v-if="addNewCategory"
      :tag-categories="tagCategoriesWithTags"
      @newCategoryForm:close="closeNewCategoryForm"
      @newCategory:created="getInstitutionTagCategories" />

    <div class="mt-4">
      <dp-loading
        v-if="isLoading"
        class="min-h-[32px]" />
      <template v-else>
        <dp-inline-notification
          v-if="tagCategoriesWithTags.length === 0"
          type="info"
          class="u-mt-1_5 u-mb"
          :message="Translator.trans('explanation.noentries')" />

        <dp-tree-list
          v-else
          align-toggle="center"
          :branch-identifier="isBranch"
          :tree-data="tagCategoriesWithTags">
          <template v-slot:header>
            <div>
              {{ Translator.trans('category_or_tag') }}
            </div>
          </template>
          <template v-slot:branch="{ nodeElement }">
            <tag-list-item
              :item="nodeElement"
              @item:deleted="handleItemDeleted"
              @item:saved="handleItemSaved" />
          </template>
          <template v-slot:leaf="{ nodeElement }">
            <tag-list-item
              :item="nodeElement"
              @item:deleted="handleItemDeleted"
              @item:saved="handleItemSaved" />
          </template>
        </dp-tree-list>
      </template>
    </div>
  </div>
</template>

<script>
import {
  DpButton,
  DpInlineNotification,
  DpLoading,
  DpTreeList
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import NewCategoryForm from './NewCategoryForm'
import NewTagForm from './NewTagForm'
import TagListItem from './TagListItem'

export default {
  name: 'TagList',

  components: {
    DpButton,
    DpInlineNotification,
    DpLoading,
    DpTreeList,
    NewCategoryForm,
    NewTagForm,
    TagListItem
  },

  data () {
    return {
      addNewCategory: false,
      addNewTag: false,
      edit: false,
      editingCategoryId: null,
      editingTagId: null,
      isLoading: false,
      tagCategoriesWithTags: []
    }
  },

  computed: {
    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items'
    }),

    ...mapState('InstitutionTag', {
      institutionTags: 'items'
    })
  },

  methods: {
    ...mapActions('InstitutionTagCategory', {
      listInstitutionTagCategories: 'list'
    }),

    closeNewCategoryForm () {
      this.addNewCategory = false
    },

    closeNewTagForm () {
      this.addNewTag = false
    },

    getInstitutionTagCategories () {
      this.isLoading = true

      this.listInstitutionTagCategories({
        fields: {
          InstitutionTagCategory: [
            'name',
            'tags'
          ].join(),
          InstitutionTag: [
            'isUsed',
            'name',
            'category'
          ].join()
        },
        include: [
          'tags',
          'tags.category'
        ].join()
      })
      .then(() => {
        this.tagCategoriesWithTags = this.transformTagsAndCategories()
      })
      .catch(err => {
        console.error(err)
      })
      .finally(() => {
        this.isLoading = false
      })
    },

    handleAddNewCategoryForm () {
      this.addNewCategory = true
      this.editingCategoryId = null
    },

    handleAddNewTagForm () {
      this.addNewTag = true
      this.editingTagId = null
    },

    /**
     * Remove item from the list
     * @param item { object } With properties id, name, type; if it's a category, also children;
     * if it's a tag, also categoryId and isUsed
     */
    handleItemDeleted (item) {
      const isTag = !!item.categoryId
      const isCategory = !item.categoryId

      if (isTag) {
        const category = this.tagCategoriesWithTags.find(category => category.id === item.categoryId)

        category.children = category.children.filter(tag => tag.id !== item.id)
      }

      if (isCategory) {
        this.tagCategoriesWithTags = this.tagCategoriesWithTags.filter(category => category.id !== item.id)
      }
    },

    handleItemSaved (item) {
      const isTag = !!item.categoryId
      const isCategory = !item.categoryId

      if (isTag) {
        const category = this.tagCategoriesWithTags.find(category => category.id === item.categoryId)

        if (category) {
          const tag = category.children.find(tag => tag.id === item.id)
          tag.name = item.name
        }
      }

      if (isCategory) {
        const category = this.tagCategoriesWithTags.find(category => category.id === item.id)

        if (category) {
          category.name = item.name
        }
      }
    },

    isBranch ({ node }) {
      return node.type === 'InstitutionTagCategory'
    },

    transformTagsAndCategories () {
      return Object.values(this.institutionTagCategories).map(category => {
        const { attributes, id, type } = category
        const tags = category.relationships?.tags?.data.length > 0 ? category.relationships.tags.list() : []

        return {
          id,
          name: attributes.name,
          children: Object.values(tags).map(tag => {
            const { id, attributes, type } = tag

            return {
              id,
              categoryId: category.id,
              isUsed: attributes.isUsed,
              name: attributes.name,
              type
            }
          }),
          type
        }
      })
    }
  },

  mounted () {
    this.getInstitutionTagCategories()
  }
}
</script>
