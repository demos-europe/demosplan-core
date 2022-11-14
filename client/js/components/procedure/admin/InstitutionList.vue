<template>
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
        :searchable="false"
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
</template>

<script>
import { DpIcon } from 'demosplan-ui/components'
import { formatDate } from 'demosplan-utils'
import { mapState, mapActions, mapMutations } from "vuex"
import DpDataTable from '@DpJs/components/core/DpDataTable/DpDataTable'
import DpMultiselect from '@DpJs/components/core/form/DpMultiselect'


export default {
  name: "InstitutionList",

  components: {
    DpDataTable,
    DpMultiselect,
    DpIcon
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
      invitableInstitutionList: 'items'
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
      const institutionList = Object.values(this.invitableInstitutionList).map(tag => {
        const { id, attributes } = tag
        return {
          id,
          edit: this.editingInstitutionId === id,
          institution: attributes.name,
          tags: attributes.assignedTags,
          createdDate: attributes.createdDate.date
        }
      })

      return this.sortByCreatedDate (institutionList)
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

    editInstitution (id) {
      this.editingInstitutionId = id
      this.editingInstitution = this.invitableInstitutionList[id]
      this.editingInstitutionTags = this.editingInstitution.attributes.assignedTags
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
          type: 'InstitutionTag'
        }
      })

      this.updateInvitableInstitution ({
        id: id,
        type: "InvitableInstitution",
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

    separateByCommas (arr) {
      const newString = arr.join(", ")

      return newString
    }
  },

  mounted () {
    this.listInvitableInstitution({
      include: [
        'id',
        'type'
      ].join(),
      fields: {
          assignedTags: [
            'id',
            'type'
        ].join()
      },
      sort: 'createdDate'
    })
  }
}
</script>

<style scoped>
</style>
