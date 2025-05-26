<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    ref="contentArea"
    class="mt-2">
    <dp-search-field
      class="h-fit mt-1 col-span-1 sm:col-span-3"
      data-cy="institutionList:searchField"
      input-width="u-1-of-1"
      @reset="handleReset"
      @search="val => handleSearch(val)" />
    <dp-data-table
      ref="dataTable"
      class="mt-2"
      :header-fields="headerFields"
      is-selectable
      is-expandable
      :items="rowItems"
      lock-checkbox-by="hasNoEmail"
      :translations="{ lockedForSelection: Translator.trans('add_orga.email_hint') }"
      track-by="id"
      @items-selected="setSelectedItems">
      <template v-slot:expandedContent="{ participationFeedbackEmailAddress, locationContacts, ccEmailAddresses, contactPerson, assignedTags }">
        <div class="lg:w-2/3 lg:flex pt-4">
          <dl class="pl-4 w-full">
            <dt class="color--grey">
              {{ Translator.trans('address') }}
            </dt>
            <template v-if="locationContacts && hasAdress">
              <dd
                v-if="locationContacts.street"
                class="ml-0">
                {{ locationContacts.street }}
              </dd>
              <dd
                v-if="locationContacts.postalcode"
                class="ml-0">
                {{ locationContacts.postalcode }}
              </dd>
              <dd
                v-if="locationContacts.city"
                class="ml-0">
                {{ locationContacts.city }}
              </dd>
            </template>
            <dd
              v-else
              class="ml-0">
              {{ Translator.trans('notspecified') }}
            </dd>
          </dl>
          <dl class="pl-4 w-full">
            <dt class="color--grey">
              {{ Translator.trans('phone') }}
            </dt>
            <dd
              v-if="locationContacts?.hasOwnProperty('phone') && locationContacts.phone"
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
            <template v-if="contactPerson">
              <dt class="color--grey mt-2">
                {{ Translator.trans('contact.person') }}:
              </dt>
              <dd class="ml-0">
                {{ contactPerson }}
              </dd>
            </template>
          </dl>
          <dl
            v-if="hasPermission('feature_institution_tag_read') && Array.isArray(assignedTags) && assignedTags.length > 0"
            class="pl-4 w-full">
            <dt class="color--grey">
              {{ Translator.trans('tags') }}
            </dt>
            <dd class="ml-0">
              <div class="flex flex-wrap gap-1 mt-1">
                <span
                  v-for="tag in assignedTags"
                  :key="tag.id">
                  {{ tag.name }}
                </span>
              </div>
            </dd>
          </dl>
        </div>
      </template>
    </dp-data-table>
    <div class="mt-2 pt-2 flex">
      <div class="w-1/3 inline-block">
            <span
              v-if="selectedItems.length"
              class="weight--bold line-height--1_6">
              {{
                selectedItems.length
              }} {{
                (selectedItems.length === 1 && Translator.trans('entry.selected')) || Translator.trans('entries.selected')
              }}
            </span>
      </div>
      <div class="w-2/3 text-right inline-block space-x-2">
        <dp-button
          data-cy="addPublicAgency"
          :text="Translator.trans('invitable_institution.add')"
          @click="addPublicInterestBodies(selectedItems)"/>
        <a
          :href="Routing.generate('DemosPlan_procedure_member_index', { procedure: procedureId })"
          data-cy="organisationList:abortAndBack"
          class="btn btn--secondary">
          {{ Translator.trans('abort.and.back') }}
        </a>
      </div>
    </div>
  </div>
</template>

<script>
import { dpApi, DpButton, DpDataTable, DpSearchField } from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'

export default {
  name: 'DpAddOrganisationList',

  components: {
    DpDataTable,
    DpButton,
    DpSearchField
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
        ...hasPermission('field_organisation_competence') ? [{ field: 'competenceDescription', label: Translator.trans('competence.explanation') }] : []
      ]
    }
  },

  data () {
    return {
      invitableToebFields: [
        'legalName',
        'participationFeedbackEmailAddress',
        'locationContacts',
        ...(hasPermission('feature_institution_tag_read') ? ['assignedTags'] : [])

      ],
      isLoading: true,
      // itemsPerPageOptions: [10, 50, 100, 200],
      // itemsPerPage: 50,
      locationContactFields: ['street', 'postalcode', 'city'],
      // sortOrder: { key: 'legalName', direction: 1 },
      searchTerm: "",
      selectedItems: []
    }
  },

  computed: {
    ...mapState('InstitutionLocationContact', {
      institutionLocationContactItems: 'items'
    }),

    ...mapState('InvitableToeb', {
      invitableToebItems: 'items'
    }),

    ...mapState('InstitutionTag', {
      institutionTagItems: 'items'
    }),

    rowItems () {
      return Object.values(this.invitableToebItems).reduce((acc, item) => {
        const locationContactId = item.relationships.locationContacts?.data.length > 0 ? item.relationships.locationContacts.data[0].id : null
        const locationContact = locationContactId ? this.getLocationContactById(locationContactId) : null
        const hasNoEmail = !item.attributes.participationFeedbackEmailAddress
        const tagReferences = item.relationships.assignedTags?.data || []
        const institutionTags = tagReferences.map(tag => ({
          id: tag.id,
          name: this.institutionTagItems?.[tag.id]?.attributes?.name || Translator.trans('error.tag.notfound')
        }))

        return [
          ...acc,
          ...[
            {
              id: item.id,
              ...item.attributes,
              locationContacts: locationContact
                ? {
                    id: locationContact.id,
                    ...locationContact.attributes
                  }
                : null,
              assignedTags: institutionTags,
              hasNoEmail
            }
          ]
        ]
      }, []) || []
    }
  },

  methods: {
    ...mapActions('InvitableToeb', {
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
              id
            }
          })
        }
      })
        // Refetch invitable institutions list to ensure that invited institutions are not displayed anymore
        .then(() => {
          this.getInstitutionsWithContacts()
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

    getInstitutionsWithContacts () {
      const permissionChecksToeb = [
        { permission: 'field_organisation_email2_cc', value: 'ccEmailAddresses' },
        { permission: 'field_organisation_contact_person', value: 'contactPerson' },
        { permission: 'field_organisation_competence', value: 'competenceDescription' }
      ]

      const permissionChecksContact = [
        { permission: 'field_organisation_phone', value: 'phone' }
      ]

      const includeParams = hasPermission('feature_institution_tag_read')
        ? ['locationContacts', 'assignedTags']
        : ['locationContacts']

      const requestParams = {
        include: includeParams.join(),
        fields: {
          InvitableToeb: this.invitableToebFields.concat(this.returnPermissionChecksValuesArray(permissionChecksToeb)).join(),
          InstitutionLocationContact: this.locationContactFields.concat(this.returnPermissionChecksValuesArray(permissionChecksContact)).join()
        }
      }

      if (hasPermission('feature_institution_tag_read')) {
        requestParams.fields.InstitutionTag = 'name'
      }

      // Filter by searchTerm
      if (this.searchTerm.trim() !== '') {
        const filters = {
          namefilter: {
            condition: {
              path: 'legalName',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup',
            }
          }
        }

        //Only add competence filter if user has permission
        if (hasPermission('field_organisation_competence')) {
          filters.competencefilter = {
            condition: {
              path: 'competenceDescription',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup',
            }
          }
        }

        // Only add tag filter if user has permission
        if (hasPermission('feature_institution_tag_read')) {
          filters.tagfilter = {
            condition: {
              path: 'assignedTags.name',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm.trim(),
              memberOf: 'searchFieldsGroup',
            }
          }
        }

        filters.searchFieldsGroup = {
          group: {
            conjunction: 'OR'
          }
        }

        requestParams.filter = filters
      }

      return this.getInstitutions(requestParams)
    },

    getLocationContactById (id) {
      return this.institutionLocationContactItems[id]
    },

    hasAdress () {
      return this.rowItems.locationContacts?.street || this.rowItems.locationContacts?.postalcode || this.rowItems.locationContacts?.city
    },

    handleSearch (searchValue) {
      this.searchTerm = searchValue
      this.getInstitutionsWithContacts(searchValue)
        .then(() => { this.isLoading = false })
    },

    handleReset () {
      this.searchTerm = ''
      this.getInstitutionsWithContacts('')
        .then(() => { this.isLoading = false })
    },


    returnPermissionChecksValuesArray (permissionChecks) {
      return permissionChecks.reduce((acc, check) => {
        if (hasPermission(check.permission)) {
          acc.push(check.value)
        }
        return acc
      }, [])
    },

    setSelectedItems (selectedItems) {
      this.selectedItems = selectedItems
    }
  },

  mounted () {
    this.getInstitutionsWithContacts()
      .then(() => { this.isLoading = false })
  }
}
</script>
