<template>
  <form
    class="grid grid-cols-[1fr,auto,auto] items-center gap-1"
    data-dp-validate="editTagOrCategoryForm">
    <div class="flex space-x-1">
      <dp-icon
        v-if="item.type === 'InstitutionTag'"
        class="text-muted mt-[2px]"
        icon="tag" />
      <div
        v-if="!isEditing"
        v-text="item.name" />
      <dp-input
        v-else
        data-cy="tagListItem:tagName"
        id="tagName"
        maxlength="250"
        required
        v-model="name" />
    </div>
    <div class="flex">
      <template v-if="!isEditing">
        <dp-button
          class="u-pl-0"
          color="secondary"
          data-cy="tagListItem:edit"
          hide-text
          icon="edit"
          :text="Translator.trans('edit')"
          variant="subtle"
          @click="edit" />
        <dp-button
          color="secondary"
          data-cy="tagListItem:delete"
          hide-text
          icon="delete"
          :text="Translator.trans('delete')"
          variant="subtle"
          @click="deleteItem" />
      </template>
      <template v-else>
        <dp-button
          color="primary"
          data-cy="tagListItem:save"
          hide-text
          icon="check"
          :text="Translator.trans('save')"
          variant="subtle"
          @click="dpValidateAction('editTagOrCategoryForm', save, false)" />
        <dp-button
          class="u-pl-0"
          color="primary"
          data-cy="tagListItem:abort"
          hide-text
          icon="xmark"
          :text="Translator.trans('abort')"
          variant="subtle"
          @click="abort" />
      </template>
    </div>
  </form>
</template>

<script>
import {
  DpButton,
  DpIcon,
  DpInput,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

export default {
  name: 'TagListItem',

  components: {
    DpButton,
    DpIcon,
    DpInput
  },

  mixins: [dpValidateMixin],

  props: {
    /**
     * If item is a category, it also has the property 'children'
     * If item is a tag, it also has the properties 'categoryId' and 'isUsed'
     */
    item: {
      type: Object,
      required: true,
      validator: (item) => {
        return item.id && item.type && item.name
      }
    }
  },

  emits: [
    'item:deleted',
    'item:saved',
    'tagIsRemoved'
  ],

  data () {
    return {
      isEditing: false,
      name: this.item.name
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
      deleteInstitutionTagCategory: 'delete',
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

    abort () {
      this.name = this.item.name
      this.isEditing = false
    },

    confirmAndDeleteCategory () {
      if (dpconfirm(Translator.trans('check.category.delete', { categoryTitle: this.item.name }))) {
        this.deleteCategory()
          .then(() => {
            this.$emit('item:deleted', this.item)
          })
      }
    },

    confirmAndDeleteCategoryWithTags () {
      const { children, name } = this.item
      const tagsInUse = children
        .filter(tag => tag.isUsed)
        .map(tag => tag.name)

      if (tagsInUse.length > 0) {
        if (dpconfirm(Translator.trans('check.category_with_tags_in_use.delete', { category: name, count: tagsInUse.length, tags: tagsInUse.join(', ') }))) {
          this.deleteCategoryAndTags()
        }
      } else if (tagsInUse.length === 0) {
        if (dpconfirm(Translator.trans('check.category_with_tags_not_in_use.delete', { category: name }))) {
          this.deleteCategoryAndTags()
        }
      }
    },

    confirmAndDeleteTag () {
      const { isUsed, name } = this.item
      const message = isUsed
        ? Translator.trans('check.tag_is_used.delete')
        : Translator.trans('check.tag.delete', { tag: name })

      if (dpconfirm(message)) {
        this.deleteTag()
      }
    },

    deleteCategoryAndTags () {
      const { id, name } = this.item

      // Tags are deleted in BE along with category
      this.deleteInstitutionTagCategory(id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confim.category_and_tags.deleted', { category: name }))
          this.$emit('item:deleted', this.item)
        })
        .catch(error => {
          console.error(error)
        })
    },

    deleteCategory () {
      const { id, name } = this.item

      return this.deleteInstitutionTagCategory(id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.category.deleted', { category: name }))
        })
        .catch(error => {
          console.error(error)
        })
    },

    deleteItem () {
      const { type } = this.item
      const isCategory = type === 'InstitutionTagCategory'
      const isTag = type === 'InstitutionTag'

      if (isCategory) {
        this.handleCategoryDeletion()
      } else if (isTag) {
        this.confirmAndDeleteTag()
      }
    },

    deleteTag (tag = this.item) {
      return this.deleteInstitutionTag(tag.id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.tag.deleted', { title: tag.name }))
          this.$emit('tagIsRemoved')
          this.$emit('item:deleted', tag)
        })
        .catch(error => {
          console.error(error)
        })
    },

    edit () {
      this.isEditing = true
    },

    handleCategoryDeletion () {
      const { children } = this.item
      const hasTags = children.length > 0

      if (!hasTags) {
        this.confirmAndDeleteCategory()
      } else {
        this.confirmAndDeleteCategoryWithTags()
      }
    },

    save () {
      const { name, type } = this.item

      if (name === this.name) {
        this.isEditing = false
        return
      }

      if (type === 'InstitutionTagCategory') {
        this.saveCategory()
      }

      if (type === 'InstitutionTag') {
        this.saveTag()
      }
    },

    saveCategory () {
      const { id, type } = this.item

      this.updateInstitutionTagCategory({
        id,
        type,
        attributes: {
          ...this.institutionTagCategories[id].attributes,
          name: this.name
        }
      })

      this.saveInstitutionTagCategory(id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.category.updated'))
          this.isEditing = false
          this.$emit('item:saved', { ...this.item, name: this.name })
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('error.api.generic'))
          this.restoreTagCategoryFromInitial(id)
        })
    },

    saveTag () {
      const { id, type } = this.item

      this.updateInstitutionTag({
        id,
        type,
        attributes: {
          ...this.institutionTags[id].attributes,
          name: this.name
        }
      })

      this.saveInstitutionTag(id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.tag.edited'))
          this.isEditing = false
          this.$emit('item:saved', { ...this.item, name: this.name })
        })
        .catch(() => {
          this.restoreTagFromInitial(id)
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    }
  }
}
</script>
