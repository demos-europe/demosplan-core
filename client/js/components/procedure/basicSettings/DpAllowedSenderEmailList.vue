<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-email-list
      v-on:saved="maillaneConnectionId === null ? saveAllowedSenderAddresses : updateAllowedSenderAddresses"
      v-on:updated="updateAllowedSenderAddresses"
      :init-emails="allowedEmailAddresses"
      form-field-name="allowedSenderEmailAddresses[][fullAddress]" />
  </div>
</template>

<script>
import { dpApi } from '@demos-europe/demosplan-ui'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'

export default {
  name: 'DpAllowedSenderEmailList',

  components: {
    DpEmailList
  },

  props: {
    /*
     * The procedureId is being passed from administration_edit.html.twig to enable a
     * filtering to just receive the maillane connection of the current procedure.
     */
    procedureId: {
      type: String,
      required: true,
      default: ''
    }
  },

  data () {
    return {
      allowedEmailAddresses: [],
      maillaneConnectionId: null
    }
  },

  methods: {
    fetchAllowedSenderAddresses() {
      const url = Routing.generate('api_resource_list', {
        resourceType: 'MaillaneConnection',
        procedure: this.procedureId
      })
      const params = {
        filter: {
          procedureFilter: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId
            }
          }
        },
        fields: {
          MaillaneConnection: ['allowedSenderEmailAddresses'].join()
        }
      }

      return dpApi.get(url, params, {serialize: true})
        .then(response => {
          if (response.data.data.length !== 0) {
            response.data.data[0].id = this.maillaneConnectionId
            response.data.data[0].attributes.allowedSenderEmailAddresses.forEach(
              emailAddress => {
                this.allowedEmailAddresses.push({ mail: emailAddress })
              }
            )
          }
        })
        .catch((e) => {
          console.error(e)
        })
    },

    saveAllowedSenderAddresses(emailAddress) {
      const payload = {
        type: 'MaillaneConnection',
        attributes: {
          allowedSenderEmailAddresses: [emailAddress]
        }
      }

      dpApi.post(Routing.generate('api_resource_create', { resourceType: 'MaillaneConnection' }), {}, { data: payload })
    },

    updateAllowedSenderAddresses() {
      const addresses = []
      this.allowedEmailAddresses.forEach(address => addresses.push(address.mail))

      const payload = {
        type: 'MaillaneConnection',
        attributes: {
          allowedSenderEmailAddresses: addresses
        }
      }

      dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'MaillaneConnection', resourceId: this.maillaneConnectionId }), {}, { data: payload })
    }
  },

  mounted () {
    this.fetchAllowedSenderAddresses()
  }
}
</script>
