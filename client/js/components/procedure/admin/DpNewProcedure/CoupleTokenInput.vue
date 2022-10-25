<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-input
      aria-labelledby="token-notification"
      id="procedureCoupleToken"
      data-dp-validate-if="#procedureCoupleToken!==''"
      :label="{
        text: Translator.trans('procedure.couple_token.label')
      }"
      :maxlength="tokenLength"
      :minlength="tokenLength"
      name="procedureCoupleToken"
      @input="validateToken" />
    <dp-inline-notification
      v-if="notification"
      id="token-notification"
      :message="notification.text"
      :type="notification.type" />
  </div>
</template>
<script>
import DpInlineNotification from '@DpJs/components/core/DpInlineNotification'
import { DpInput } from 'demosplan-ui/components'
import { dpRpc } from '@DemosPlanCoreBundle/plugins/DpApi'
import length from 'demosplan-ui/shared/props'

export default {
  name: 'CoupleTokenInput',

  components: {
    DpInlineNotification,
    DpInput
  },

  props: {
    tokenLength: length
  },

  data () {
    return {
      notification: null
    }
  },

  methods: {
    async validateToken (token) {
      if (token.length > (this.tokenLength - 1)) {
        const notification = {}
        const response = await dpRpc('procedure.token.usage', { token: token })
        const sourceProcedure = response.data[0].result.sourceProcedure
        const targetProcedure = response.data[0].result.targetProcedure

        if (sourceProcedure && targetProcedure) {
          notification.text = Translator.trans('procedure.couple_token.validation.already_used')
          notification.type = 'warning'
        } else if (sourceProcedure) {
          notification.text = Translator.trans('procedure.couple_token.validation.success', { orgaName: sourceProcedure.orgaName, procedureName: sourceProcedure.name })
          notification.type = 'confirm'
        } else {
          notification.text = Translator.trans('procedure.couple_token.validation.not_found')
          notification.type = 'error'
        }

        this.notification = notification
      } else {
        this.notification = null
      }
    }
  }
}
</script>
