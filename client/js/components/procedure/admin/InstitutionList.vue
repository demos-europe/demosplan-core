<template>
  <dp-data-table
    data-dp-validate="tagsTable"
    has-flyout
    :header-fields="headerFields"
    track-by="id"
    :items="institutions"
    class="u-mt-2">
    <template v-slot:tags="rowData">
      <div v-if="!rowData.edit">
          <span v-for="(tag, i) in rowData.tags"
                :key="i"
                v-cleanhtml="tag" />
        </div>
      <dp-multiselect
        v-else
        v-model="selectedTags"
        :options="tags"
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
          <button
            :aria-label="Translator.trans('item.delete')"
            class="btn--blank o-link--default">
            <i
              class="fa fa-trash"
              aria-hidden="true" />
          </button>
        </template>
        <template v-else>
          <button
            :aria-label="Translator.trans('save')"
            class="btn--blank o-link--default u-mr-0_25"
            @click="addTags(rowData.id)">
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
import {mapState, mapActions, mapMutations} from "vuex";
import DpDataTable from '@DpJs/components/core/DpDataTable/DpDataTable'
import { DpButton, DpIcon, DpInput, DpLoading } from 'demosplan-ui/components'
import { CleanHtml } from 'demosplan-ui/directives'
import DpMultiselect from '@DpJs/components/core/form/DpMultiselect'


export default {
  name: "InstitutionList",

  directives: {
    cleanhtml: CleanHtml
  },

  components: {
    DpDataTable,
    DpButton,
    DpInput,
    DpIcon,
    DpLoading,
    DpMultiselect
  },

  data () {
    return {
      editingInstitutionId: null,
      editingInstitutionTags: [],
      editingInstitution: {},
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
      ],
    }
  },

  computed: {
    ...mapState('institutionTag', {
      institutionTagList: 'items'
    }),

    ...mapState('invitableInstitution', {
      invitableInstitutionList: 'items'
    }),

    tags () {
      return Object.values(this.institutionTagList).map(tag => {
        const { id, attributes } = tag
        return {
          id,
          edit: this.editingTagId === id,
          label: attributes.label
        }
      })
    },

    selectedTags: {
      get () {
        return this.editingInstitutionTags
      },

      set (newValue) {
        return this.editingInstitutionTags.push(newValue)
      }
    },

    institutions () {
      return Object.values(this.invitableInstitutionList).map(tag => {
        const { id, attributes } = tag
        return {
          id,
          edit: this.editingInstitutionId === id,
          institution: attributes.name,
          tags: attributes.assignedTags
        }
      })
    }
  },

  methods: {
    ...mapActions('invitableInstitution', {
      listInvitableInstitution: 'list',
      saveInvitableInstitution: 'save'
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

    addTags (id) {
      this.updateInvitableInstitution({
        id: id,
        type: this.invitableInstitutionList[id].type,
        attributes: {
          ...this.invitableInstitutionList[id].attributes,
          assignedTags: this.selectedTags
        }
      })

      this.saveInvitableInstitution(id)
        .then(dplan.notify.confirm(Translator.trans('confirm.saved')))
        .catch(err => {

          console.error(err)
        })
    }
  },

  mounted() {
    this.listInvitableInstitution()
  }
}
</script>

<style scoped>
</style>
