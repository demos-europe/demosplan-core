<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    ref="contentArea"
    class="u-mt-0_5">
    <dp-data-table-extended
      ref="dataTable"
      class="u-mt-0_5"
      :header-fields="headerFields"
      :table-items="rowItems"
      is-selectable
      :items-per-page-options="itemsPerPageOptions"
      :default-sort-order="sortOrder"
      @items-selected="setSelectedItems"
      :init-items-per-page="itemsPerPage">
      <template v-slot:footer>
        <div class="u-pt-0_5">
          <div class="u-1-of-3 inline-block">
            <span
              class="weight--bold line-height--1_6"
              v-if="selectedItems.length">
              {{ selectedItems.length }} {{ (selectedItems.length === 1 && Translator.trans('entry.selected')) || Translator.trans('entries.selected') }}
            </span>
          </div><!--
       --><div class="u-2-of-3 text-right inline-block space-inline-s">
            <dp-button
              data-cy="addPublicAgency"
              :text="Translator.trans('invitable_institution.add')"
              @click="addPublicInterestBodies(selectedItems)" />
            <a
              :href="Routing.generate('DemosPlan_procedure_member_index', { procedure: procedureId })"
              class="btn btn--secondary">
              {{ Translator.trans('abort.and.back') }}
            </a>
        </div>
        </div>
      </template>
    </dp-data-table-extended>
  </div>
</template>

<script>
import { dpApi, DpButton, DpDataTableExtended } from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'

export default {
  name: 'DpAddOrganisationList',

  components: {
    DpDataTableExtended,
    DpButton
  },

  props: {
    procedureId: {
      type: String,
      required: true
    },

    headerFields: {
      type: Array,
      required: false,
      default: () => [
        { field: 'legalName', label: Translator.trans('invitable_institution') },
        { field: 'competenceDescription', label: Translator.trans('competence.explanation') }
      ]
    }
  },

  data () {
    return {
      isLoading: true,
      itemsPerPageOptions: [10, 50, 100, 200],
      itemsPerPage: 50,
      sortOrder: { key: 'legalName', direction: 1 },
      selectedItems: []
    }
  },

  computed: {
    ...mapState('invitableToeb', ['items']),

    rowItems () {
      return Object.values(this.items).reduce((acc, item) => {
        return [
          ...acc,
          ...[
            {
              id: item.id,
              ...item.attributes
            }
          ]
        ]
      }, []) || []
    }
  },

  methods: {
    ...mapActions('invitableToeb', {
      getInstitutions: 'list'
    }),

    setSelectedItems (selectedItems) {
      this.selectedItems = selectedItems
    },

    addPublicInterestBodies (publicAgenciesIds) {
      if (publicAgenciesIds.length === 0) {
        return dplan.notify.notify('warning', Translator.trans('organisation.select.first'))
      }

      dpApi({
        method: 'POST',
        url: Routing.generate('dplan_api_procedure_add_invited_public_affairs_bodies', {
          procedureId: this.procedureId
        }),
        data: {
          data: publicAgenciesIds.map(id => {
            return {
              type: 'publicAffairsAgent',
              id: id
            }
          })
        }
      })
        // Refetch invitable institutions list to ensure that invited institutions are not displayed anymore
        .then(() => {
          this.getInstitutions()
            .then(() => {
              dplan.notify.notify('confirm', Translator.trans('confirm.invitable_institutions.added'))
              this.$refs.dataTable.updateFields()

              // Reset selected items so that the footer updates accordingly
              this.selectedItems = []
              // Also reset selection in DpDataTableExtended as this.selectedItems resets only local variable
              this.$refs.dataTable.resetSelection()
            })
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('warning.invitable_institution.not.added'))
        })
    }
  },

  mounted () {
    this.getInstitutions({ procedureId: this.procedureId })
      .then(() => { this.isLoading = false })
  }
}
</script>
