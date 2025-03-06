<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p
      :class="hasPermission('feature_identity_broker_login') ? prefixClass('u-3-of-4-desk-up u-mb-2') : prefixClass('c-login-register__col c-login-register__col-full')"
      v-html="Translator.trans('register.orga.description', { customer: customer })" />

    <div :class="prefixClass(`${hasPermission('feature_identity_broker_login') ? 'is-separated' : ''} c-login-register u-mt-desk-up u-mb-2-desk-up`)">
      <div :class="prefixClass(`${hasPermission('feature_identity_broker_login') ? 'c-login-register__col-left' : 'c-login-register__col-full'} c-login-register__col`)">
        <form
          :action="Routing.generate('DemosPlan_orga_register')"
          data-dp-validate
          method="post"
          name="login">
          <slot />
          <h2
            :class="prefixClass('font-size-large u-mb')"
            v-text="Translator.trans('register.email')" />
          <fieldset>
            <div :class="prefixClass('mb-0.5')">
              <dp-input
                id="r_organame"
                class="mb-2"
                data-cy="orga"
                :label="{
                  bold: false,
                  text: Translator.trans('organisation.name')
                }"
                name="r_organame"
                required />
              <dp-input
                id="r_orgaphone"
                data-cy="orga_phone"
                :label="{
                  bold: false,
                  text: Translator.trans('phone.call.back')
                }"
                name="r_orgaphone"
                required
                type="tel" />
            </div>
          </fieldset>

          <fieldset>
            <legend :class="prefixClass('font-size-medium is-label u-mb-0_25')">
              {{ Translator.trans('organisation.type') }}
            </legend>
            <p :class="prefixClass('u-mb')">
              {{ Translator.trans('organisation.kind.explanation') }}
            </p>
            <div :class="prefixClass('space-stack-s')">
              <dp-checkbox
                id="orgatype_invitable_institution"
                data-cy="orgatype_institution"
                :label="{
                  text: Translator.trans('invitable_institution'),
                  hint: Translator.trans('register.institution.hint')
                }"
                name="r_orgatype[]"
                value-to-send="OPSORG" />
              <dp-checkbox
                id="orgatype_municipality"
                data-cy="orgatype_municipality"
                :label="{
                  text: Translator.trans('municipality'),
                  hint: Translator.trans('register.municipality.hint')
                }"
                name="r_orgatype[]"
                value-to-send="OLAUTH" />
              <dp-checkbox
                id="orgatype_planningagency"
                data-cy="orgatype_planningagency"
                :label="{
                  text: Translator.trans('planningagency'),
                  hint: Translator.trans('register.planningagency.hint')
                }"
                name="r_orgatype[]"
                value-to-send="OPAUTH" />
            </div>
          </fieldset>

          <fieldset>
            <div :class="prefixClass('mb-0.5 mt-1 grid  gap-x-4 gap-y-2')">
              <legend :class="prefixClass('font-size-medium is-label mb-0 u-mt md:col-span-2')">
                {{ Translator.trans('organisation.administration') }}
              </legend>
              <dp-input
                id="r_useremail"
                :class="prefixClass('md:col-span-2')"
                data-cy="useremail"
                :label="{
                  bold: false,
                  text: Translator.trans('email.address')
                }"
                name="r_useremail"
                required
                type="email" />
              <dp-input
                id="r_firstname"
                data-cy="user_firstname"
                :label="{
                  bold: false,
                  text: Translator.trans('name.first')
                }"
                name="r_firstname"
                required />
              <dp-input
                id="r_lastname"
                data-cy="user_lastname"
                :label="{
                  bold: false,
                  text: Translator.trans('name.last')
                }"
                name="r_lastname"
                required />
            </div>
          </fieldset>

          <dp-checkbox
            id="gdpr_consent"
            data-cy="gdpr_consent"
            :class="prefixClass('u-mb-0_5')"
            :label="{
              text: Translator.trans('confirm.gdpr.consent.registration', { terms: Routing.generate('DemosPlan_misccontent_static_terms'), dataprotectionUrl: Routing.generate('DemosPlan_misccontent_static_dataprotection'), projectName: dplan.projectName })
            }"
            name="gdpr_consent"
            required
            value-to-send="on" />

          <input
            name="_csrf_token"
            type="hidden"
            :value="csrfToken">

          <dp-button
            :class="prefixClass('u-mt-0_5 u-mb-0_25')"
            data-cy="submit"
            :text="Translator.trans('register.orga')"
            type="submit" />
        </form>
      </div>

      <div
        :class="prefixClass('c-login-register__col c-login-register__col-right')"
        v-if="hasPermission('feature_identity_broker_login')">
        <h2
          :class="prefixClass('font-size-large u-mb u-mt-lap-down')"
          v-text="Translator.trans('login.other_account')" />
        <p
          :class="prefixClass('u-mb-0_125')"
          v-html="Translator.trans('login.bund.description')" />

        <!-- Insert identity broker Url when activated -->
        <dp-button
          href="#"
          :text="Translator.trans('login.bund.action')"
          variant="outline" />
        <div
          :class="prefixClass('u-mt u-mb-0_125')">
          <p v-html="Translator.trans('faq.section', { url: Routing.generate('DemosPlan_faq') })" />
        </div>
      </div>
    </div>
    <p
      :class="hasPermission('feature_identity_broker_login') ? '' : prefixClass('c-login-register__col c-login-register__col-full')"
      v-html="Translator.trans('register.navigation.alternative_text', { login: Routing.generate('DemosPlan_user_login_alternative'), registrationType: Translator.trans('citizen.alternative'), registrationLink: Routing.generate('DemosPlan_citizen_registration_form') })" />
  </div>
</template>

<script>
import {
  DpButton,
  DpCheckbox,
  DpInput,
  prefixClassMixin
} from '@demos-europe/demosplan-ui'

export default {
  name: 'OrgaRegisterForm',

  components: {
    DpButton,
    DpCheckbox,
    DpInput
  },

  mixins: [prefixClassMixin],

  props: {
    customer: {
      type: String,
      required: true
    },
    csrfToken: {
      type: String,
      required: true
    }
  }

}
</script>
