<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-inline-notification
      dismissible
      :message="Translator.trans('explanation.invitable_institution.group.tags')"
      type="info" />

    <dp-column-selector
      data-cy="institutionList:selectableColumns"
      :initial-selection="currentSelection"
      local-storage-key="institutionList"
      :selectable-columns="selectableColumns"
      use-local-storage
      @selection-changed="setCurrentSelection" />

    <dp-data-table
      data-dp-validate="tagsTable"
      has-flyout
      :header-fields="headerFieldsAvailable"
      track-by="id"
      :items="institutionList"
      class="u-mt-2">
      <template v-slot:institution="rowData">
        <ul class="o-list max-w-12">
          <li>
            {{ rowData.institution }}
          </li>
          <li class="o-list__item o-hellip--nowrap">
            {{ date(rowData.createdDate) }}
          </li>
        </ul>
      </template>
      <template v-slot:tags="rowData">
        <div v-if="!rowData.edit">
          <span>
            {{ separateByCommas(rowData.tags) }}
          </span>
        </div>
        <dp-multiselect
          v-else
          v-model="editingInstitutionTags"
          :options="tagList"
          label="name"
          track-by="id"
          multiple />
      </template>
      <template v-slot:action="rowData">
        <div class="float-right">
          <template v-if="!rowData.edit">
            <button
              :aria-label="Translator.trans('item.edit')"
              class="btn--blank o-link--default"
              @click="editInstitution(rowData.id)">
              <i
                class="fa fa-pencil"
                aria-hidden="true" />
            </button>
          </template>
          <template v-else>
            <button
              :aria-label="Translator.trans('save')"
              class="btn--blank o-link--default u-mr-0_25"
              @click="addTagsToInstitution(rowData.id)">
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

    <dp-sliding-pagination
      v-if="totalPages > 1"
      class="u-mr-0_25 u-ml-0_5 u-mt-0_5"
      :current="currentPage"
      :total="totalPages"
      :non-sliding-size="50"
      @page-change="getInstitutionsByPage" />
  </div>
</template>

<script>
import {
  DpColumnSelector,
  DpDataTable,
  DpIcon,
  DpInlineNotification,
  DpMultiselect,
  DpSlidingPagination,
  formatDate
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

export default {
  name: 'InstitutionList',

  components: {
    DpColumnSelector,
    DpDataTable,
    DpMultiselect,
    DpIcon,
    DpInlineNotification,
    DpSlidingPagination
  },

  data () {
    return {
      currentSelection: [],
      editingInstitutionId: null,
      editingInstitution: null,
      editingInstitutionTags: [],
      headerFieldsAvailable: [
        {
          field: 'institution',
          label: Translator.trans('institution'),
          colClass: 'u-2-of-12'
        }
      ]
    }
  },

  computed: {
    ...mapState('InstitutionTag', {
      institutionTagList: 'items'
    }),

    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items'
    }),

    ...mapState('InvitableInstitution', {
      invitableInstitutionList: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages'
    }),

    institutionList () {
      return Object.values(this.invitableInstitutionList).map(tag => {
        const { id, attributes, relationships } = tag

        return {
          createdDate: attributes.createdDate.date,
          edit: this.editingInstitutionId === id,
          id,
          institution: attributes.name,
          tags: relationships.assignedTags.data
        }
      })
    },

    selectableColumns () {
      return this.headerFieldsAvailable.map(headerField => ([headerField.field, headerField.label]))
    },

    tagList () {
      return Object.values(this.institutionTagList).map(tag => {
        const { id, attributes } = tag
        return {
          id,
          name: attributes.name
        }
      })
    }
  },

  methods: {
    ...mapActions('InstitutionTagCategory', {
      listInstitutionTagCategories: 'list'
    }),

    ...mapActions('InvitableInstitution', {
      listInvitableInstitution: 'list',
      saveInvitableInstitution: 'save',
      restoreInstitutionFromInitial: 'restoreFromInitial'
    }),

    ...mapMutations('InvitableInstitution', {
      updateInvitableInstitution: 'setItem'
    }),

    abortEdit () {
      this.editingInstitutionId = null
      this.editingInstitutionTags = []
    },

    addTagsToInstitution (id) {
      const institutionTagsString = JSON.stringify(this.editingInstitutionTags)
      const institutionTagsArray = JSON.parse(institutionTagsString)
      const payload = institutionTagsArray.map(el => {
        return {
          id: el.id,
          type: 'InstitutionTag'
        }
      })

      this.updateInvitableInstitution({
        id,
        type: 'InvitableInstitution',
        attributes: { ...this.invitableInstitutionList[id].attributes },
        relationships: {
          assignedTags: {
            data: payload
          }
        }
      })

      this.saveInvitableInstitution(id)
        .then(dplan.notify.confirm(Translator.trans('confirm.saved')))
        .catch(err => {
          this.restoreInstitutionFromInitial(id)
          console.error(err)
        })
        .finally(() => {
          this.editingInstitutionId = null
        })
    },

    date (d) {
      return formatDate(d)
    },

    editInstitution (id) {
      this.editingInstitutionTags = []
      this.editingInstitutionId = id
      this.editingInstitution = this.invitableInstitutionList[id]
      this.editingInstitution.relationships.assignedTags.data.forEach(el => {
        const tag = this.getTagById(el.id)
        this.editingInstitutionTags.push(tag)
      })
    },

    getInstitutionsByPage (page) {
      this.listInvitableInstitution({
        page: {
          number: page,
          size: 50
        },
        sort: '-createdDate',
        fields: {
          InstitutionTag: [
            'id',
            'name'
          ].join(),
          InvitableInstitution: [
            'name',
            'createdDate',
            'assignedTags'
          ].join()
        },
        include: [
          'assignedTags'
        ].join()
      })
    },

    getInstitutionTagCategories () {
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
          // @todo add categories to availableHeaderFields
        })
        .catch(err => {
          console.error(err)
        })
    },

    getTagById (tagId) {
      return this.tagList.find(el => el.id === tagId) ?? null
    },

    getTagNameById (tagId) {
      return this.tagList
        .filter(el => el.id === tagId)
        .map(el => el.name)
    },

    separateByCommas (institutionTags) {
      const tagsLabels = []

      institutionTags.forEach(el => {
        const name = this.getTagNameById(el.id)

        tagsLabels.push(name)
      })

      return tagsLabels.join(', ')
    },

    setCurrentSelection (selection) {
      this.currentSelection = selection
    }
  },

  mounted () {
    this.getInstitutionsByPage(1)
    this.getInstitutionTagCategories()
  }
}
</script>
