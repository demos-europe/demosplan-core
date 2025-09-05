<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <organisation-table
    ref="organisationTable"
    :header-fields="headerFields"
    resource-type="InvitableToeb"
    :procedure-id="procedureId"
    @selected-items="setSelectedItems"
  />

  <div class="mt-2 pt-2 flex">
    <div class="w-1/3 inline-block">
      <span
        v-if="selectedItems.length"
        class="weight--bold line-height--1_6"
      >
        {{ selectedItemsText }}
      </span>
    </div>
    <div class="w-2/3 text-right inline-block space-x-2">
      <dp-button
        :text="Translator.trans('invitable_institution.add')"
        data-cy="addPublicAgency"
        rounded
        @click="addPublicInterestBodies(selectedItems)"
      />
      <dp-button
        :href="Routing.generate('DemosPlan_procedure_member_index', { procedure: procedureId })"
        :text="Translator.trans('abort.and.back')"
        color="secondary"
        data-cy="organisationList:abortAndBack"
        rounded
      />
    </div>
  </div>
</template>

<script>
import { dpApi, DpButton } from '@demos-europe/demosplan-ui'
import OrganisationTable from '@DpJs/components/procedure/admin/InstitutionTagManagement/OrganisationTable'

export default {
  name: 'DpAddOrganisationList',

  components: {
    dpApi, // eslint-disable-line vue/no-unused-components
    DpButton,
    OrganisationTable,
  },

  props: {
    procedureId: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      selectedItems: [],
      headerFields: [
        {
          field: 'legalName',
          label: Translator.trans('invitable_institution'),
        },
        ...(hasPermission('field_organisation_competence') ?
          [{
            field: 'competenceDescription',
            label: Translator.trans('competence.explanation'),
          }] :
          []),
      ],
    }
  },

  computed: {
    selectedItemsText () {
      return this.selectedItems.length === 1 ?
        Translator.trans('entry.selected') :
        Translator.trans('entries.selected', { count: this.selectedItems.length })
    },
  },

  methods: {
    addPublicInterestBodies (publicAgenciesIds) {
      if (publicAgenciesIds.length === 0) {
        return dplan.notify.notify('warning', Translator.trans('organisation.select.first'))
      }

      dpApi({
        method: 'POST',
        url: Routing.generate('dplan_api_procedure_add_invited_public_affairs_bodies', {
          procedureId: this.procedureId,
        }),
        data: {
          data: publicAgenciesIds.map(id => {
            return {
              type: 'publicAffairsAgent',
              id,
            }
          }),
        },
      })
        // Refetch invitable institutions list to ensure that invited institutions are not displayed anymore
        .then(() => {
          this.$refs.organisationTable.getInstitutionsWithContacts()
            .then(() => {
              dplan.notify.notify('confirm', Translator.trans('confirm.invitable_institutions.added'))

              // Reset selected items so that the footer updates accordingly
              this.$refs.organisationTable.setSelectedItems([])
            })
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('warning.invitable_institution.not.added'))
        })
    },

    setSelectedItems (selectedItems) {
      this.selectedItems = selectedItems
    },
  },
}
</script>
