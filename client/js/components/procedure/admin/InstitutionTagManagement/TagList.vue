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
        :disabled="tagCategories.length === 0"
        :text="Translator.trans('tag.new')"
        @click="handleAddNewTagForm" />
      <dp-button
        :color="tagCategories.length === 0 ? 'primary' : 'secondary'"
        data-cy="tagList:newCategory"
        :text="Translator.trans('tag.category.new')"
        @click="handleAddNewCategoryForm" />
    </div>
    <new-tag-form
      v-if="addNewTag"
      :tag-categories="tagCategories"
      @newTagForm:close="closeNewTagForm"
      @newTag:created="getInstitutionTagCategories()" />

    <new-category-form
      v-if="addNewCategory"
      :tag-categories="tagCategories"
      @newCategoryForm:close="closeNewCategoryForm"
      @newCategory:created="getInstitutionTagCategories()" />

    <div class="mt-4">
      <dp-loading
        v-if="isLoading"
        class="min-h-[32px]" />
      <template v-else>
        <dp-inline-notification
          v-if="tagCategories.length === 0"
          type="info"
          class="u-mt-1_5 u-mb"
          :message="Translator.trans('explanation.noentries')" />

        <dp-tree-list
          v-else
          align-toggle="center"
          :branch-identifier="isBranch"
          :tree-data="tagCategories">
          <template v-slot:header>
            <div>
              {{ Translator.trans('category_or_tag') }}
            </div>
          </template>
          <template v-slot:branch="{ nodeElement }">
            <tag-list-item :item="nodeElement" />
          </template>
          <template v-slot:leaf="{ nodeElement }">
            <tag-list-item :item="nodeElement" />
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
      headerFields: [
        {
          field: 'name',
          label: Translator.trans('Kategorie / Schlagwort'),
          colClass: 'u-11-of-12'
        }
      ],
      initialRowData: {},
      isLoading: false
    }
  },

  computed: {
    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items'
    }),

    ...mapState('InstitutionTag', {
      institutionTags: 'items'
    }),

    tagCategories () {
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
            'name'
          ].join()
        },
        include: [
          'tags'
        ].join()
      })
        .then(() => {
          this.isLoading = false
        })
        .catch(err => {
          console.error(err)
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

    isBranch ({ node }) {
      return node.type === 'InstitutionTagCategory'
    },

    /**
     * When saving a new tag the comparison of `foundSimilarLabel.length === 0` needs to be executed
     * When updating a new tag the comparison of `foundSimilarLabel.length === 1` needs to be executed instead
     * should always be possible.
     *
     * @param tagLabel { string }
     * @param isNewTagLabel { boolean }
     * @returns { boolean }
     */

    isUniqueTagName (tagLabel, isNewTagLabel = false) {
      const foundSimilarLabel = this.tags.filter(el => el.label === tagLabel)
      return isNewTagLabel ? foundSimilarLabel.length === 0 : foundSimilarLabel.length === 1
    }
  },

  mounted () {
    this.getInstitutionTagCategories()
  }
}
</script>
