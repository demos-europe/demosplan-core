<template>
  <dp-data-table
    data-dp-validate="tagsTable"
    has-flyout
    :header-fields="headerFields"
    track-by="id"
    :items="institutionsMock"
    class="u-mt-2">
    <template v-slot:action="rowData">
      <div class="float--right">
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
            @click="dpValidateAction('tagsTable', () => updateTag(rowData.id, rowData.label), false)">
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
import { mapState, mapActions } from "vuex";
import DpDataTable from '@DpJs/components/core/DpDataTable/DpDataTable'


export default {
  name: "InstitutionList",

  components: {
    DpDataTable
  },

  data () {
    return {
      addNewTag: false,
      edit: false,
      editingTagId: null,
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
      institutionMockData: [
        {
          id: 'abc1',
          attributes: {
            title: 'Institution-1'
          },
          tags: 'tag-1'
        },
        {
          id: 'abc2',
          label: 'Institution-2',
          attributes: {
            title: 'Institution-2'
          },
          tags: 'tag-2'
        },
        {
          id: 'abc3',
          label: 'Institution-3',
          attributes: {
            title: 'Institution-3'
          },
          tags: 'tag-3'
        }
      ]
    }
  },

  computed: {
    ...mapState('institutionTag', {
      institutionTags: 'items'
    }),

    ...mapState('invitableInstitution', {
      invitableInstitution: 'items'
    }),

    tags () {
      return Object.values(this.institutionTags).map(tag => {
        const { id, attributes } = tag
        return {
          id,
          edit: this.editingTagId === id,
          label: attributes.label
        }
      })
    },

    institutionsMock () {
      return Object.values(this.institutionMockData).map(tag => {
        const { id, attributes, tags } = tag
        return {
          id,
          edit: this.editingTagId === id,
          institution: attributes.title,
          tags: tags
        }
      })
    }
  },

  methods: {
    ...mapActions('invitableInstitution', {
      listInvitableInstitution: 'list'
    }),

    editTag (id) {
      this.addNewTag = false
      this.editingTagId = id
      this.newTag.label = null
    },

    deleteTag (id) {
      this.deleteInstitutionTag(id)
        .then(dplan.notify.confirm(Translator.trans('confirm.deleted')))
        .catch(err => {
          console.error(err)
        })
    },

    updateTag (id, label) {
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
          this.restoreTagFromInitial(id)
          console.error(err)
        })
        .finally(() => {
          this.editingTagId = null
        })
    },

    abortEdit () {
      this.editingTagId = null
    }
  },

  mounted() {
    this.listInvitableInstitution()
  }
}
</script>

<style scoped>
</style>
