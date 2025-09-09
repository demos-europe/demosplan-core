<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p class="u-pt-0_5">
      {{ Translator.trans('password.set.info') }}
    </p>
    <form
      :action="Routing.generate('DemosPlan_user_password_set', {'token': token, 'uId': userId})"
      class="u-mt space-stack-m"
      data-dp-validate="changePasswordForm"
      method="POST"
      ref="changePasswordForm">
      <input
        name="_token"
        type="hidden"
        :value="csrfToken">

      <dp-input
        id="password_new"
        v-model="passwordNew"
        class="u-1-of-3"
        :label="{
          bold: false,
          text: Translator.trans('password.new')
        }"
        name="password"
        pattern="(?=.*){8,}"
        required
        type="password" />
      <dp-input
        id="password_new_2"
        v-model="passwordNewConfirm"
        class="u-1-of-3"
        :label="{
          bold: false,
          text: Translator.trans('password.new.confirm')
        }"
        name="password_new_2"
        pattern="(?=.*){8,}"
        required
        type="password" />

      <dp-button-row
        primary
        :primary-text="Translator.trans('password.set.action')"
        secondary
        @primary-action="dpValidateAction('changePasswordForm', submit, false)"
        @secondary-action="resetPassword" />
    </form>
  </div>
</template>

<script>
import { DpButtonRow, DpInput, dpValidateMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'SetPassword',

  components: {
    DpButtonRow,
    DpInput
  },

  mixins: [dpValidateMixin],

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    userId: {
      type: String,
      required: true
    },
    token: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      passwordNew: '',
      passwordNewConfirm: ''
    }
  },

  methods: {
    resetPassword () {
      this.passwordNew = ''
      this.passwordNewConfirm = ''
    },

    submit () {
      this.$refs.changePasswordForm.submit()
    }
  }
}
</script>
