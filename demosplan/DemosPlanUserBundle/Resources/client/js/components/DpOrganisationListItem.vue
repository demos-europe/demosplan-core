<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
<!--  This component is used as a wrapper for DpItem to display organisation data that can be editable -->
</documentation>

<template>
  <dp-table-card
    :id="organisation.id"
    class="o-accordion u-ph-0_5"
    :open="isOpen">
    <!-- Item header -->
    <template v-slot:header>
      <div
        v-if="editable && selectable"
        class="u-mt-0_75 display--inline-block o-accordion--checkbox">
        <!-- 'Select item' checkbox -->
        <input
          type="checkbox"
          :id="`selected` + organisation.id"
          :checked="selected"
          data-cy="organisationItemSelect"
          @change="$emit('item:selected', organisation.id)">
      </div><!--
   --><div
        @click="isOpen = false === isOpen"
        :class="{'u-pl-0_5': false === selectable}"
        class="display--inline-block o-accordion--header-content">
        <div
          class="weight--bold u-10-of-12 u-mt-0_75 u-mb-0_5 display--inline-block o-hellip--nowrap"
          data-cy="organisationListTitle">
          {{ initialOrganisation.attributes.name }}
        </div>
          <!-- Toggle expanded/collapsed -->
        <div class="o-accordion--button float--right text--right u-mt-0_5 display--inline">
          <button
            type="button"
            data-cy="accordionToggleBtn"
            class="btn--blank o-link--default">
            <i
              class="fa"
              :class="isOpen ? 'fa-angle-up': 'fa-angle-down'" />
          </button>
        </div>
      </div>
    </template>

    <!-- Item content / editable data -->
    <div
      data-cy="editItemToggle"
      class="u-mt"
      data-dp-validate="organisationForm">
      <!-- Form fields -->
      <dp-organisation-form-fields
        :available-orga-types="availableOrgaTypes"
        :initial-organisation="initialOrganisation"
        :organisation="organisation"
        :organisation-id="organisation.id"
        @organisation-update="updateOrganisation" />

      <!-- Button row -->
      <dp-button-row
        form-name="organisationForm"
        :primary="editable"
        secondary
        :secondary-text="Translator.trans('close')"
        @primary-action="dpValidateAction('organisationForm', save)"
        @secondary-action="reset" />
    </div>
  </dp-table-card>
</template>

<script>
import DpButtonRow from '@DemosPlanCoreBundle/components/DpButtonRow'
import DpTableCard from '@DemosPlanCoreBundle/components/DpTableCardList/DpTableCard'
import dpValidateMixin from '@DpJs/lib/validation/dpValidateMixin'
import { mapState } from 'vuex'

export default {
  name: 'DpOrganisationListItem',

  components: {
    DpButtonRow,
    DpOrganisationFormFields: () => import(/* webpackChunkName: "organisation-form-fields" */ './DpOrganisationFormFields'),
    DpTableCard
  },

  mixins: [dpValidateMixin],

  inject: [
    'writableFields'
  ],

  props: {
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

  data () {
    return {
      isOpen: false,
      isLoading: true,
      moduleSubstring: (this.moduleName !== '') ? `/${this.moduleName}` : ''
    }
  },

  computed: {
    ...mapState('orga', {
      organisations: 'items'
    }),

    ...mapState('orga/pending', {
      pendingOrganisations: 'items'
    }),

    initialOrganisation () {
      return (this.moduleName === '') ? this.$store.state.orga.initial[this.organisation.id] : this.$store.state.orga[this.moduleName].initial[this.organisation.id]
    },

    /**
     * Is any of the fields editable for the current user?
     */
    editable () {
      return hasPermission('area_manage_orgas_all') || hasPermission('feature_orga_edit_all_fields') || hasPermission('area_organisations_applications_manage')
    }
  },

  methods: {
    reset () {
      this.restoreOrganisation(this.organisation.id)
        .then(() => {
          this.$root.$emit('organisation-reset')
          this.isOpen = !this.isOpen
        })
    },

    restoreOrganisation (payload) {
      return this.$store.dispatch(`orga${this.moduleSubstring}/restoreFromInitial`, payload)
    },

    save () {
      if (this.dpValidate.organisationForm) {
        this.isOpen = !this.isOpen
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
      } else {
        dplan.notify.notify('error', Translator.trans('error.mandatoryfields.no_asterisk'))
      }
    },

    saveOrganisationAction (payload) {
      this.$store.dispatch(`orga${this.moduleSubstring}/save`, payload)
        .then(() => {
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

    setItem (payload) {
      this.$store.commit(`orga${this.moduleSubstring}/setItem`, payload)
    },

    toggleItem (open) {
      this.isOpen = open
    },

    updateOrganisation (payload) {
      this.setItem({ ...payload, id: payload.id, group: null })
    }
  }
}
</script>
