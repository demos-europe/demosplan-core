<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="u-mt-0_5">
    <div
      v-if="!addNewTag"
      class="text-right">
      <dp-button
        :text="Translator.trans('tag.new')"
        @click="handleAddNewTagForm()" />
    </div>
    <div
      v-else
      data-dp-validate="addNewTagForm">
      <dp-loading
        v-if="isLoading"
        overlay />
      <div class="border rounded space-stack-m space-inset-m">
        <div class="relative u-pb-0_5 font-size-large">
          {{ Translator.trans('tag.new') }}
          <button
            class="btn--blank o-link--default float-right"
            @click="closeNewTagForm()">
            <dp-icon icon="close" />
          </button>
        </div>
        <dp-input
          id="createTag"
          v-model="newTag.label"
          :label="{
            text: Translator.trans('name')
          }" />
        <dp-button-row
          :busy="isLoading"
          align="left"
          primary
          secondary
          @primary-action="dpValidateAction('addNewTagForm', () => saveNewTag(newTag), false)"
          @secondary-action="closeNewTagForm()" />
      </div>
    </div>
    <dp-data-table
      data-dp-validate="tagsTable"
      has-flyout
      :header-fields="headerFields"
      track-by="id"
      :items="tagCategories"
      class="u-mt-2">
      <template v-slot:label="rowData">
        <div
          v-if="!rowData.edit"
          v-text="rowData.name" />
        <dp-input
          v-else
          id="editInstitutionTag"
          maxlength="250"
          required
          v-model="rowData.name" />
      </template>
      <template v-slot:action="rowData">
        <div class="float-right">
          <template v-if="!rowData.edit">
            <button
              :aria-label="Translator.trans('item.edit')"
              class="btn--blank o-link--default"
              @click="editTag(rowData.id)">
              <i
                class="fa fa-pencil"
                aria-hidden="true" />
            </button>
            <button
              :aria-label="Translator.trans('item.delete')"
              class="btn--blank o-link--default"
              @click="deleteTag(rowData.id)">
              <i
                class="fa fa-trash"
                aria-hidden="true" />
            </button>
          </template>
          <template v-else>
            <button
              :aria-label="Translator.trans('save')"
              class="btn--blank o-link--default u-mr-0_25"
              @click="dpValidateAction('tagsTable', () => updateTag(rowData.id, rowData.name), false)">
              <dp-icon
                icon="check"
                aria-hidden="true" />
            </button>
            <button
              class="btn--blank o-link--default"
              :aria-label="Translator.trans('abort')"
              @click="abortEdit()">
              <dp-icon
                icon="xmark"
                aria-hidden="true" />
            </button>
          </template>
        </div>
      </template>
    </dp-data-table>
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
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import tagCategories from './InstitutionTagCategories.json'
import tags from './InstitutionTags.json'

export default {
  name: 'TagList',

  components: {
    DpButton,
    DpButtonRow,
    DpDataTable,
    DpIcon,
    DpInput,
    DpLoading
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      addNewTag: false,
      edit: false,
      editingTagId: null,
      headerFields: [
        {
          field: 'label',
          label: Translator.trans('tags'),
          colClass: 'u-11-of-12'
        },
        {
          field: 'action',
          label: Translator.trans('actions'),
          colClass: 'u-1-of-10'
        }
      ],
      initialRowData: {},
      isLoading: false,
      newTag: {}
    }
  },

  computed: {
    // ...mapState('InstitutionTagCategories', {
    //   institutionTagCategories: 'items'
    // }),

    ...mapState('InstitutionTag', {
      institutionTags: 'items'
    }),

    tagCategories () {
      return Object.values(tagCategories).map(category => {
        const { attributes, id, type } = category

        return {
          id,
          name: attributes.name,
          tags: Object.values(tags).map(tag => {
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
      // return Object.values(this.institutionTagCategories).map(category => {
      //   const { attributes, id, type } = category
      //   const tags = category.relationships.tags.data.length > 0 ? category.relationships.tags.data.list() : []
      //
      //   return {
      //     id,
      //     name: attributes.name,
      //     tags,
      //     type
      //   }
      // })
    },

    tags () {
      return Object.values(this.institutionTags).map(tag => {
        const { id, attributes } = tag
        return {
          id,
          edit: this.editingTagId === id,
          label: attributes.label
        }
      })
    }
  },

  methods: {
    // ...mapActions('InstitutionTagCategory', {
    //   listInstitutionTagCategories: 'list'
    // }),

    ...mapActions('InstitutionTag', {
      createInstitutionTag: 'create',
      deleteInstitutionTag: 'delete',
      listInstitutionTags: 'list',
      restoreTagFromInitial: 'restoreFromInitial',
      saveInstitutionTag: 'save'
    }),

    ...mapMutations('InstitutionTag', {
      updateInstitutionTag: 'setItem'
    }),

    abortEdit () {
      this.editingTagId = null
    },

    closeNewTagForm () {
      this.addNewTag = false
      this.newTag.label = null
    },

    deleteTag (id) {
      this.deleteInstitutionTag(id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.deleted'))
          this.$emit('tagIsRemoved')
        })
        .catch(err => {
          console.error(err)
        })
    },

    editTag (id) {
      this.addNewTag = false
      this.editingTagId = id
      this.newTag.label = null
    },

    getInstitutionTagCategories () {
      // this.listInstitutionTagCategories({
      //   fields: {
      //     InstitutionTagCategory: [
      //       'name',
      //       'tags'
      //     ].join()
      //   },
      //   include: ['tags'].join()
      // })
    },

    getInstitutionTags () {
      this.listInstitutionTags({
        fields: {
          InstitutionTag: ['label', 'id'].join()
        }
      })
    },

    handleAddNewTagForm () {
      this.addNewTag = true
      this.editingTagId = null
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

    resetNewTagForm () {
      this.newTag = {}
      this.addNewTag = false
    },

    saveNewTag () {
      if (!this.isUniqueTagName(this.newTag.label, true)) {
        return dplan.notify.error(Translator.trans('workflow.tag.error.duplication'))
      }
      this.isLoading = true

      // Persist changes in database
      const payload = {
        type: 'InstitutionTag',
        attributes: {
          label: this.newTag.label
        }
      }
      this.createInstitutionTag(payload)
        .then(() => {
          this.getInstitutionTags()
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

    updateTag (id, label) {
      if (!this.isUniqueTagName(label)) {
        return dplan.notify.error(Translator.trans('workflow.tag.error.duplication'))
      }

      this.updateInstitutionTag({
        id,
        type: this.institutionTags[id].type,
        attributes: {
          ...this.institutionTags[id].attributes,
          label
        }
      })

      this.saveInstitutionTag(id)
        .then(dplan.notify.confirm(Translator.trans('confirm.saved')))
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
    // this.getInstitutionTagCategories()
    this.getInstitutionTags()
  }
}
</script>
