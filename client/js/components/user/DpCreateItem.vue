<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- Expandable component that displays form fields to create a new item, e.g. user or organisation
      It receives the form fields via a child component that can by dynamically included

      We might put the form fields part inside a slot and add some form fields as default content, should a more
      generic use case be identifiable at any point
   -->
</documentation>

<template>
  <dp-accordion
    :is-open="isOpen"
    :title="Translator.trans(itemTitle)"
    @item:toggle="(open) => { toggleItem(open) }">
    <div class="o-box--dark soft">
      <div
        class="px-3 py-3"
        :data-cy="customComponent[entity].formName"
        :data-dp-validate="customComponent[entity].formName">
        <!-- Form fields   -->
        <component
          v-bind="dynamicComponentProps"
          :is="dynamicComponent"
          ref="formFields"
          @[dynamicEvent]="update" />

        <!-- Save/Abort buttons   -->
        <dp-button-row
          class="mt-6"
          data-cy="createItem"
          :form-name="customComponent[entity].formName"
          primary
          secondary
          @primary-action="dpValidateAction(customComponent[entity].formName, save)"
          @secondary-action="reset" />
      </div>
    </div>
  </dp-accordion>
</template>

<script>
import { DpAccordion, DpButtonRow, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'
import { mapActions, mapMutations } from 'vuex'

export default {
  name: 'DpCreateItem',

  provide () {
    return {
      proceduresDirectLinkPrefix: this.proceduresDirectLinkPrefix,
      projectName: this.projectName,
      subdomain: this.subdomain,
      submissionTypeDefault: this.submissionTypeDefault,
      submissionTypeShort: this.submissionTypeShort,
      showNewStatementNotification: this.showNewStatementNotification,
      presetUserOrgaId: this.presetUserOrgaId,
      writableFields: this.writableFields
    }
  },

  components: {
    DpAccordion,
    DpButtonRow,
    DpOrganisationFormFields: defineAsyncComponent(() => import(/* webpackChunkName: "organisation-form-fields" */ './DpOrganisationList/DpOrganisationFormFields')),
    DpUserFormFields: defineAsyncComponent(() => import(/* webpackChunkName: "user-form-fields" */ './DpUserList/DpUserFormFields'))
  },

  mixins: [dpValidateMixin],

  props: {
    availableOrgaTypes: {
      type: Array,
      required: false,
      default: () => []
    },

    /**
     * E.g. organisation, user
     * needed to define what component to use as dynamicComponent
     */
    entity: {
      type: String,
      required: true
    },

    /**
     * Accordion title
     */
    itemTitle: {
      type: String,
      required: true
    },

    presetUserOrgaId: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Needed for orgaSlug in dp-organisation-form-fields
     */
    proceduresDirectLinkPrefix: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Needed for translationKey
     */
    projectName: {
      required: false,
      type: String,
      default: ''
    },

    showNewStatementNotification: {
      type: Boolean,
      required: false,
      default: false
    },

    subdomain: {
      type: String,
      required: false,
      default: ''
    },

    writableFields: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  emits: [
    'get-items',
    'organisation-reset',
    'organisation-update',
    'user-reset',
    'user-update'
  ],

  data () {
    return {
      /**
       * Define here the properties of the dynamicComponent: name, props & updateEvent
       * componentName: {String}
       * componentProps: {Object}
       * formName: {String} needed for dpValidateAction
       * resetEvent: {String}
       * updateEvent: {String}
       */
      customComponent: {
        organisation: {
          componentName: 'dp-organisation-form-fields',
          componentProps: {
            availableOrgaTypes: this.availableOrgaTypes
          },
          formName: 'newOrganisationForm',
          resetEvent: 'organisation-reset',
          updateEvent: 'organisation-update'
        },
        user: {
          componentName: 'dp-user-form-fields',
          componentProps: {},
          formName: 'newUserForm',
          resetEvent: 'user-reset',
          updateEvent: 'user-update'
        }
      },
      isOpen: false,
      item: {}
    }
  },

  computed: {
    dynamicComponent () {
      return this.customComponent[this.entity].componentName
    },

    dynamicComponentProps () {
      return this.customComponent[this.entity].componentProps
    },

    dynamicEvent () {
      return this.customComponent[this.entity].updateEvent
    },

    itemResource () {
      const type = this.entity === 'user' ? 'AdministratableUser' : this.entity
      return {
        type,
        ...this.item
      }
    }
  },

  methods: {
    ...mapActions('Orga', {
      createOrganisation: 'create'
    }),
    ...mapActions('AdministratableUser', {
      createUser: 'create'
    }),

    ...mapMutations('AdministratableUser', {
      updateAdministratableUser: 'setItem'
    }),

    changeTypeToPascalCase (payload) {
      const newPayload = {
        ...payload,
        attributes: {
          ...payload.attributes
        },
        relationships: {
          customers: {
            data: payload.customers?.data[0].id
              ? payload.customers.data.map(el => {
                return {
                  ...el,
                  type: 'Customer'
                }
              })
              : null
          },
          departments: {
            data: payload.departments?.data[0].id
              ? payload.departments.data.map(el => {
                return {
                  ...el,
                  type: 'Department'
                }
              })
              : null
          }
        }
      }

      return newPayload
    },

    reset () {
      this.isOpen = false
      this.item = {}
      this.$root.$emit(this.customComponent[this.entity].resetEvent)
      const inputsWithErrors = this.$el.querySelector('[data-dp-validate]').querySelectorAll('.is-invalid')
      Array.from(inputsWithErrors).forEach(input => {
        input.classList.remove('is-invalid')
        const inputNodeName = input.nodeName
        if (inputNodeName === 'INPUT' || inputNodeName === 'SELECT') {
          input.setCustomValidity('')
        }
      })
    },

    save () {
      if (this.entity === 'user') {
        if (this.dpValidate.newUserForm) {
          this.createUser(this.itemResource)
            .then(response => {
              const { type: userType, relationships = {} } = this.itemResource
              const newUser = Object.values(response.data[userType])[0]
              const payload = { ...newUser, relationships }
              this.updateAdministratableUser({ ...payload, id: newUser.id })
              this.reset()
              dplan.notify.notify('confirm', Translator.trans('confirm.user.created'))
            })
            .catch(() => {
              // Fail silently
            })
        }
      } else if (this.entity === 'organisation') {
        if (this.dpValidate.newOrganisationForm) {
          // Add mandantory status<->type relation if the user didn't click the add-button
          if (this.item.attributes.registrationStatuses.length === 0) {
            this.$refs.formFields.saveNewRegistrationStatus()
          }
          // The Types for relationships should be sent as PascalCase
          const payload = this.changeTypeToPascalCase(this.itemResource)
          this.createOrganisation(payload)
            .then(() => {
              if (this.itemResource.attributes.registrationStatuses.find(el => el.status === 'pending')) {
                this.$root.$emit('get-items')
              }
              this.reset()
              // Confirm notification is done in BE
            })
            .catch(err => { console.error(err) })
        } else {
          dplan.notify.notify('error', Translator.trans('error.mandatoryfields.no_asterisk'))
        }
      }
    },

    toggleItem (open) {
      this.isOpen = open
    },

    update (item) {
      this.item = item
    }
  }
}
</script>
