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
    <dp-data-table
      data-dp-validate="tagsTable"
      has-flyout
      :header-fields="headerFields"
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
          label="label"
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
      class="u-mr-0_25 u-ml-0_5 u-mt-0_5"
      v-if="totalPages > 1"
      :current="currentPage"
      :total="totalPages"
      :non-sliding-size="50"
      @page-change="getInstitutionsByPage" />
  </div>
</template>

<script>
import {
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
    DpDataTable,
    DpMultiselect,
    DpIcon,
    DpInlineNotification,
    DpSlidingPagination
  },

  data () {
    return {
      editingInstitutionId: null,
      editingInstitution: null,
      editingInstitutionTags: [],
      headerFields: [
        {
          field: 'institution',
          label: Translator.trans('institution'),
          colClass: 'u-2-of-12'
        },
        {
          field: 'tags',
          label: Translator.trans('tags'),
          colClass: 'u-9-of-12'
        },
        {
          field: 'action',
          colClass: 'u-1-of-12'
        }
      ]
    }
  },

  computed: {
    ...mapState('InstitutionTag', {
      institutionTagList: 'items'
    }),

    ...mapState('InvitableInstitution', {
      invitableInstitutionList: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages'
    }),

    tagList () {
      return Object.values(this.institutionTagList).map(tag => {
        const { id, attributes } = tag
        return {
          id,
          label: attributes.label
        }
      })
    },

    institutionList () {
      return Object.values(this.invitableInstitutionList).map(tag => {
        const { id, attributes, relationships } = tag
        return {
          id,
          edit: this.editingInstitutionId === id,
          institution: attributes.name,
          tags: relationships.assignedTags.data,
          createdDate: attributes.createdDate.date
        }
      })
    }
  },

  methods: {
    ...mapActions('InvitableInstitution', {
      listInvitableInstitution: 'list',
      saveInvitableInstitution: 'save',
      restoreInstitutionFromInitial: 'restoreFromInitial'
    }),

    ...mapMutations('InvitableInstitution', {
      updateInvitableInstitution: 'setItem'
    }),

    getInstitutionsByPage (page) {
      this.listInvitableInstitution({
        page: {
          number: page,
          size: 50
        },
        sort: '-createdDate',
        fields: {
          InstitutionTag: [
            'label',
            'id'
          ].join()
        }
      })
    },

    editInstitution (id) {
      this.editingInstitutionTags = []
      this.editingInstitutionId = id
      this.editingInstitution = this.invitableInstitutionList[id]
      this.editingInstitution.relationships.assignedTags.data.map(el => {
        const tag = this.getTagById(el.id)
        this.editingInstitutionTags.push(tag)
      })
    },

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
        id: id,
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
          // Restore statement in store in case request failed
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

    separateByCommas (institutionTags) {
      const tagsLabels = []

      institutionTags.map(el => {
        const label = this.getTagLabelById(el.id)
        tagsLabels.push(label)
      })

      return tagsLabels.join(', ')
    },

    getTagById (tagId) {
      let tag = {}
      this.tagList
        .filter(el => el.id === tagId)
        .map(el => {
          tag = {
            id: el.id,
            label: el.label
          }
        })

      return tag
    },

    getTagLabelById (tagId) {
      return this.tagList
        .filter(el => el.id === tagId)
        .map(el => {
          return el.label
        })
    }
  },

  mounted () {
    this.getInstitutionsByPage(1)
  }
}
</script>
