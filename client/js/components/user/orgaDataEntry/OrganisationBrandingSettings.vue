<template>
  <fieldset
    v-if="showOrganisationBrandingSection"
    class="w-3/4">
    <template v-if="hasPermission('feature_orga_logo_edit')">
      <legend class="font-size-large weight--normal u-mb-0_25">
        {{ Translator.trans('organisation.procedures.branding') }}
      </legend>
      <p v-cleanhtml="brandingExplanation"></p>
    </template>
    <template v-else>
      <legend class="font-size-large weight--normal u-mb-0_75">
        {{ Translator.trans('organisation.procedures.branding') }}
      </legend>
    </template>

    <!-- Data Protection -->
    <div
      v-if="hasPermission('field_data_protection_text_customized_edit_orga')"
      class="o-form__label w-full">
      <label
        :for="`${organisation.id}:data_protection`"
        class="o-form__label w-full">
        {{ Translator.trans('data.protection.notes') }}
        <small class="lbl__hint block">
          {{ Translator.trans('customer.data.protection.explanation') }}
        </small>
      </label>
      <dp-editor
        :id="`${organisation.id}:data_protection`"
        class="o-form__control-tiptap u-mb-0_75"
        data-cy="organisationData:branding:dataProtection"
        :hidden-input="organisation.dataProtection || ''"
        :toolbar-items="{
            linkButton: true,
            headings: [3, 4]
          }"
        :value="organisation.dataProtection || ''" />
    </div>

    <!-- Imprint -->
    <div
      v-if="hasPermission('field_imprint_text_customized_edit_orga')"
      class="w-full">
      <label
        :for="`${organisation.id}:imprint`"
        class="o-form__label w-full">
        {{ Translator.trans('imprint') }}
        <small class="lbl__hint block">
          {{ Translator.trans('organisation.imprint.hint') }}
        </small>
      </label>
      <dp-editor
        :id="`${organisation.id}:imprint`"
        class="o-form__control-tiptap u-mb-0_75"
        data-cy="organisationData:branding:imprint"
        :hidden-input="`${organisation.id}:imprint`"
        :toolbar-items="{
          linkButton: true,
          headings: [3, 4]
        }"
        :value="organisation.imprint || ''" />
    </div>

    <!-- Public Display -->
    <div
      v-if="hasPermission('field_organisation_agreement_showname')"
      class="mb-0">
      <!-- showList needs to be defined as it is an mandatory field, if orga is changed by support user (bobhh) -->
      <input
        type="hidden"
        :name="`${organisation.id}:showlist`"
        :value="organisation && organisation.showlist ? 1 : 0">
      <label
        for="orga_showname"
        class="o-form__label bald">
        {{ Translator.trans('agree.publication') }}
      </label>
      <small class="lbl__hint">
        {{ Translator.trans('agree.publication.explanation', { projectName }) }}
      </small>
      <dp-checkbox
        id="orga_showname"
        :name="`${organisation.id}:showname`"
        :checked="organisation.showname"
        :label="{
          text: Translator.trans('agree.publication.text'),
          bold: true
        }" />
    </div>
  </fieldset>
</template>

<script>
import { CleanHtml, DpEditor, DpCheckbox } from '@demos-europe/demosplan-ui'

export default {
  name: 'OrganisationBrandingSettings',

  components: {
    DpEditor,
    DpCheckbox
  },

  directives: {
    cleanhtml: CleanHtml,
  },

  props: {
    hasOrganisationBrandingSection: {
      type: Boolean,
      required: false,
      default: true
    },

    organisation: {
      type: Object,
      required: true
    },

    projectName: {
      type: String,
      required: true
    }
  },

  computed: {
    showOrganisationBrandingSection () {
      return hasPermission('feature_orga_logo_edit') ||
        hasPermission('field_data_protection_text_customized_edit_orga') ||
        hasPermission('field_imprint_text_customized_edit_orga') ||
        hasPermission('field_organisation_agreement_showname')
    },

    brandingExplanation() {
      if (hasPermission('feature_orga_logo_edit')) {
        return Translator.trans('organisation.procedures.branding.link', {
          href: Routing.generate('DemosPlan_orga_branding_edit', {
            orgaId: this.organisation.id || ''
          })
        })
      }
      return ''
    }
  }
}
</script>
