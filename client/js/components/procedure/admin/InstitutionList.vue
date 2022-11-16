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
        <ul class="o-list max-width-350">
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
          <span> {{ separateByCommas(rowData.tags) }}</span>
          </div>
        <dp-multiselect
          v-else
          v-model="editingInstitutionTags"
          :options="tagList"
          label="label"
          track-by="id"
          multiple>
        </dp-multiselect>
      </template>
      <template v-slot:action="rowData">
        <div class="float--right">
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
import { DpIcon } from 'demosplan-ui/components'
import { formatDate } from 'demosplan-utils'
import { mapState, mapActions, mapMutations } from "vuex"
import DpDataTable from '@DpJs/components/core/DpDataTable/DpDataTable'
import DpMultiselect from '@DpJs/components/core/form/DpMultiselect'
import DpInlineNotification from '@DpJs/components/core/DpInlineNotification'
import DpSlidingPagination from '@DpJs/components/core/DpSlidingPagination'


export default {
  name: "InstitutionList",

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
          colClass: 'u-2-of-12',
        },
        {
          field: 'tags',
          label: Translator.trans('tags'),
          colClass: 'u-9-of-12',
        },
        {
          field: 'action',
          colClass: 'u-1-of-12'
        }
      ]
    }
  },

  computed: {
    ...mapState('institutionTag', {
      institutionTagList: 'items'
    }),

    ...mapState('invitableInstitution', {
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
    ...mapActions('invitableInstitution', {
      listInvitableInstitution: 'list',
      saveInvitableInstitution: 'save',
      restoreInstitutionFromInitial: 'restoreFromInitial'
    }),

    ...mapMutations('invitableInstitution', {
      updateInvitableInstitution: 'setItem'
    }),

    getInstitutionsByPage (page) {
      this.listInvitableInstitution({
        page: {
          number: page,
          size: 50
        },
        sort: '-createdDate'
      })
    },

    editInstitution (id) {
      this.editingInstitutionId = id
      this.editingInstitution = this.invitableInstitutionList[id]
      this.editingInstitutionTags = this.editingInstitution.relationships.assignedTags.data
    },

    abortEdit () {
      this.editingInstitutionId = null
    },

    addTagsToInstitution (id) {
      const institutionTagsString = JSON.stringify(this.editingInstitutionTags)
      const institutionTagsArray = JSON.parse(institutionTagsString)

      const payload = institutionTagsArray.map(el => {
        return {
          id: el.id,
          type: 'InstitutionTag',
          label: el.label
        }
      })

      this.updateInvitableInstitution ({
        id: id,
        type: 'InvitableInstitution',
        attributes: { ...this.invitableInstitutionList[id].attributes },
        relationships: {
          assignedTags: {
            data: payload
          }
        }
      })

      this.saveInvitableInstitution (id)
        .then(dplan.notify.confirm(Translator.trans('confirm.saved')))
        .catch(err => {
          // Restore statement in store in case request failed
          this.restoreInstitutionFromInitial (id)
          console.error(err)
        })
        .finally(() => {
        this.editingInstitutionId = null
      })
    },

    sortByCreatedDate (array) {
      return array.sort((a, b) => {
        return new Date(b.createdDate) - new Date(a.createdDate)
      })
    },

    date (d) {
      return formatDate (d)
    },

    separateByCommas (tagsArr) {
      const tagsLabels = this.getTagsLabels(tagsArr)

      return tagsLabels.join(", ")
  },

    getTagsLabels (tagsArr) {
      let tagsLabels = []

      tagsArr.map(el => {
        tagsLabels.push(el.label)
      })

      return tagsLabels
    }
  },

  mounted () {
    this.getInstitutionsByPage(1)
  }
}
</script>

<style scoped>
</style>
