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
      @newCategoryForm:close="closeNewCategoryForm"
      @newCategory:created="getInstitutionTagCategories()" />


    <div class="mt-4">
      <dp-loading
        v-if="isLoading"
        class="min-h-[32px]" />
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
          <tag-list-item
            :item="nodeElement"
            @delete="deleteItem"
            @save="saveCategory" />
        </template>
        <template v-slot:leaf="{ nodeElement }">
          <tag-list-item
            :item="nodeElement"
            @delete="deleteItem"
            @save="saveTag" />
        </template>
      </dp-tree-list>
    </div>
  </div>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpDataTable,
  DpIcon,
  DpInput,
  DpLoading,
  dpValidateMixin,
  DpTreeList
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import NewCategoryForm from './NewCategoryForm'
import NewTagForm from './NewTagForm'
import TagListItem from './TagListItem'

export default {
  name: 'TagList',

  components: {
    DpButton,
    DpButtonRow,
    DpDataTable,
    DpIcon,
    DpInput,
    DpLoading,
    DpTreeList,
    NewCategoryForm,
    NewTagForm,
    TagListItem
  },

  mixins: [dpValidateMixin],

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
        },
        {
          field: 'action',
          label: Translator.trans('actions'),
          colClass: 'u-1-of-10'
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
      deleteInstitutionTagCategory: 'delete',
      listInstitutionTagCategories: 'list',
      restoreTagCategoryFromInitial: 'restoreFromInitial',
      saveInstitutionTagCategory: 'save'
    }),

    ...mapActions('InstitutionTag', {
      deleteInstitutionTag: 'delete',
      restoreTagFromInitial: 'restoreFromInitial',
      saveInstitutionTag: 'save'
    }),

    ...mapMutations('InstitutionTagCategory', {
      updateInstitutionTagCategory: 'setItem'
    }),

    ...mapMutations('InstitutionTag', {
      updateInstitutionTag: 'setItem'
    }),

    closeNewCategoryForm () {
      this.addNewCategory = false
    },

    closeNewTagForm () {
      this.addNewTag = false
    },

    confirmAndDeleteCategory (id) {
      if (dpconfirm(Translator.trans('check.category.delete', { categoryTitle: this.institutionTagCategories[id].attributes.name }))) {
        this.deleteTagCategory(id)
      }
    },

    confirmAndDeleteCategoryWithTags (id, children) {
      const tagsAreUsed = true // @todo implement

      if (tagsAreUsed) {
        if (dpconfirm(Translator.trans('Sind Sie sicher, dass Sie die Kategorie und alle Schlagworte darin löschen möchten? Die folgenden Schlagworte sind an Institutionen vergeben: ' +
          'Wenn Sie die Kategorie und die Schlagworte löschen, werden die Schlagworte von den Institutionen entfernt.'))) {
          this.deleteCategoryAndTags(id, children)
        }
      } else if (!tagsAreUsed) {
        if (dpconfirm(Translator.trans('Sind Sie sicher, dass Sie die Kategorie und alle Schlagworte darin löschen möchten? ' +
          'Die Schlagworte sind nicht an Institutionen vergeben.'))) {
          this.deleteCategoryAndTags(id, children)
        }
      }
    },

    confirmAndDeleteTag (id, tagIsUsed) {
      if (tagIsUsed) {
        if (dpconfirm(Translator.trans('Sind Sie sicher, dass Sie das Schlagwort löschen möchten? Es ist aktuell an folgende Institutionen vergeben: ' +
          'Wenn Sie das Schlagwort löschen, wird es von den Institutionen entfernt.'))) {
          this.deleteTag(id)
        }
      } else if (!tagIsUsed) {
        if (dpconfirm(Translator.trans('check.tag.delete', { tag: this.institutionTags[id].attributes.name }))) {
          this.deleteTag(id)
        }
      }
    },

    deleteCategoryAndTags (id, tags) {
      const promises = [
        this.deleteTagCategory(id),
        ...tags.map(tag => this.deleteTag(tag.id))
      ]

      Promise.allSettled(promises)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.deleted'))
        })
        .catch(err => {
          console.error(err)
        })
    },

    deleteTagCategory (id) {
      // return this.deleteInstitutionTagCategory(id)
      //   .then(() => {
      //     dplan.notify.confirm(Translator.trans('confirm.deleted'))
      //   })
      //   .catch(err => {
      //     console.error(err)
      //   })
    },

    deleteItem (item) {
      const { id } = item
      const isCategory = item.type === 'InstitutionTagCategory'
      const isTag = item.type === 'InstitutionTag'
      const hasTags = item.children?.length > 0

      if (isCategory) {
        this.handleCategoryDeletion(id, hasTags, item.children)
      } else if (isTag) {
        this.handleTagDeletion(id)
      }
    },

    deleteTag (id) {
      return this.deleteInstitutionTag(id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.deleted'))
          this.$emit('tagIsRemoved')
        })
        .catch(err => {
          console.error(err)
        })
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

    handleCategoryDeletion (id, hasTags, children) {
      if (!hasTags) {
        this.confirmAndDeleteCategory(id)
      } else {
        this.confirmAndDeleteCategoryWithTags(id, children)
      }
    },

    handleTagDeletion (id) {
      const tagIsUsed = true; // @todo implement
      if (tagIsUsed) {
        this.confirmAndDeleteTag(id, true)
      } else {
        this.confirmAndDeleteTag(id, false)
      }
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
    },

    saveCategory (category) {
      this.updateInstitutionTagCategory({
        id: category.id,
        type: category.type,
        attributes: {
          ...this.institutionTagCategories[category.id].attributes,
          name: category.name
        }
      })

      this.saveInstitutionTagCategory(category.id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.category.updated'))
        })
        .catch(error => {
          console.error(error)
          this.restoreTagCategoryFromInitial(category.id)
        })
    },

    saveTag (id, label) {
      this.updateInstitutionTag({
        id,
        type: this.institutionTags[id].type,
        attributes: {
          ...this.institutionTags[id].attributes,
          label
        }
      })

      this.saveInstitutionTag(id)
        .then(dplan.notify.confirm(Translator.trans('confirm.tag.edited')))
        .catch(err => {
          this.restoreTagFromInitial(id)
          console.error(err)
        })
        .finally(() => {
          this.editingTagId = null
        })
    }
  },

  mounted () {
    this.getInstitutionTagCategories()
  }
}
</script>
