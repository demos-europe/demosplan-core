<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p class="u-pt-0_5">
      {{ Translator.trans('text.password.change') }}
    </p>
    <form
      class="u-mt space-stack-m"
      id="changepwd"
      ref="changepwd"
      :action="Routing.generate('DemosPlan_user_change_password')"
      method="POST"
      data-dp-validate>
      <input
        type="hidden"
        name="userId"
        :value="userId"
        required>
      <input
        name="_token"
        type="hidden"
        :value="csrfToken">

      <dp-input
        id="password_old"
        v-model="passwordOld"
        class="u-1-of-3"
        :label="{
          text: Translator.trans('password.old')
        }"
        name="password_old"
        required
        type="password" />

      <dp-input
        id="password_new"
        v-model="passwordNew"
        class="u-1-of-3"
        :label="{
          text: Translator.trans('password.new')
        }"
        name="password_new"
        required
        type="password" />

      <dp-input
        id="password_new_2"
        v-model="passwordNewConfirm"
        class="u-1-of-3"
        :label="{
          text: Translator.trans('password.new.confirm')
        }"
        name="password_new_2"
        required
        type="password" />

      <dp-button-row
        primary
        :primary-text="Translator.trans('password.change')"
        secondary
        @primary-action="submit"
        @secondary-action="resetPassword" />
    </form>
  </div>
</template>

<script>
import { DpButtonRow, DpInput } from '@demos-europe/demosplan-ui'

export default {
  name: 'ChangePassword',

  components: {
    DpButtonRow,
    DpInput
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    userId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      passwordNew: '',
      passwordNewConfirm: '',
      passwordOld: ''
    }
  },

  methods: {
    resetPassword () {
      this.passwordOld = ''
      this.passwordNew = ''
      this.passwordNewConfirm = ''
    },

    submit () {
      this.$refs.changepwd.submit()
    }
  }
}
</script>
