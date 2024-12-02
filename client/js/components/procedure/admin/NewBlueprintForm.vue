<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <form
    name="xsubmititem"
    :action="Routing.generate('DemosPlan_master_new')"
    enctype="multipart/form-data"
    method="post"
    data-dp-validate>
    <input
      type="hidden"
      name="_token"
      :value="tokenVarsValue">
    <input
      type="hidden"
      name="action"
      value="new">
    <input
      type="hidden"
      name="r_master"
      value="true">
    <input
      name="_token"
      type="hidden"
      :value="csrfToken">

    <fieldset>
      <legend
        class="sr-only"
        v-text="Translator.trans('blueprint.data')" />
      <dp-input
        id="r_name"
        data-cy="newMasterName"
        :label="{
          text: Translator.trans('name')
        }"
        maxlength="200"
        name="r_name"
        required />

      <dp-select
        id="r_copymaster"
        class="mt-4"
        data-cy="NewBlueprintForm:selectedBlueprint"
        v-model="selectedBlueprint"
        :label="{
          hint: Translator.trans('procedure.template.fields', { fields: procedureTemplateFields }),
          text: Translator.trans('master')
        }"
        name="r_copymaster"
        :options="blueprintOptions"
        :show-placeholder="false"
        @select="setValuesFromSelectedBlueprint" />

      <div class="relative mt-4">
        <dp-loading
          v-if="isLoading"
          overlay />

        <dp-text-area
          id="r_desc"
          data-cy="NewBlueprintForm:internalNote"
          :label="Translator.trans('internalnote')"
          name="r_desc"
          reduced-height />

        <dp-input
          :id="agencyMainEmailId"
          class="mt-4"
          data-cy="agencyMainEmailAddress"
          :label="{
            hint: Translator.trans('explanation.organisation.email.procedure.agency'),
            text: Translator.trans('email.procedure.agency'),
            tooltip: Translator.trans('email.procedure.agency.help')
          }"
          name="agencyMainEmailAddress[fullAddress]"
          type="email"
          v-model="mainEmail" />

        <dp-label
          class="mt-4"
          for="emailList"
          :text="Translator.trans('email.address.more')"
          :hint="Translator.trans('email.address.more.explanation')"
          :tooltip="Translator.trans('email.address.more.explanation.help')" />
        <dp-email-list
          id="emailList"
          allow-updates-from-outside
          :class="`${mainEmail === '' ? 'opacity-70 pointer-events-none' : '' } mt-2`"
          :init-emails="emailAddresses" />

        <dp-text-area
          v-if="hasPermission('field_procedure_contact_person')"
          class="mt-4"
          :label="Translator.trans('public.participation.contact')"
          :hint="Translator.trans('explanation.public.participation.contact')"
          id="r_publicParticipationContact"
          name="r_publicParticipationContact"
          :value="publicParticipationContact" />

        <dp-checkbox
          v-if="hasPermission('feature_admin_customer_master_procedure_template')"
          id="r_customerMasterBlueprint"
          class="mt-4"
          :disabled="isCustomerMasterBlueprintExisting"
          :label="{
            hint: Translator.trans('explanation.customer.masterblueprint'),
            text: Translator.trans('master.of.customer.set')
          }"
          name="r_customerMasterBlueprint" />

        <dp-inline-notification
          v-if="isCustomerMasterBlueprintExisting && hasPermission('feature_admin_customer_master_procedure_template')"
          :message="Translator.trans('explanation.customer.masterblueprint.uncheck.existing')"
          type="warning" />

        <div class="text-right space-inline-s">
          <input
            class="btn btn--primary"
            type="submit"
            :value="Translator.trans('save')"
            id="saveButton"
            data-cy="NewBlueprintForm:saveButton">

          <a
            class="btn btn--secondary"
            data-cy="NewBlueprintForm:abortButton"
            :href="Routing.generate('DemosPlan_procedure_templates_list')">
            {{ Translator.trans('abort') }}
          </a>
        </div>
      </div>
    </fieldset>
  </form>
</template>

<script>
import {
  CleanHtml,
  dpApi,
  DpCheckbox,
  DpInput,
  DpLabel,
  DpLoading,
  DpSelect,
  DpTextArea
} from '@demos-europe/demosplan-ui'
import DpEmailList from '@DpJs/components/procedure/basicSettings/DpEmailList'

export default {
  name: 'NewBlueprintForm',

  directives: {
    cleanhtml: CleanHtml
  },

  components: {
    DpCheckbox,
    DpEmailList,
    DpInput,
    DpLabel,
    DpLoading,
    DpSelect,
    DpTextArea
  },

  props: {
    agencyMainEmailId: {
      type: String,
      required: true
    },

    agencyMainEmailFullAddress: {
      type: String,
      required: true
    },

    blueprintOptions: {
      type: Array,
      default: () => []
    },

    csrfToken: {
      type: String,
      required: true
    },

    initEmailAddresses: {
      type: Array,
      default: () => []
    },

    isCustomerMasterBlueprintExisting: {
      type: Boolean,
      required: true
    },

    masterBlueprintId: {
      type: String,
      required: false,
      default: () => ''
    },

    /*
     * This contains all fields that are copied from an existing procedureTemplate
     * if selected. Since one of the permissions to determine currently activated fields
     * is not even a permission but a twig variables set in DefaultTwigVariablesService.php,
     * the string is calculated in twig rather than vue.
     */
    procedureTemplateFields: {
      type: String,
      required: true
    },

    publicParticipationContact: {
      type: String,
      required: false,
      default: ''
    },

    tokenVarsValue: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      isLoading: false,
      mainEmail: this.agencyMainEmailFullAddress || '',
      selectedBlueprint: this.masterBlueprintId,
      emailAddresses: this.initEmailAddresses
    }
  },

  methods: {
    fetchSelectedBlueprint (blueprintId) {
      this.isLoading = true
      const url = Routing.generate('api_resource_get', { resourceType: 'ProcedureTemplate', resourceId: blueprintId })
      const params = {
        fields: {
          ProcedureTemplate: 'agencyMainEmailAddress,agencyExtraEmailAddresses',
          AgencyEmailAddress: 'fullAddress'
        },
        include: 'agencyExtraEmailAddresses'
      }
      return dpApi.get(url, params)
        .then(({ data }) => {
          this.isLoading = false
          return {
            mainMail: data.data.attributes.agencyMainEmailAddress,
            agencyMailAddresses: data.included.filter(el => el.type === 'AgencyEmailAddress').map(el => ({ mail: el.attributes.fullAddress }))
          }
        })
        // When the request fails planners will have to fill in an address manually
        .catch(err => {
          console.error(err)
        })
    },

    async setValuesFromSelectedBlueprint (blueprintId) {
      // Do not copy mail from master blueprint otherwise fetch mail from selected blueprint
      const blueprint = await this.fetchSelectedBlueprint(blueprintId)
      this.mainEmail = blueprintId === this.masterBlueprintId ? '' : blueprint.mainMail
      this.emailAddresses = blueprintId === this.masterBlueprintId ? [] : blueprint.agencyMailAddresses
    }
  }
}
</script>
