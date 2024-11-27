<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <form data-dp-validate="addNewTagForm">
    <dp-loading
      v-if="isLoading"
      overlay />
    <div class="border rounded space-stack-m space-inset-m">
      <div class="relative u-pb-0_5 font-size-large">
        {{ Translator.trans('tag.new.create') }}
        <button
          class="btn--blank o-link--default float-right"
          @click="handleCloseForm()">
          <dp-icon icon="close" />
        </button>
      </div>
      <dp-input
        id="createTag"
        v-model="newTag.name"
        data-cy="newTagForm:tag"
        :label="{
          text: Translator.trans('name')
        }"
        required />
      <dp-select
        v-model="newTag.category"
        data-cy="newTagForm:category"
        :label="{
          text: Translator.trans('category')
        }"
        :options="tagCategoryOptions"
        required />
      <dp-button-row
        alignment="left"
        :busy="isLoading"
        primary
        secondary
        @primary-action="dpValidateAction('addNewTagForm', () => saveNewTag(), false)"
        @secondary-action="handleCloseForm()" />
    </div>
  </form>
</template>

<script>
import {
  checkResponse,
  DpButton,
  DpButtonRow,
  DpIcon,
  DpInput,
  DpLoading,
  DpSelect,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions } from 'vuex'
export default {
  name: 'NewTagForm',

  components: {
    DpButton,
    DpButtonRow,
    DpIcon,
    DpInput,
    DpLoading,
    DpSelect
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
      newTag: {}
    }
  },

  computed: {
    tagCategoryOptions () {
      return this.tagCategories.map(category => ({
        value: category.id,
        label: category.name
      }))
    }
  },

  methods: {
    ...mapActions('InstitutionTag', {
      createInstitutionTag: 'create'
    }),

    handleCloseForm () {
      this.$emit('newTagForm:close')
      this.newTag = {}
    },

    isTagNameUnique (name, categoryId) {
      return !this.tagCategories.some(category =>
        category.id === categoryId &&
        category.children.some(tag => tag.name === name)
      )
    },

    resetNewTagForm () {
      this.newTag = {}
      this.$emit('newTagForm:close')
    },

    saveNewTag () {
      if (!this.isTagNameUnique(this.newTag.name, this.newTag.category)) {
        dplan.notify.error(Translator.trans('tag.name.unique.error'))

        return
      }

      this.isLoading = true

      // Persist changes in database
      const payload = {
        type: 'InstitutionTag',
        attributes: {
          name: this.newTag.name,
        },
        relationships: {
          category: {
            data: {
              type: 'InstitutionTagCategory',
              id: this.newTag.category
            }
          }
        }
      }
      this.createInstitutionTag(payload)
        .then(() => {
          this.$emit('newTag:created')
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => {
          console.error(err)
        })
        .finally(() => {
          this.isLoading = false
          this.resetNewTagForm()
        })
    },
  }
}
</script>
