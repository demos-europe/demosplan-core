<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <form data-dp-validate="addNewCategoryForm">
    <dp-loading
      v-if="isLoading"
      overlay />
    <div class="border rounded space-stack-m space-inset-m">
      <div class="relative u-pb-0_5 font-size-large">
        {{ Translator.trans('tag.category.new.create') }}
        <button
          class="btn--blank o-link--default float-right"
          @click="handleCloseForm()">
          <dp-icon icon="close" />
        </button>
      </div>
      <dp-input
        id="createCategory"
        v-model="newCategory.name"
        data-cy="newCategoryForm:category"
        :label="{
          text: Translator.trans('name')
        }"
        required />
      <dp-button-row
        alignment="left"
        :busy="isLoading"
        primary
        secondary
        @primary-action="dpValidateAction('addNewCategoryForm', () => saveNewCategory(), false)"
        @secondary-action="handleCloseForm()" />
    </div>
  </form>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpIcon,
  DpInput,
  DpLoading,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import {mapActions} from "vuex";
export default {
  name: 'NewCategoryForm',

  components: {
    DpButton,
    DpButtonRow,
    DpIcon,
    DpInput,
    DpLoading
  },

  mixins: [dpValidateMixin],

  props: {
    tagCategories: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      isLoading: false,
      newCategory: {}
    }
  },

  methods: {
    ...mapActions('InstitutionTagCategory', {
      createInstitutionTagCategory: 'create'
    }),

    handleCloseForm() {
      this.$emit('newCategoryForm:close')
      this.newCategory.label = null
    },

    isCategoryNameUnique (name) {
      return !this.tagCategories.some(category => category.name === name)
    },

    resetNewCategoryForm () {
      this.newCategory = {}
      this.$emit('newCategoryForm:close')
    },

    saveNewCategory () {
      if (!this.isCategoryNameUnique(this.newCategory.name)) {
        dplan.notify.error(Translator.trans('tag.category.name.unique.error'))

        return
      }

      this.isLoading = true

      // Persist changes in database
      const payload = {
        type: 'InstitutionTagCategory',
        attributes: {
          name: this.newCategory.name
        }
      }
      this.createInstitutionTagCategory(payload)
        .then(() => {
          this.$emit('newCategory:created')
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => {
          console.error(err)
        })
        .finally(() => {
          this.isLoading = false
          this.resetNewCategoryForm()
        })
    },
  }
}
</script>
