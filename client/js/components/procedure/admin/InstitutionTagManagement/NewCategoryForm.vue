<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div data-dp-validate="addNewCategoryForm">
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
        v-model="newCategory.label"
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
  </div>
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

  data () {
    return {
      isLoading: false,
      newCategory: {}
    }
  },

  methods: {
    ...mapActions('InstitutionCategory', {
      createInstitutionCategory: 'create'
    }),

    handleCloseForm() {
      this.$emit('newCategoryForm:close')
      this.newCategory.label = null
    },

    resetNewCategoryForm () {
      this.newCategory = {}
      this.$emit('newCategoryForm:close')
    },

    saveNewCategory () {
      // TO DO: Do we need to check for unique in FE?
      this.isLoading = true

      // Persist changes in database
      const payload = {
        type: 'InstitutionCategory',
        attributes: {
          label: this.newCategory.label
        }
      }
      this.createInstitutionCategory(payload)
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
