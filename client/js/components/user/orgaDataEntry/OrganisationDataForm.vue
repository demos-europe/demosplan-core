<template>
  <form
    :class="prefixClass('u-mt')"
    :action="Routing.generate('DemosPlan_orga_edit_save', { orgaId: organisation.id })"
    method="post"
    data-dp-validate="orgadata">
    <input
      data-cy="editOrga:organisationId"
      type="hidden"
      name="organisation_ident"
      :value="organisation.id">
    <input
      data-cy="editOrga:addressIdent"
      type="hidden"
      name="address_ident"
      :value="organisation.addressId">
    <input
      type="hidden"
      name="_token"
      :value="csrfToken">
    <fieldset :class="prefixClass('w-3/4')">
      <legend :class="prefixClass('font-size-large weight--normal mb-3')">
        {{ Translator.trans('organisation.data') }}
      </legend>

      <!-- Name -->
      <dp-input
        id="orga_name"
        v-model="organisation.name"
        :class="prefixClass('mb-2')"
        data-cy="organisationData:name"
        :name="`${organisation.id}:name`"
        :label="{
          text: Translator.trans('name.legal')
        }"
        :disabled="!isOrgaDataEditable"
        required />

      <!-- Street -->
      <div :class="prefixClass('flex items-start gap-1 mb-2')">
        <dp-input
          id="orga_address_street"
          v-model="organisation.street"
          :class="!organisation.street.length ? prefixClass('w-4') : ''"
          data-cy="organisationData:address:street"
          :name="`${organisation.id}:address_street`"
          :label="{
            text: Translator.trans('street')
          }"
          :size="!isOrgaDataEditable ? organisation.street.length : null"
          :disabled="!isOrgaDataEditable" />

        <dp-input
          v-if="showDetailedInfo"
          id="orga_addressHouseNumber"
          v-model="organisation.houseNumber"
          data-cy="organisationData:address:houseNumber"
          :name="`${organisation.id}:address_houseNumber`"
          :label="{
            text: Translator.trans('street.number.short')
          }"
          :size="5"
          :disabled="!isOrgaDataEditable" />
      </div>

      <!-- Postal Code and City -->
      <div :class="[prefixClass(showDetailedInfo ? 'flex-row' : 'flex-col'), prefixClass('flex items-start gap-1 mb-2')]">
        <dp-input
          id="orga_address_postalcode"
          v-model="organisation.postalcode"
          data-cy="organisationData:address:postalcode"
          :class="prefixClass('shrink')"
          :name="`${organisation.id}:address_postalcode`"
          :label="{
            text: Translator.trans('postalcode')
          }"
          :pattern="isOrgaDataEditable ? '^[0-9]{5}$' : ''"
          :size="5"
          :disabled="!isOrgaDataEditable" />

        <dp-input
          id="orga_address_city"
          v-model="organisation.city"
          data-cy="organisationData:address:city"
          :name="`${organisation.id}:address_city`"
          :label="{
            text: Translator.trans('city')
          }"
          :disabled="!isOrgaDataEditable" />
      </div>

      <!-- Phone -->
      <dp-input
        v-if="hasPermission('field_organisation_phone')"
        id="orga_address_phone"
        :class="prefixClass('mb-2')"
        v-model="organisation.phone"
        data-cy="organisationData:phone"
        :name="`${organisation.id}:address_phone`"
        :label="{
          text: Translator.trans('phone')
        }"
        :disabled="!isOrgaDataEditable" />

      <!-- Types -->
      <dp-select
        v-if="hasTypes"
        id="orga_type"
        :class="prefixClass('mb-2')"
        data-cy="organisationData:type"
        :name="`${organisation.id}:type`"
        :options="orgaTypes"
        :selected="orgaTypes[0]"
        :label="{
          text: Translator.trans('type')
        }"
        :disabled="!isOrgaDataEditable" />

      <!-- Slug -->
      <div v-if="hasPermission('feature_orga_slug') && hasPermission('feature_orga_slug_edit')">
        <label
          for="orga_slug"
          :class="prefixClass('o-form__label')">
          {{ Translator.trans('organisation.procedurelist.slug') }}
        </label>
        <small :class="prefixClass('lbl_hint block')">
          {{ Translator.trans('organisation.procedurelist.slug.explanation') }}
        </small>

        <div :class="prefixClass('flex flex-row items-center')">
          <span :class="prefixClass('color--grey')">
            {{ proceduresDirectlinkPrefix }}
          </span>
          <dp-input
            id="orga_slug"
            v-model="organisation.currentSlugName"
            data-cy="organisationData:currentSlugName"
            :data-organisation-id="organisation.id"
            :name="`${organisation.id}:slug`" />
        </div>

        <div>
          <label
            :for="`${organisation.id}:urlPreview`"
            :class="prefixClass('o-form__label')">
            {{ Translator.trans('preview') }}
          </label>
          <p
            :id="`${organisation.id}:urlPreview`"
            :data-shorturl="proceduresDirectlinkPrefix + '/'">
            {{ proceduresDirectlinkPrefix }}/{{ organisation.currentSlugName || '' }}
          </p>
        </div>
      </div>

      <!-- Display Slug and Customer List -->
      <template v-if="showDetailedInfo">
        <dl
          v-if="displaySlug || displayCustomer"
          :class="prefixClass('description-list space-stack-s')">
          <div v-if="displaySlug">
            <dt class="font-semibold">
              {{ Translator.trans('organisation.procedurelist.slug') }}
            </dt>
            <dd :class="prefixClass('color--grey')">
              {{ proceduresDirectlinkPrefix }}/{{ organisation.currentSlugName }}
            </dd>
          </div>

          <div v-if="displayCustomer">
            <dt :class="prefixClass('font-semibold')">
              {{ Translator.trans('customer', { count: customers.length }) }}
            </dt>
            <dd
              v-for="(customer, index) in customers"
              :key="customer.id"
              :class="prefixClass('color--grey inline')">
              {{ customer.name }}<span v-if="index < customers.length - 1">, </span>
            </dd>
          </div>
        </dl>
      </template>
    </fieldset>

    <!-- Submission type -->
    <fieldset
      v-if="hasPermission('feature_change_submission_type')"
      id="submissionType"
      :class="prefixClass('w-3/4 mb-2')">
      <legend :class="prefixClass('font-size-large weight--normal mb-3')">
        {{ Translator.trans('statement.submission.type') }}
      </legend>
      <input
        type="hidden"
        :name="`${organisation.id}:current_submission_type`"
        :value="organisation.submissionType">
      <dp-radio
        id="submission_type_short"
        :name="`${organisation.id}:submission_type`"
        :value="submissionTypeShort"
        data-cy="organisationData:submissionType:short"
        :label="{
          text: Translator.trans('statement.submission.shorthand'),
          bold: true,
          hint: Translator.trans('explanation.statement.submit.process.short')
        }"
        :checked="organisation.submissionType === submissionTypeShort" />
      <dp-radio
        id="submission_type_default"
        :name="`${organisation.id}:submission_type`"
        :value="submissionTypeDefault"
        data-cy="organisationData:submissionType:default"
        :label="{
          text: Translator.trans('statement.submission.default'),
          bold: true,
          hint: Translator.trans('explanation.statement.submit.process.default')
        }"
        :checked="organisation.submissionType === submissionTypeDefault" />
    </fieldset>

    <email-notification-settings
      :organisation="organisation"
      :user="user"
      :will-receive-new-statement-notification="willReceiveNewStatementNotification"
      :has-notification-section="hasNotificationSection" />

    <paper-copy-preferences
      v-if="hasPaperCopyPermission"
      :organisation="organisation" />

    <organisation-competence :organisation="organisation" />

    <organisation-branding-settings
      :organisation="organisation"
      :project-name="projectName" />

    <div
      v-if="displayButtons"
      :class="prefixClass('text-right space-inline-s')">
      <dp-button
        data-cy="organisationData:saveButton"
        :text="Translator.trans('save')"
        @click="handleSubmit" />

      <dp-button
        type="reset"
        color="secondary"
        data-cy="organisationData:abortButton"
        :text="Translator.trans('reset')" />
    </div>
  </form>
</template>

<script>
import { DpButton, DpInput, DpRadio, DpSelect, prefixClassMixin } from '@demos-europe/demosplan-ui'
import EmailNotificationSettings from '@DpJs/components/user/orgaDataEntry/EmailNotificationSettings'
import OrganisationBrandingSettings from '@DpJs/components/user/orgaDataEntry/OrganisationBrandingSettings'
import OrganisationCompetence from '@DpJs/components/user/orgaDataEntry/OrganisationCompetence'
import PaperCopyPreferences from '@DpJs/components/user/orgaDataEntry/PaperCopyPreferences'

export default {
  name: 'OrganisationDataForm',

  components: {
    DpButton,
    DpInput,
    DpRadio,
    DpSelect,
    EmailNotificationSettings,
    OrganisationBrandingSettings,
    OrganisationCompetence,
    PaperCopyPreferences
  },

  mixins: [prefixClassMixin],

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    customers: {
      type: Array,
      required: false,
      default: () => ([])
    },

    hasTypes: {
      type: Boolean,
      required: false,
      default: false
    },

    isOrgaDataEditable: {
      type: Boolean,
      required: true
    },

    hasNotificationSection: {
      type: Boolean,
      required: false,
      default: false
    },

    projectName: {
      type: String,
      required: true
    },

    organisation: {
      type: Object,
      required: true
    },

    orgaTypes: {
      type: Array,
      required: false,
      default: () => ([])
    },

    proceduresDirectlinkPrefix: {
      type: String,
      required: false,
      default: ''
    },

    showDetailedInfo: {
      type: Boolean,
      required: false,
      default: false
    },

    submissionTypeDefault: {
      type: String,
      required: false,
      default: ''
    },

    submissionTypeShort: {
      type: String,
      required: false,
      default: ''
    },

    user: {
      type: Object,
      required: true
    },

    willReceiveNewStatementNotification: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  data () {
    return {
      displayCustomer: hasPermission('feature_display_customer_names') && this.customers?.length > 0,
      displaySlug: hasPermission('feature_orga_slug') &&
        !hasPermission('feature_orga_slug_edit') &&
        this.organisation.currentSlugName !== '',
      hasPaperCopyPermission: hasPermission('field_organisation_paper_copy') ||
        hasPermission('field_organisation_paper_copy_spec') ||
        hasPermission('field_organisation_competence')
    }
  },

  computed: {
    displayButtons () {
      return this.isOrgaDataEditable ||
        this.hasPaperCopyPermission ||
        this.hasNotificationSection ||
        this.showDetailedInfo ||
        hasPermission('feature_change_submission_type')
    }
  },
  methods: {
    handleSubmit () {
      if (hasPermission('feature_change_submission_type') &&
        this.organisation.submissionType === this.submissionTypeShort &&
        !window.dpconfirm(Translator.trans('confirm.statement.orgaedit.change'))) {
        this.$el.reset()
        return false
      }

      this.$el.submit()
    }
  },

  mounted () {
    this.$el.querySelectorAll('input[type=text]').forEach((input) => {
      input.defaultValue = input.value
    })

    this.$el.querySelectorAll('input[type=radio]').forEach((input) => {
      input.defaultChecked = input.checked
    })
  }
}
</script>
