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
          color="secondary"
          data-cy="tagListItem:delete"
          hide-text
          icon="delete"
          :text="Translator.trans('delete')"
          variant="subtle"
          @click="$emit('delete', item)" />
        <dp-button
          class="u-pl-0"
          color="secondary"
          data-cy="tagListItem:edit"
          hide-text
          icon="edit"
          :text="Translator.trans('edit')"
          variant="subtle"
          @click="edit" />
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
    item: {
      type: Object,
      required: true,
      validator: (item) => {
        return item.id && item.type && item.name
      }
    }
  },

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
      restoreTagCategoryFromInitial: 'restoreFromInitial',
      saveInstitutionTagCategory: 'save'
    }),

    ...mapActions('InstitutionTag', {
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

    edit () {
      this.isEditing = true
    },

    save () {
      if (this.item.name === this.name) {
        this.isEditing = false
        return
      }

      if (this.item.type === 'InstitutionTagCategory') {
        this.saveCategory()
      }

      if (this.item.type === 'InstitutionTag') {
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

      this.saveInstitutionTagCategory(this.item.id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.category.updated'))
          this.isEditing = false
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
        })
        .catch(() => {
          this.restoreTagFromInitial(id)
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    }
  }
}
</script>
