<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-table-card
    :id="organisation.id"
    class="o-accordion u-ph-0_5"
    :open="isOpen">
    <!-- Item header -->
    <template v-slot:header>
      <div class="flex">
        <input
          v-if="editable && selectable"
          type="checkbox"
          :id="`selected` + organisation.id"
          :checked="selected"
          data-cy="organisationItemSelect"
          @change="$emit('item:selected', organisation.id)">
        <div
          @click="isOpen = !isOpen"
          class="weight--bold cursor-pointer o-hellip--nowrap u-pv-0_75 u-ph-0_25 grow"
          data-cy="organisationListTitle">
          {{ initialOrganisation.attributes.name }}
        </div>
        <button
          @click="isOpen = !isOpen"
          type="button"
          data-cy="accordionToggleBtn"
          class="btn--blank o-link--default">
          <dp-icon
            aria-hidden="true"
            :aria-label="ariaLabel"
            :icon="icon" />
        </button>
      </div>
    </template>

    <!-- Item content / editable data -->
    <div
      data-cy="editItemToggle"
      class="u-mt"
      data-dp-validate="organisationForm">
      <!-- Form fields -->
      <dp-organisation-form-fields
        :additional-field-options="additionalFieldOptions"
        :available-orga-types="availableOrgaTypes"
        :initial-organisation="initialOrganisation"
        :organisation="organisation"
        :organisation-id="organisation.id"
        @addon-update="updateAddonPayload"
        @addonOptions:loaded="setAdditionalFieldOptions"
        @organisation-update="updateOrganisation" />

      <!-- Button row -->
      <dp-button-row
        form-name="organisationForm"
        :primary="editable"
        secondary
        @primary-action="dpValidateAction('organisationForm', save)"
        @secondary-action="reset" />
    </div>
  </dp-table-card>
</template>

<script>
import { dpApi, DpButtonRow, DpIcon, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'
import DpTableCard from '@DpJs/components/user/DpTableCardList/DpTableCard'
import { mapState } from 'vuex'

export default {
  name: 'DpOrganisationListItem',

  components: {
    DpButtonRow,
    DpIcon,
    DpOrganisationFormFields: defineAsyncComponent(() => import(/* webpackChunkName: "organisation-form-fields" */ './DpOrganisationFormFields')),
    DpTableCard
  },

  mixins: [dpValidateMixin],

  inject: [
    'writableFields'
  ],

  props: {
    additionalFieldOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    availableOrgaTypes: {
      type: Array,
      required: false,
      default: () => []
    },

    organisation: {
      type: Object,
      required: true
    },

    selectable: {
      required: false,
      type: Boolean,
      default: true
    },

    selected: {
      required: false,
      type: Boolean,
      default: false
    },

    moduleName: {
      required: false,
      type: String,
      default: ''
    }
  },

  emits: [
    'addonOptions:loaded',
    'get-items',
    'item:selected',
    'organisation-reset'
  ],

  data () {
    return {
      addonPayload: { /** The payload required for addon requests. When a value is entered in the addon field, it emits data that must include the following fields */
        attributes: null,
        id: '',
        initValue: '',
        resourceType: '',
        url: '',
        value: ''
      },
      isOpen: false,
      isLoading: true,
      moduleSubstring: (this.moduleName !== '') ? `/${this.moduleName}` : ''
    }
  },

  computed: {
    ...mapState('Orga', {
      organisations: 'items'
    }),

    ...mapState('Orga/Pending', {
      pendingOrganisations: 'items'
    }),

    ariaLabel () {
      return Translator.trans(this.isOpen ? 'aria.collapse' : 'aria.expand')
    },

    initialOrganisation () {
      return (this.moduleName === '') ? this.$store.state.Orga.initial[this.organisation.id] : this.$store.state.Orga[this.moduleName].initial[this.organisation.id]
    },

    /**
     * Is any of the fields editable for the current user?
     */
    editable () {
      return hasPermission('area_manage_orgas_all') || hasPermission('feature_orga_edit_all_fields') || hasPermission('area_organisations_applications_manage')
    },

    icon () {
      return this.isOpen ? 'chevron-up' : 'chevron-down'
    }
  },

  methods: {
    createAddonPayload () {
      return {
        type: this.addonPayload.resourceType,
        attributes: this.addonPayload.attributes,
        relationships: this.addonPayload.url === 'api_resource_update'
          ? undefined
          : {
              orga: {
                data: {
                  type: 'Orga',
                  id: this.organisation.id
                }
              }
            },
        ...(this.addonPayload.url === 'api_resource_update' ? { id: this.addonPayload.id } : {})
      }
    },

    handleAddonRequest () {
      const payload = this.createAddonPayload()

      const addonRequest = dpApi({
        headers: {
          ...(dplan.csrfToken && { 'x-csrf-token': dplan.csrfToken }) // TODO: should be adjusted in UI: api2defaultHeaders
        },
        method: this.addonPayload.url === 'api_resource_update' ? 'PATCH' : 'POST',
        url: Routing.generate(this.addonPayload.url, {
          resourceType: this.addonPayload.resourceType,
          ...(this.addonPayload.url === 'api_resource_update' && { resourceId: this.addonPayload.id })
        }),
        data: {
          data: payload
        }
      })

      return addonRequest
    },

    reset () {
      this.restoreOrganisation(this.organisation.id)
        .then(() => {
          this.$root.$emit('organisation-reset')
          this.isOpen = !this.isOpen
        })
    },

    restoreOrganisation (payload) {
      return this.$store.dispatch(`Orga${this.moduleSubstring}/restoreFromInitial`, payload)
    },

    save () {
      if (this.dpValidate.organisationForm) {
        this.isOpen = !this.isOpen
        const addonExists = Boolean(window.dplan.loadedAddons['addon.additional.field'])
        const addonHasValue = this.addonPayload.value || this.addonPayload.initValue

        if (addonExists && addonHasValue) {
          this.handleAddonRequest().then(() => this.submitOrganisationForm())
        } else {
          this.submitOrganisationForm()
        }
      } else {
        dplan.notify.notify('error', Translator.trans('error.mandatoryfields.no_asterisk'))
      }
    },

    saveOrganisationAction (payload) {
      this.$store.dispatch(`Orga${this.moduleSubstring}/save`, payload)
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
          /*
           * Reload organisations and pending organisations in case an organisation has to be moved to the other list, i.e.
           * a) the registrationStatuses of a pending organisation no longer contain a status of 'pending' or b) the registrationStatuses of an activated organisation now
           * contain a status of 'pending', i.e.
           */
          if ((typeof Object.keys(this.pendingOrganisations).find(id => id === this.organisation.id) !== 'undefined' &&
            typeof this.organisation.attributes.registrationStatuses.find(el => el.status === 'pending') === 'undefined') ||
            (typeof Object.keys(this.organisations).find(id => id === this.organisation.id) !== 'undefined' &&
            typeof this.organisation.attributes.registrationStatuses.find(el => el.status === 'pending') !== 'undefined')) {
            this.$root.$emit('get-items')
          }
        })
    },

    setAdditionalFieldOptions (options) {
      this.$emit('addonOptions:loaded', options)
    },

    setItem (payload) {
      this.$store.commit(`Orga${this.moduleSubstring}/setItem`, payload)
    },

    submitOrganisationForm () {
      /*
       * Some update requests need this information, others cant handle them
       * depending on the permissions
       */
      const additionalAttributes = ['showname', 'showlist']

      if (hasPermission('feature_notification_ending_phase')) {
        additionalAttributes.push('emailNotificationEndingPhase')
      }
      if (hasPermission('feature_notification_statement_new')) {
        additionalAttributes.push('emailNotificationNewStatement')
      }

      this.saveOrganisationAction({
        id: this.organisation.id,
        options: {
          attributes: {
            full: ['registrationStatuses'],
            unchanged: additionalAttributes
          }
        }
      })
    },

    toggleItem (open) {
      this.isOpen = open
    },

    updateAddonPayload (payload) {
      this.addonPayload = payload
    },

    updateOrganisation (payload) {
      this.setItem({ ...payload, id: payload.id })
    }
  }
}
</script>
