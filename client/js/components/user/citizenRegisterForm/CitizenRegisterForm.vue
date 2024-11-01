<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p
      :class="isIdp ? prefixClass('u-3-of-4-desk-up u-mb-2') : prefixClass('c-login-register__col c-login-register__col-full')"
      v-html="Translator.trans('register.info.citizen')" />

    <!-- To test the functionality of this component you need to set the variable $useIdp in demosplan/DemosPlanCoreBundle/Controller/User/DemosPlanUserController.php:496 to true-->
    <div :class="prefixClass(`${isIdp ? 'is-separated' : ''} c-login-register u-mt-desk-up u-mb-2-desk-up`)">
      <div :class="prefixClass(`${isIdp ? 'c-login-register__col-left' : 'c-login-register__col-full'} c-login-register__col`)">
        <form
          :action="Routing.generate('DemosPlan_citizen_register')"
          data-dp-validate
          method="post"
          name="login">
          <h2
            :class="prefixClass('font-size-large u-mb')"
            v-text="Translator.trans('login.email')" />
          <slot />
          <div :class="prefixClass('mb-3 grid gap-4')">
            <dp-input
              id="r_email"
              :class="prefixClass('md:col-span-2')"
              data-cy="email"
              :label="{
                bold: false,
                text: Translator.trans('email.address')
              }"
              name="r_email"
              required
              type="email" />

            <dp-input
              id="r_firstname"
              data-cy="firstname"
              :label="{
                bold: false,
                text: Translator.trans('name.first')
              }"
              name="r_firstname"
              required />
            <dp-input
              id="r_lastname"
              data-cy="lastname"
              :label="{
                bold: false,
                text: Translator.trans('name.last')
              }"
              name="r_lastname"
              required />
          </div>

          <dp-checkbox
            id="gdpr_consent"
            data-cy="gdpr_consent"
            :class="prefixClass('u-mb-0_5')"
            :label="{
              text: Translator.trans('confirm.gdpr.consent.registration', { terms: Routing.generate('DemosPlan_misccontent_static_terms'), dataprotectionUrl: Routing.generate('DemosPlan_misccontent_static_dataprotection'), projectName: dplan.projectName })
            }"
            name="gdpr_consent"
            required
            value="on" />
          <input
            name="_csrf_token"
            type="hidden"
            :value="csrfToken">
          <dp-button
            :class="prefixClass('u-mt-0_5 u-mb-0_25')"
            data-cy="submit"
            :text="Translator.trans('register')"
            type="submit" />
        </form>
      </div>

      <div
        :class="prefixClass('c-login-register__col c-login-register__col-right')"
        v-if="isIdp">
        <h2
          :class="prefixClass('font-size-large u-mb u-mt-lap-down')"
          v-text="Translator.trans('login.other_account')" />
        <p
          :class="prefixClass('u-mb-0_125')"
          v-html="Translator.trans('register.idp.citizen.description', { projectName: dplan.projectName })" />
        <dp-button
          :href="idpLoginPath"
          :text="Translator.trans('login.idp.action')"
          variant="outline" />

        <div
          :class="prefixClass('u-mt-2 u-mb-0_125')">
          <p v-html="Translator.trans('faq.section', { url: Routing.generate('DemosPlan_faq') })" />
        </div>
      </div>
    </div>

    <p
      :class="isIdp ? '' : prefixClass('c-login-register__col c-login-register__col-full')"
      v-html="Translator.trans('register.navigation.alternative_text', { login: Routing.generate('DemosPlan_user_login_alternative'), registrationType: Translator.trans('organisation'), registrationLink: Routing.generate('DemosPlan_orga_register_form') })" />
  </div>
</template>

<script>
import { DpButton, DpCheckbox, DpInput, prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'CitizenRegisterForm',

  components: {
    DpButton,
    DpCheckbox,
    DpInput
  },

  mixins: [prefixClassMixin],

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
  }
}
</script>
