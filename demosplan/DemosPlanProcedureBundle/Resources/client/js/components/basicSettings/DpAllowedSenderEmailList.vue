<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-email-list
    :init-emails="allowedEmailAddresses"
    :form-field-name="formFieldName" />
</template>

<script>
import { dpApi } from '@DemosPlanCoreBundle/plugins/DpApi'
import DpEmailList from '@DemosPlanProcedureBundle/components/basicSettings/DpEmailList'

export default {
  name: "DpAllowedSenderEmailList",

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
      default: '',
    }
  },

  data () {
    return {
      allowedEmailAddresses: [],
      formFieldName: 'allowedSenderEmailAddresses[][fullAddress]'
    }
  },

  methods: {
    fetchAllowedSenderAddresses () {
      const url = Routing.generate('api_resource_list', { resourceType: 'MaillaneConnection', procedure: this.procedureId })
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

      return dpApi.get(url, params, { serialize: true })
        .then(response => {
          if (response.data.data.length !== 0) {
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
    }
  },

  mounted () {
    this.fetchAllowedSenderAddresses()
  }
}
</script>
