<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
(c) 2010-present DEMOS E-Partizipation GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <div
      class="text--right">
      <dp-button
        v-if="!addNewTag"
        @click="addNewTag = true"
        :text="Translator.trans('institution.tag.addTag')" />
    </div>
    <div
      v-if="addNewTag"
      class="position--relative"
      data-dp-validate="addNewTagForm">
      <div class="border border-radius-small space-stack-m space-inset-m">
        <div class="position--relative u-pb-0_5 font-size-large">
          {{ Translator.trans('institution.tag.create') }}
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
            text: Translator.trans('institution.tag.name')
          }"/>
        <dp-button-row
          :busy="isLoading"
          primary
          secondary
          @primary-action="dpValidateAction('addNewTagForm', () => saveNewTag(newTag), false)"
          @secondary-action="addNewTag = false" />
      </div>
    </div>
    <dp-data-table
      data-dp-validate="tagsTable"
      v-if=""
      has-flyout
      :header-fields="headerFields"
      is-draggable
      track-by=""
      :items="tags"
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
      <template v-slot:flyout="rowData">
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
              @click="rowData.edit=false">
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
import { dpApi } from '@DemosPlanCoreBundle/plugins/DpApi'
import DpButtonRow from '@DpJs/components/core/DpButtonRow'
import DpDataTable from '@DpJs/components/core/DpDataTable/DpDataTable'
import {DpButton, DpIcon, DpInput, DpLoading} from 'demosplan-ui/components'
import dpValidateMixin from '@DpJs/lib/validation/dpValidateMixin';

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
      headerFields: [
        { field: 'label', label: 'Schlagworte', colClass: 'u-5-of-12' }
      ],
      initialRowData: {},
      isLoading: false,
      newTag: {},
      tags: []
    }
  },
  methods: {
    deleteTag (rowData) {
      dpApi.delete(Routing.generate('api_resource_delete', { resourceType: 'InstitutionTag', resourceId: rowData.id }))
        .catch((err) => console.error(err))
        .finally(() => {
          rowData.edit = false
        })
    },
    editTag (rowData) {
      // Reset row which was in editing state before
      const editingTag = this.tags.find(tag => tag.edit === true)
      if (editingTag) {
        editingTag.name = this.initialRowData.label
        editingTag.edit = false
      }

      // Save initial state of currently edited row
      this.initialRowData.label = rowData.label
      rowData.edit = true
    },
    fetchInstitutionTags () {
      dpApi.get(Routing.generate('api_resource_list', {
        resourceType: 'InstitutionTag',
        fields: {
          InstitutionTag: ['label', 'id'].join()
        }
      }))
        .then(response => {
          const tags = response.data.data
          tags.forEach((tag) => {
            this.tags.push({
              edit: false,
              id: tag.id,
              label: tag.attributes.label
            })
          })
        })
    },
    saveNewTag () {
      this.isLoading = true
      /**
       * Persist changes in database
       */
      const payload = {
        type: 'InstitutionTag',
        attributes: {
          label: this.newTag.label,
        }
      }
      dpApi.post(Routing.generate('api_resource_create', { resourceType: 'InstitutionTag' }), {}, { data: payload })
        .then(() => {
          /**
           * Update local data so no additional api request is needed to fetch the updated data
           */
          const localDataToUpdate = {
            label: this.newTag.label
          }
          this.tags.push(localDataToUpdate)
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => console.error(err))
        .finally(() => {
          this.isLoading = false
        })
    },

    updateTag (rowData) {
      console.log(rowData.id)
      const payload = {
        data: {
          type: 'InstitutionTag',
          id: rowData.id,
          attributes: {
            label: rowData.label,
          }
        }
      }

      dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'InstitutionTag', resourceId: rowData.id }), {}, payload)
        .then(dplan.notify.confirm(Translator.trans('confirm.saved')))
        .catch((err) => console.error(err))
        .finally(() => {
          rowData.edit = false
        })
    },
  },

  mounted() {
    this.fetchInstitutionTags()
  }
}
</script>
