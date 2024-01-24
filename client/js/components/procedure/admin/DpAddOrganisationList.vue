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
      is-expandable
      is-selectable
      :items-per-page-options="itemsPerPageOptions"
      :default-sort-order="sortOrder"
      @items-selected="setSelectedItems"
      :init-items-per-page="itemsPerPage">
      <template v-slot:expandedContent="{ participationFeedbackEmailAddress, locationContacts, ccEmailAddresses }">
        <div class="lg:w-2/3 lg:flex pt-4">
          <dl class="layout__item">
            <dt class="color--grey">
              {{ Translator.trans('address') }}:
            </dt>
            <dd
              v-if="locationContacts.street"
              class="ml-0">
              {{ locationContacts.street }}
            </dd>
            <dd
              v-if="locationContacts.postalCode"
              class="ml-0">
              {{ locationContacts.postalCode }}
            </dd>
            <dd
              v-if="locationContacts.city"
              class="ml-0">
              {{ locationContacts.city }}
            </dd>
            <dd
              v-if="!locationContacts.street && !locationContacts.city && !locationContacts.postalCode"
              class="ml-0">
              {{ Translator.trans('notspecified') }}
            </dd>
          </dl>
          <dl class="layout__item">
            <dt class="color--grey">
              {{ Translator.trans('phone') }}
            </dt>
            <dd
              v-if="locationContacts.phone"
              class="ml-0">
              {{ locationContacts.phone }}
            </dd>
            <dd
              v-else
              class="ml-0">
              {{ Translator.trans('notspecified') }}
            </dd>
            <dt class="color--grey mt-2">
              {{ Translator.trans('email.participation') }}
            </dt>
            <dd
              v-if="participationFeedbackEmailAddress"
              class="ml-0">
              {{ participationFeedbackEmailAddress }}
            </dd>
            <dd
              v-else
              class="ml-0">
              {{ Translator.trans('no.participation.email') }}
            </dd>
            <template v-if="ccEmailAddresses">
              <dt class="color--grey mt-2">
                {{ Translator.trans('email.cc.participation') }}:
              </dt>
              <dd class="ml-0">
                {{ ccEmailAddresses }}
              </dd>
            </template>
          </dl>
        </div>
      </template>
      <template v-slot:footer>
        <div class="u-pt-0_5">
          <div class="u-1-of-3 inline-block">
            <span
              class="weight--bold line-height--1_6"
              v-if="selectedItems.length">
              {{ selectedItems.length }} {{ (selectedItems.length === 1 && Translator.trans('entry.selected')) || Translator.trans('entries.selected') }}
            </span>
          </div>
          <div class="u-2-of-3 text-right inline-block space-inline-s">
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
    ...mapState('institutionLocationContact', {
      institutionLocationContactItems: 'items'
    }),

    ...mapState('invitableToeb', {
      invitableToebItems: 'items'
    }),

    rowItems () {
      return Object.values(this.invitableToebItems).reduce((acc, item) => {
        const locationContactId = item.relationships.locationContacts.data[0].id
        const locationContact = this.getLocationContactById(locationContactId)

        return [
          ...acc,
          ...[
            {
              id: item.id,
              ...item.attributes,
              locationContacts: {
                id: locationContact.id,
                ...locationContact.attributes
              }
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
          this.getInstitutionList()
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
    },

    getInstitutionList () {
      return this.getInstitutions({
        include: ['locationContacts'].join(),
        fields: {
          InvitableToeb: [
            'legalName',
            'competenceDescription',
            'ccEmailAddresses',
            'participationFeedbackEmailAddress',
            'locationContacts',
            'contactPerson'
          ].join(),
          InstitutionLocationContact: [
            'street',
            'postalcode',
            'city',
            'phone'
          ].join()
        }
      })
    },

    getLocationContactById (id) {
      return Object.values(this.institutionLocationContactItems).find(el => el.id === id)
    },

    setSelectedItems (selectedItems) {
      this.selectedItems = selectedItems
    }
  },

  mounted () {
    this.getInstitutionList()
      .then(() => { this.isLoading = false })
  }
}
</script>
