<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <!-- To test the functionality of this component you need to set the variable $useIdp in demosplan/DemosPlanCoreBundle/Controller/User/DemosPlanUserAuthenticationController.php:258 to true-->
    <div :class="prefixClass(`${isIdp || hasPermission('feature_identity_broker_login') ? 'is-separated' : ''} c-login-register u-mt-desk-up u-mb-2-desk-up`)">
      <div :class="prefixClass(`${isIdp || hasPermission('feature_identity_broker_login') ? 'c-login-register__col-left' : 'c-login-register__col-full'} c-login-register__col`)">
        <form
          ref="loginForm"
          :action="Routing.generate('DemosPlan_user_login')"
          data-dp-validate="loginForm"
          method="post"
          name="login">
          <h2
            :class="prefixClass('font-size-large u-mb')"
            v-text="Translator.trans('login.email')" />

          <!-- This slot is used to pass markup from the twig template into here that is needed for spam protection. -->
          <slot />

          <div :class="prefixClass('u-mb-0_75 space-stack-s')">
            <dp-input
              id="r_useremail"
              data-cy="username"
              :label="{
                bold: false,
                text: Translator.trans('email.address'),
              }"
              name="r_useremail"
              :prevent-default-on-enter="false"
              required />
            <dp-input
              id="password"
              data-cy="password"
              :label="{
                bold: false,
                text: Translator.trans('password'),
              }"
              name="password"
              :prevent-default-on-enter="false"
              required
              type="password" />
            <input
              type="hidden"
              name="_csrf_token"
              :value="csrfToken">

            <dp-button
              :class="prefixClass('u-mt')"
              data-cy="submit"
              :text="Translator.trans('login')"
              type="submit"
              @click.prevent="submit" />
          </div>
          <a
            :class="prefixClass('o-link--default')"
            data-cy="password_forgot"
            :href="Routing.generate('DemosPlan_user_password_recover')"
            v-text="Translator.trans('password.forgot')" />
        </form>
      </div>

      <div
        v-if="isIdp || hasPermission('feature_identity_broker_login')"
        :class="prefixClass('c-login-register__col c-login-register__col-right')">
        <h2
          :class="prefixClass('font-size-large u-mb u-mt-lap-down')"
          v-text="Translator.trans('login.other_account')" />
        <div v-if="isIdp">
          <p
            :class="prefixClass('u-mb-0_125')"
            v-html="Translator.trans('login.idp.description')" />
          <dp-button
            :href="idpLoginPath"
            :text="Translator.trans('login.idp.action')"
            variant="outline" />
        </div>
        <div v-if="hasPermission('feature_identity_broker_login')">
          <p
            :class="prefixClass('u-mt u-mb-0_125')"
            v-html="Translator.trans('login.bund.description')" />

          <!-- Insert identity broker Url when activated -->
          <dp-button
            href="#"
            :text="Translator.trans('login.bund.action')"
            variant="outline" />
          <div
            :class="prefixClass('u-mt-2 u-mb-0_125')">
            <p v-html="Translator.trans('faq.section', { url: Routing.generate('DemosPlan_faq') })" />
          </div>
        </div>
      </div>
    </div>

    <p
      v-if="hasPermission('feature_citizen_registration') && hasPermission('feature_orga_registration')"
      :class="isIdp ? '' : prefixClass('c-login-register__col c-login-register__col-full')"
      v-html="Translator.trans('register.navigation.text', { organisation: Routing.generate('DemosPlan_citizen_registration_form'), user: Routing.generate('DemosPlan_orga_register_form') })" />
  </div>
</template>

<script>
import { DpButton, DpInput, dpValidateMixin, prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'IdpLoginForm',

  components: {
    DpButton,
    DpInput
  },

  mixins: [prefixClassMixin, dpValidateMixin],

  props: {
    isIdp: {
      type: Boolean,
      required: true,
      default: false
    },

    idpLoginPath: {
      type: String,
      required: true,
      default: ''
    },

    csrfToken: {
      type: String,
      required: true
    }
  },

  methods: {
    submit () {
      this.dpValidateAction('loginForm', () => {
        this.$refs.loginForm.submit()
      }, false)
    }
  }
}
</script>
