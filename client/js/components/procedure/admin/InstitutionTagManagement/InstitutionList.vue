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

    <dp-loading
      class="u-mt"
      v-if="isLoading" />
    <template v-else>
      <dp-column-selector
        data-cy="institutionList:selectableColumns"
        :initial-selection="currentSelection"
        local-storage-key="institutionList"
        :selectable-columns="selectableColumns"
        use-local-storage
        @selection-changed="setCurrentSelection" />

      <dp-data-table
        ref="dataTable"
        class="u-mt-2 overflow-x-auto scrollbar-none"
        data-dp-validate="tagsTable"
        data-cy="institutionList:dataTable"
        :header-fields="headerFields"
        is-resizable
        :items="institutionList"
        track-by="id">
        <template v-slot:name="institution">
          <ul class="o-list max-w-12">
            <li>
              {{ institution.name }}
            </li>
            <li class="o-list__item o-hellip--nowrap">
              {{ date(institution.createdDate) }}
            </li>
          </ul>
        </template>
        <template
          v-for="category in institutionTagCategories"
          v-slot:[category.attributes.name]="institution">
          <div v-if="!institution.edit">
            <span>
              {{ separateByCommas(institution.tags.filter(tag => tag.category.id === category.id)) }}
            </span>
          </div>
          <dp-multiselect
            v-else
            v-model="editingInstitutionTags[category.id]"
            :data-cy="`institutionList:tags${category.attributes.name}`"
            label="name"
            multiple
            :options="getCategoryTags(category.id)"
            track-by="id" />
        </template>
        <template v-slot:action="institution">
          <div class="float-right">
            <template v-if="!institution.edit">
              <button
                :aria-label="Translator.trans('item.edit')"
                class="btn--blank o-link--default"
                data-cy="institutionList:editTag"
                @click="editInstitution(institution.id)">
                <i
                  class="fa fa-pencil"
                  aria-hidden="true" />
              </button>
            </template>
            <template v-else>
              <button
                :aria-label="Translator.trans('save')"
                class="btn--blank o-link--default u-mr-0_25"
                data-cy="institutionList:saveTag"
                @click="addTagsToInstitution(institution.id)">
                <dp-icon
                  icon="check"
                  aria-hidden />
              </button>
              <button
                :aria-label="Translator.trans('abort')"
                class="btn--blank o-link--default"
                data-cy="institutionList:abortTag"
                @click="abortEdit()">
                <dp-icon
                  icon="xmark"
                  aria-hidden />
              </button>
            </template>
          </div>
        </template>
      </dp-data-table>

      <div
        ref="scrollBar"
        class="sticky bottom-0 left-0 right-0 h-3 overflow-x-scroll overflow-y-hidden">
        <div />
      </div>
    </template>

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
  DpLoading,
  DpMultiselect,
  DpSlidingPagination,
  DpStickyElement,
  formatDate
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import tableScrollbarMixin from '@DpJs/components/shared/mixins/tableScrollbarMixin'

export default {
  name: 'InstitutionList',

  components: {
    DpColumnSelector,
    DpDataTable,
    DpMultiselect,
    DpIcon,
    DpInlineNotification,
    DpLoading,
    DpSlidingPagination,
    DpStickyElement
  },

  mixins: [tableScrollbarMixin],

  data () {
    return {
      currentSelection: [],
      editingInstitutionId: null,
      editingInstitution: null,
      editingInstitutionTags: {},
      isLoading: true,
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

    categoryFieldsAvailable() {
      return Object.values(this.institutionTagCategories).map(category => ({
        field: category.attributes.name,
        label: category.attributes.name
      }))
    },

    headerFields () {
      const institutionField = {
        field: 'name',
        label: Translator.trans('institution')
      }

      const categoryFields = this.categoryFieldsAvailable.filter(headerField => this.currentSelection.includes(headerField.field))

      const actionField = {
        field: 'action'
      }

      return [institutionField, ...categoryFields, actionField]
    },

    institutionList () {
      return Object.values(this.invitableInstitutionList).map(tag => {
        const { id, attributes, relationships } = tag

        return {
          createdDate: attributes.createdDate.date,
          edit: this.editingInstitutionId === id,
          id,
          name: attributes.name,
          tags: relationships.assignedTags.data.map(tag => {
            const tagDetails = this.getTagById(tag.id)

            return {
              id: tag.id,
              type: tag.type,
              name: tagDetails.name,
              category: tagDetails.category
            }
          })
        }
      })
    },

    selectableColumns () {
      return this.categoryFieldsAvailable.map(headerField => ([headerField.field, headerField.label]))
    },

    tagList () {
      return Object.values(this.institutionTagList).map(tag => {
        const { id, attributes, relationships } = tag

        return {
          id,
          name: attributes.name,
          category: relationships?.category?.data
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
      this.editingInstitutionTags = {}
    },

    addTagsToInstitution (id) {
      const institutionTagsArray = Object.values(this.editingInstitutionTags).flatMap(category => Object.values(category))
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
      this.editingInstitutionTags = {}
      this.editingInstitutionId = id
      this.editingInstitution = this.invitableInstitutionList[id]

      // Initialize editingInstitutionTags with categoryId
      Object.values(this.institutionTagCategories).forEach(category => {
        if (!this.editingInstitutionTags[category.id]) {
          this.$set(this.editingInstitutionTags, category.id, [])
        }
      })
      this.editingInstitution.relationships.assignedTags.data.forEach(el => {
        const tag = this.getTagById(el.id)
        this.editingInstitutionTags[tag.category.id].push(tag)
      })
    },

    getCategoryTags (categoryId) {
      const tags = this.institutionTagCategories[categoryId].relationships?.tags?.data.length > 0 ? this.institutionTagCategories[categoryId].relationships.tags.list() : []

      return Object.values(tags).map(tag => {
        return {
          id: tag.id,
          name: tag.attributes.name
        }
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
          InvitableInstitution: [
            'name',
            'createdDate',
            'assignedTags'
          ].join(),
          InstitutionTag: [
            'category',
            'name'
          ].join(),
          InstitutionTagCategory: [
            'name'
          ].join()
        },
        include: [
          'assignedTags',
          'assignedTags.category',
          'category'
        ].join()
      })
    },

    getInstitutionTagCategories () {
      this.isLoading = true
      this.listInstitutionTagCategories({
        fields: {
          InstitutionTagCategory: [
            'name',
            'tags'
          ].join(),
          InstitutionTag: [
            'isUsed',
            'name',
            'category'
          ].join()
        },
        include: [
          'tags',
          'tags.category'
        ].join()
      })
      .then(() => {
        this.setInitialSelection()
      })
      .finally(() => {
        this.isLoading = false
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
    },

    setInitialSelection () {
      this.currentSelection = Object.values(this.institutionTagCategories).slice(0, 7).map(category => category.attributes.name)
    }
  },

  mounted () {
    this.getInstitutionsByPage(1)
    this.getInstitutionTagCategories()
  }
}
</script>
