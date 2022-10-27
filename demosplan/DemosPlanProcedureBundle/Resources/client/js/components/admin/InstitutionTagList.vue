<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div class="text--right">
      <dp-button
        v-if="!addNewTag"
        :text="Translator.trans('tag.new')"
        @click="openAddNewTagField()" />
    </div>
    <div
      v-if="addNewTag"
      class="position--relative"
      data-dp-validate="addNewTagForm">
      <dp-loading
        v-if="isLoading"
        overlay />
      <div class="border border-radius-small space-stack-m space-inset-m">
        <div class="position--relative u-pb-0_5 font-size-large">
          {{ Translator.trans('tags.create') }}
          <button
            class="btn--blank o-link--default float--right"
            @click="addNewTag = false">
            <dp-icon icon="close" />
          </button>
        </div>
        <dp-input
          id="createTag"
          v-model="newTag.label"
          :label="{
            text: Translator.trans('name')
          }"/>
        <dp-button-row
          :busy="isLoading"
          :align="alignLeft"
          primary
          secondary
          @primary-action="dpValidateAction('addNewTagForm', () => saveNewTag(newTag), false)"
          @secondary-action="addNewTag = false" />
      </div>
    </div>
    <dp-data-table
      data-dp-validate="tagsTable"
      has-flyout
      :header-fields="headerFields"
      track-by="id"
      :items="mapTags"
      class="u-mt-2"
    >
      <template v-slot:label="rowData">
        <div
          v-if="!rowData.edit"
          v-text="rowData.label" />
        <dp-input
          v-else
          id="editInstitutionTag"
          maxlength="250"
          required
          v-model="rowData.label" />
      </template>
      <template v-slot:action="rowData"
      class="float--right">
        <div class="float--right">
          <template v-if="!rowData.edit">
            <button
              :aria-label="Translator.trans('item.edit')"
              class="btn--blank o-link--default"
              @click="editTag(rowData)">
              <i
                class="fa fa-pencil"
                aria-hidden="true"/>
            </button>
            <button
              :aria-label="Translator.trans('item.delete')"
              class="btn--blank o-link--default"
              @click="deleteTag(rowData)">
              <i
                class="fa fa-trash"
                aria-hidden="true"/>
            </button>
          </template>
          <template v-if="rowData.edit">
            <button
              :aria-label="Translator.trans('save')"
              class="btn--blank o-link--default u-mr-0_25"
              @click="dpValidateAction('tagsTable', () => updateTag(rowData), false)">
              <dp-icon
                icon="check"
                aria-hidden="true" />
            </button>
            <button
              class="btn--blank o-link--default"
              :aria-label="Translator.trans('abort')"
              @click="isEditing = ''">
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
import { DpButton, DpIcon, DpInput, DpLoading } from 'demosplan-ui/components'
import { mapActions, mapMutations, mapState } from 'vuex'
import DpButtonRow from '@DpJs/components/core/DpButtonRow'
import DpDataTable from '@DpJs/components/core/DpDataTable/DpDataTable'
import dpValidateMixin from '@DpJs/lib/validation/dpValidateMixin'

export default {
  name: 'InstitutionTagList',

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
      alignLeft: 'left',
      edit: false,
      headerFields: [
        {
          field: 'label',
          label: Translator.trans('tags'),
          colClass: 'u-11-of-12'
        },
        {
          field: 'action',
          label: Translator.trans('action'),
          colClass: 'u-1-of-10'
        }
      ],
      initialRowData: {},
      isEditing: '',
      isLoading: false,
      newTag: {},
      tagsArray: [],
    }
  },

  computed: {
    ...mapState('institutionTag', {
      institutionTags: 'items'
    }),

    mapTags () {
      this.tagsArray= this.institutionTagsArray()
      return this.tagsArray
    }
  },

  methods: {
    ...mapActions('institutionTag', {
      listInstitutionTags: 'list',
      createInstitutionTag: 'create',
      deleteInstitutionTag: 'delete',
      saveInstitutionTag: 'save'
    }),

    ...mapMutations('institutionTag', {
      updateInstitutionTag: 'setItem'
    }),

    deleteTag ( { id }) {
      this.deleteInstitutionTag(id)
        .then(dplan.notify.confirm(Translator.trans('confirm.deleted')))
        .catch(err => {
          console.error(err)
        })
    },

    editTag ( { id }) {
      this.isEditing = id
    },

    getInstitutionTags () {
      this.listInstitutionTags({
        fields: {
          InstitutionTag: ['label', 'id'].join()
        }
      })
    },

    institutionTagsArray () {
      let array = []
      Object.keys(this.institutionTags).forEach(tag => {
        array.push({
          edit: this.isEditing === tag,
          id: this.institutionTags[tag].id,
          label: this.institutionTags[tag].attributes.label
        })
      })
      return array
    },

     // When saving a new tag the comparison of `foundSimilarLabel.length === 0` needs to be executed
     // When updating a new tag the comparison of `foundSimilarLabel.length === 1` needs to be executed instead
     // should always be possible.
     //
     // @param tagLabel { string }
     // @param isNewTagLabel { boolean }
     // @returns { boolean }

    isUniqueTagName (tagLabel, isNewTagLabel = false) {
      const foundSimilarLabel = this.tagsArray.filter(el => el.label === tagLabel)
      return isNewTagLabel ? foundSimilarLabel.length === 0 : foundSimilarLabel.length === 1
    },

    openAddNewTagField () {
      this.addNewTag = true
      this.isEditing = ''

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

    updateTag ({ id, label }) {
      if (!this.isUniqueTagName(label)) {
        return dplan.notify.error(Translator.trans('workflow.tag.error.duplication'))
      }
      this.updateInstitutionTag({
        id: id,
        type: this.institutionTags[id].type,
        attributes: {
          ...this.institutionTags[id].attributes,
          label: label
        }
      })
      this.saveInstitutionTag(id)
        .then(dplan.notify.confirm(Translator.trans('confirm.saved')))
        .catch(err => {
          console.error(err)
        })
        .finally(() => {
          this.isEditing = false
        })
    }
  },

  mounted () {
    this.getInstitutionTags()
  }
}
</script>
