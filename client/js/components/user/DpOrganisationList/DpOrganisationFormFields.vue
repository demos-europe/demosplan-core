<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <!-- Form fields -->
    <fieldset class="layout u-mb">
      <div class="u-1-of-1 layout__item">
        <label
          v-if="canEdit('name')"
          :for="organisation.id + ':orgaName'"
          class="u-mb-0_25">
          {{ Translator.trans('name.legal') }}*
        </label>
        <label
          v-else
          :for="organisation.id + ':orgaName'"
          class="u-mb-0_25">
          {{ Translator.trans('name.legal') }}
        </label>
        <input
          v-if="canEdit('name')"
          type="text"
          :id="organisation.id + ':orgaName'"
          class="w-full u-mb-0_5 block"
          style="height: 27px;"
          data-cy="orgaFormField:orgaName"
          @input="emitOrganisationUpdate"
          v-model="localOrganisation.attributes.name"
          required>
        <p
          v-else-if="false === canEdit('name')"
          class="color--grey u-mb-0_5">
          {{ organisation.attributes.name }}
        </p>
      </div>

      <div class="layout__item u-4-of-12">
        <label
          :for="organisation.id + 'addressStreet'"
          class="u-mb-0_25">
          {{ Translator.trans('street') }}
        </label>
        <input
          v-if="canEdit('street')"
          type="text"
          :id="organisation.id + 'addressStreet'"
          class="w-full u-mb-0_5"
          style="height: 27px;"
          data-cy="orgaFormField:addressStreet"
          @input="emitOrganisationUpdate"
          v-model="localOrganisation.attributes.street">
        <p
          v-else-if="false === canEdit('street') && organisation.attributes.street !== ''"
          class="color--grey u-mb-0_5">
          {{ organisation.attributes.street }}
        </p>
        <p
          v-else-if="false === canEdit('street') && organisation.attributes.street === ''"
          class="color--grey u-mb-0_5">
          -
        </p>
      </div><!--

   --><div
        v-if="canEdit('houseNumber') || organisation.attributes.houseNumber !== ''"
        class="layout__item u-2-of-12">
        <label
          :for="organisation.id + 'addressHouseNumber'"
          class="u-mb-0_25">
          {{ Translator.trans('street.number') }}
        </label>
        <input
          v-if="canEdit('houseNumber')"
          type="text"
          :id="organisation.id + 'addressHouseNumber'"
          class="w-full u-mb-0_5"
          style="height: 27px;"
          data-cy="orgaFormField:addressHouseNumber"
          @input="emitOrganisationUpdate"
          v-model="localOrganisation.attributes.houseNumber">
        <p
          v-else-if="false === canEdit('houseNumber') && organisation.attributes.houseNumber !== ''"
          class="color--grey u-mb-0_5">
          {{ organisation.attributes.houseNumber }}
        </p>
      </div><!--
   -->
      <div
        v-if="canEdit('addressExtension') || organisation.attributes.addressExtension !== ''"
        class="layout__item u-2-of-12">
        <label
          :for="organisation.id + 'addressExtension'"
          class="u-mb-0_25">
          {{ Translator.trans('address.extension') }}
        </label>
        <input
          v-if="canEdit('addressExtension')"
          type="text"
          :id="organisation.id + 'addressExtension'"
          class="w-full u-mb-0_5"
          style="height: 27px;"
          data-cy="orgaFormField:addressExtension"
          @input="emitOrganisationUpdate"
          v-model="localOrganisation.attributes.addressExtension">
        <p
          v-else-if="false === canEdit('addressExtension') && organisation.attributes.addressExtension !== ''"
          class="color--grey u-mb-0_5">
          {{ organisation.attributes.addressExtension }}
        </p>
      </div>
      <div class="u-1-of-2 layout__item">
        <div class="layout">
          <div class="layout__item u-2-of-6">
            <label
              :for="organisation.id + ':addressPostalCode'"
              class="u-mb-0_25">
              {{ Translator.trans('postalcode') }}
            </label>
            <input
              v-if="canEdit('postalcode')"
              type="text"
              :id="organisation.id + ':addressPostalCode'"
              class="w-full"
              style="height: 27px;"
              data-cy="orgaFormField:addressPostalCode"
              pattern="^[0-9]{5}$"
              @input="emitOrganisationUpdate"
              v-model="localOrganisation.attributes.postalcode">
            <p
              v-else-if="false === canEdit('postalcode') && organisation.attributes.postalcode !== ''"
              class="color--grey u-mb-0_5">
              {{ organisation.attributes.postalcode }}
            </p>
            <p
              v-else-if="false === canEdit('postalcode') && organisation.attributes.postalcode === ''"
              class="color--grey u-mb-0_5">
              -
            </p>
          </div><!--
       --><div class="layout__item u-4-of-6">
            <label
              :for="organisation.id + ':addressCity'"
              class="u-mb-0_25">
              {{ Translator.trans('city') }}
            </label>
            <input
              v-if="canEdit('city')"
              type="text"
              :id="organisation.id + ':addressCity'"
              class="w-full"
              style="height: 27px;"
              data-cy="orgaFormField:addressCity"
              @input="emitOrganisationUpdate"
              v-model="localOrganisation.attributes.city">
            <p
              v-else-if="false === canEdit('city') && organisation.attributes.city !== ''"
              class="color--grey u-mb-0_5">
              {{ organisation.attributes.city }}
            </p>
            <p
              v-else-if="false === canEdit('city') && organisation.attributes.city === ''"
              class="color--grey u-mb-0_5">
              -
            </p>
          </div>
        </div>
      </div>
      <div
        v-if="hasPermission('field_organisation_phone')"
        class="layout__item u-2-of-6">
        <label
          :for="organisation.id + ':addressPhone'"
          class="u-mb-0_25">
          {{ Translator.trans('phone') }}
        </label>
        <input
          v-if="canEdit('phone')"
          type="text"
          :id="organisation.id + ':addressPhone'"
          class="w-full u-mb-0_5"
          style="height: 27px;"
          data-cy="orgaFormField:addressPhone"
          @input="emitOrganisationUpdate"
          v-model="localOrganisation.attributes.phone">
        <p
          v-else-if="false === canEdit('phone') && organisation.attributes.phone !== ''"
          class="color--grey u-mb-0_5">
          {{ organisation.attributes.phone }}
        </p>
        <p
          v-else-if="false === canEdit('phone') && organisation.attributes.phone === ''"
          class="color--grey u-mb-0_5">
          -
        </p>
      </div>

      <addon-wrapper
        hook-name="addon.additional.field"
        :addon-props="{
          additionalFieldOptions,
          class: 'ml-4',
          isValueRemovable: true,
          relationshipId: this.organisationId,
          relationshipKey: 'orga'
        }"
        class="w-1/2"
        @resourceList:loaded="setAdditionalFieldOptions"
        @selected="updateAddonPayload"
        @blur="updateAddonPayload" />

      <div class="layout__item u-1-of-1 u-mt">
        <legend class="u-pb-0_5">
          {{ Translator.trans('organisation.types.permissions') }}
        </legend>

        <!-- Currently assigned or requested permissions -->
        <template v-if="registrationStatuses.length > 0 && canEdit('registrationStatuses') || hasPermission('area_organisations_applications_manage')">
          <div
            v-for="(registrationStatus, idx) in registrationStatuses"
            :key="`lbl${idx}`"
            class="layout">
            <div class="layout__item u-1-of-4">
              <label
                class="u-mb-0_5"
                :for="`type_${registrationStatus.type}:${organisation.id}`">
                {{ registrationTypeLabel(registrationStatus.type) }}
              </label>
            </div><!--
         --><div class="layout__item u-1-of-4">
              <select
                class="u-1-of-1"
                :name="`type_${registrationStatus.type}:${organisation.id}`"
                :id="`type_${registrationStatus.type}:${organisation.id}`"
                data-cy="orgaFormField:editRegistrationStatus"
                @change="emitOrganisationUpdate"
                v-model="registrationStatuses[idx].status">
                <option
                  v-for="typeStatus in typeStatuses"
                  :value="typeStatus.value"
                  :key="typeStatus.value"
                  :selected="typeStatus.value === registrationStatus.status">
                  {{ typeStatus.label }}
                </option>
              </select>
            </div>
          </div>
        </template>

        <!-- Readonly: Currently assigned or requested permissions -->
        <template v-if="canEdit('registrationStatuses') === false && hasPermission('area_organisations_applications_manage') === false">
          <div
            v-for="(registrationStatus, idx) in registrationStatuses"
            :key="idx"
            class="layout u-mb-0_25">
            <div
              class="layout__item u-1-of-4 weight--bold">
              {{ registrationStatus.label }}
            </div><!--
         --><div
              class="layout__item u-1-of-4">
              {{ registrationStatusLabel(registrationStatus.status) }}
            </div>
          </div>
        </template>

        <!-- Assign new permissions -->
        <template
          v-if="availableRegistrationTypes.length > 0 && (canEdit('registrationStatuses') || hasPermission('area_organisations_applications_manage'))">
          <button
            v-if="showAddStatusForm === false"
            class="btn btn--primary u-mt-0_25 u-mb-0_5"
            @click="showAddStatusForm = true"
            data-cy="orgaFormField:showAddStatusForm"
            type="button">
            {{ Translator.trans('permission.new') }}
          </button>

          <div
            class="layout"
            v-if="showAddStatusForm">
            <!-- Select row  -->
            <div class="layout__item u-1-of-4">
              <select
                class="u-1-of-1"
                :title="Translator.trans('organisation.type')"
                data-cy="orgaFormField:organisationType"
                v-model="statusForm.type">
                <option
                  v-for="type in availableRegistrationTypes"
                  :value="type.value"
                  :key="type.value">
                  {{ Translator.trans(type.label) }}
                </option>
              </select>
            </div><!--
         --><div class="layout__item u-1-of-4">
              <select
                class="u-1-of-1"
                :title="Translator.trans('permission.status')"
                data-cy="orgaFormField:permissionStatus"
                v-model="statusForm.status">
                <option
                  v-for="typeStatus in typeStatuses"
                  :value="typeStatus.value"
                  :key="typeStatus.value">
                  {{ typeStatus.label }}
                </option>
              </select>
            </div>

            <!-- Button row  -->
            <div class="layout__item u-1-of-2 u-mt-0_5 space-inline-m">
              <button
                class="btn btn--primary"
                @click="saveNewRegistrationStatus"
                data-cy="orgaFormField:saveNewRegistrationStatus"
                type="button">
                {{ Translator.trans('permission.add') }}
              </button><!--
           --><button
                class="btn btn--secondary"
                @click="resetRegistrationStatus"
                v-if="registrationStatuses.length > 0"
                type="button">
                {{ Translator.trans('abort') }}
              </button>
            </div>
          </div>
        </template>

        <dp-checkbox
          v-if="hasPermission('feature_manage_procedure_creation_permission')"
          :id="`${organisation.id}:procedureCreatePermission`"
          class="mt-2"
          data-cy="orgaFormField:procedureCreatePermission"
          :label="{
            text: Translator.trans('procedure.canCreate'),
            bold: true
          }"
          v-model="localOrganisation.attributes.canCreateProcedures"
          @change="emitOrganisationUpdate" />
      </div>

      <div
        v-if="canEdit('cssvars') && hasPermission('feature_orga_branding_edit')"
        class="layout__item u-1-of-1 u-mt">
        <legend class="u-pb-0_5">
          {{ Translator.trans('branding.label') }}
        </legend>
        <dp-text-area
          data-cy="orgaFormField:styling"
          :hint="Translator.trans('branding.styling.hint')"
          :id="`${organisation.id}:cssvars`"
          :label="Translator.trans('branding.styling.input')"
          reduced-height
          @input="emitOrganisationUpdate"
          v-model="localOrganisation.attributes.cssvars" />
        <dp-details
          data-cy="organisationFormFields:brandingStyling"
          :summary="Translator.trans('branding.styling.details')">
          <span v-html="Translator.trans('branding.styling.details.description')" />
        </dp-details>
      </div>

      <!-- Orga slug -->
      <div
        v-if="hasPermission('feature_orga_slug_edit') && canEdit('slug')"
        class="layout__item u-mt">
        <label
          :for="organisation.id + ':slug'"
          class="u-mb-0_25">
          {{ Translator.trans('organisation.procedurelist.slug') }}
        </label>
        <p class="lbl__hint">
          {{ Translator.trans('organisation.procedurelist.slug.explanation') }}
        </p>
        <input
          v-if="canEdit('slug')"
          class="w-full inline u-mb-0_5 u-1-of-3 organisationSlug"
          style="height: 27px;"
          type="text"
          :data-organisation-id="organisation.id"
          data-slug
          :id="organisation.id + ':slug'"
          :value="organisationSlug">

        <div>
          <strong v-if="canEdit('slug')">{{ Translator.trans('preview') }}:</strong>
          <p
            :id="organisation.id + ':urlPreview'"
            :data-shorturl="proceduresDirectLinkPrefix + '/'">
            {{ proceduresDirectLinkPrefix }}/{{ organisationSlug }}
          </p>
        </div>
      </div>

      <div
        v-if="organisationId !== ''"
        class="layout__item u-mt">
        <label class="u-mb-0_25">
          {{ Translator.trans('customer', { count: hasOwnProp(organisation.relationships, 'customers') ? organisation.relationships.customers.data.length : 0 }) }}
        </label>
        <p
          v-if="hasOwnProp(organisation.relationships,'customers') && organisation.relationships.customers.data.length !== 0"
          class="color--grey">
          {{ customers }}
        </p>
        <p
          v-else
          class="u-p-0 color--grey">
          -
        </p>
      </div>
    </fieldset>

    <template v-if="hasPermission('feature_change_submission_type')">
      <legend class="layout__item u-p-0 u-pb-0_5">
        {{ Translator.trans('statement.submission.type') }}
      </legend>

      <label class="u-mb-0_25">
        <input
          type="radio"
          :value="submissionTypeShort"
          @input="emitOrganisationUpdate"
          :checked="localOrganisation.attributes.submissionType === submissionTypeShort"
          :disabled="false === canEdit('submissionType')">
        {{ Translator.trans('statement.submission.shorthand') }}

        <p class="u-ml-0_75 weight--normal">
          {{ Translator.trans('explanation.statement.submit.process.short') }}
        </p>
      </label>

      <label class="u-mb-0_25">
        <input
          type="radio"
          :value="submissionTypeDefault"
          @input="emitOrganisationUpdate"
          :checked="localOrganisation.attributes.submissionType === submissionTypeDefault"
          :disabled="false === canEdit('submissionType')">
        {{ Translator.trans('statement.submission.default') }}

        <p class="u-ml-0_75 weight--normal">
          {{ Translator.trans('explanation.statement.submit.process.default') }}
        </p>
      </label>
    </template>

    <fieldset>
      <div v-if="canEdit('email2') || hasPermission('field_organisation_email2_cc')">
        <legend class="layout__item u-p-0 u-pb-0_5">
          {{ Translator.trans('email.notifications') }}
        </legend>

        <div v-if="canEdit('email2')">
          <label
            :for="organisation.id + ':email2'"
            class="u-mb-0_25">
            {{ Translator.trans('email.participation') }}
            <p
              class="weight--normal lbl__hint"
              v-cleanhtml="Translator.trans('explanation.organisation.email.participation')" />
          </label>
          <input
            v-if="canEdit('email2')"
            :id="organisation.id + ':email2'"
            class="layout__item u-1-of-2 u-mb-0_5 u-pr-0_5"
            style="height: 27px;"
            data-cy="orgaFormField:emailParticipation"
            type="email"
            @input="emitOrganisationUpdate"
            v-model="localOrganisation.attributes.email2">
        </div>

        <div v-else-if="false === canEdit('email2')">
          <strong
            class="u-mb-0_25">
            {{ Translator.trans('email.participation') }}
          </strong>
          <p
            v-if="organisation.attributes.email2 !== ''"
            class="color--grey">
            {{ organisation.attributes.email2 }}
          </p>
          <p
            v-if="organisation.attributes.email2 === ''"
            class="color--grey">
            -
          </p>
        </div>

        <div v-if="hasPermission('field_organisation_email2_cc')">
          <div v-if="canEdit('ccEmail2')">
            <label>
              {{ Translator.trans('email.cc.participation') }}
              <p
                class="weight--normal lbl__hint"
                v-cleanhtml="Translator.trans('explanation.organisation.email.cc')" />
              <input
                class="layout__item u-1-of-2"
                style="height: 27px;"
                type="email"
                data-cy="orgaFormField:emailCC"
                @input="emitOrganisationUpdate"
                v-model="localOrganisation.attributes.ccEmail2">
            </label>
          </div>

          <div v-else-if="false === canEdit('ccEmail2')">
            <label>
              {{ Translator.trans('email.cc.participation') }}
              <p
                v-if="organisation.attributes.ccEmail2 !== ''"
                class="color--grey">
                {{ organisation.attributes.ccEmail2 }}
              </p>
              <p
                v-else-if="organisation.attributes.ccEmail2 === ''"
                class="color--grey">
                {{ organisation.attributes.ccEmail2 }}
              </p>
            </label>
          </div>
        </div>
      </div>

      <div v-if="hasPermission('feature_organisation_email_reviewer_admin') && hasPermission('field_organisation_email_reviewer_admin')">
        <label>
          {{ Translator.trans('email.reviewer.admin') }}
          <p
            class="weight--normal"
            v-cleanhtml="Translator.trans('explanation.organisation.email.reviewer.admin')" />
          <input
            class="layout__item u-1-of-2"
            style="height: 27px;"
            type="email"
            @input="emitOrganisationUpdate"
            v-model="localOrganisation.attributes.reviewerEmail">
        </label>
      </div>

      <!-- show 'notification for new statements' & 'notification for ending procedure' fields only if editable -->
      <div
        class="u-mt-0_5"
        v-if="hasPermission('feature_orga_edit_all_fields') && (hasPermission('feature_notification_statement_new') || hasPermission('feature_notification_ending_phase'))">
        <strong class="u-mb-0_25">
          {{ Translator.trans('email.notifications') }}
        </strong>
        <div v-if="hasPermission('feature_notification_statement_new') && showNewStatementNotification">
          <label
            class="weight--normal u-mb-0_25">
            <input
              type="checkbox"
              :name="organisation.id + ':emailNotificationNewStatement'"
              @change="emitOrganisationUpdate"
              v-model="localOrganisation.attributes.emailNotificationNewStatement">
            {{ Translator.trans('explanation.notification.new.statement') }}
          </label>
        </div>

        <div v-if="hasPermission('feature_notification_ending_phase')">
          <label class="weight--normal u-mb-0_25">
            <input
              type="checkbox"
              :name="organisation.id + ':emailNotificationEndingPhase'"
              data-cy="orgaFormField:notificationEndingPhase"
              @change="emitOrganisationUpdate"
              v-model="localOrganisation.attributes.emailNotificationEndingPhase">
            {{ Translator.trans('explanation.notification.ending.phase') }}
          </label>
        </div>
      </div>
    </fieldset>

    <fieldset v-if="canEdit('copy') || (hasPermission('field_organisation_paper_copy_spec') && canEdit('paperCopySpec'))">
      <div v-if="canEdit('copy') && hasPermission('field_organisation_management_paper_copy')">
        <legend class="layout__item u-mt-0_5 u-p-0 u-pb-0_5">
          {{ Translator.trans('copies.paper') }}
        </legend>
        <label>
          {{ Translator.trans('quantity') }}
          <p class="font-size-6 weight--normal">
            {{ Translator.trans('explanation.organisation.copies.paper') }}
          </p>
          <select
            class="bg-color--white"
            style="height: 27px;"
            data-cy="orgaFormField:organisationCopiesPaper"
            v-model="localOrganisation.attributes.copy"
            @change="emitOrganisationUpdate">
            <option
              v-for="(count, idx) in paperCopyCountOptions"
              :key="idx"
              :value="count">
              {{ count }}
            </option>
          </select>
        </label>
      </div>

      <label v-if="hasPermission('field_organisation_paper_copy_spec') && canEdit('paperCopySpec')">
        {{ Translator.trans('copies.kind') }}
        <p
          class="font-size-6 weight--normal"
          v-cleanhtml="Translator.trans('explanation.organisation.copies.kind')" />

        <textarea
          class="h-9"
          data-cy="orgaFormField:copiesKind"
          @input="emitOrganisationUpdate"
          v-model="localOrganisation.attributes.copySpec" />
      </label>
    </fieldset>

    <label v-if="hasPermission('field_organisation_competence') && canEdit('competence')">
      {{ Translator.trans('competence.explanation') }}
      <p
        class="font-size-6 weight--normal">
        {{ Translator.trans('explanation.organisation.competence') }}
      </p>

      <textarea
        class="h-9"
        data-cy="orgaFormField:competence"
        @input="emitOrganisationUpdate"
        v-model="localOrganisation.attributes.competence" />
    </label>

    <div
      v-else-if="hasPermission('field_organisation_competence') && false === canEdit('competence')"
      class="u-mt-0">
      <strong>
        {{ Translator.trans('competence.explanation') }}
      </strong>
      <p
        class="font-size-6 weight--normal">
        {{ Translator.trans('explanation.organisation.competence.extern') }}
      </p>
      <p
        v-if="localOrganisation.attributes.competence !== ''"
        class="color--grey weight--normal">
        {{ localOrganisation.attributes.competence }}
      </p>
      <p
        v-else-if="localOrganisation.attributes.competence === ''"
        class="color--grey weight--normal">
        -
      </p>
    </div>

    <fieldset v-if="hasPermission('field_organisation_agreement_showname') || hasPermission('feature_organisation_set_showlist')">
      <legend class="layout__item u-p-0 u-pb-0_5">
        {{ Translator.trans('public.display') }}
      </legend>

      <div v-if="hasPermission('feature_organisation_set_showlist') && canEdit('showlist')">
        <strong class="u-mb-0_25">
          {{ Translator.trans('invitable_institution.list.show') }}
        </strong>
        <p class="font-size-6">
          {{ Translator.trans('explanation.invitable_institution.list.show') }}
        </p>
        <label class="u-mb-0">
          <input
            type="checkbox"
            data-cy="orgaFormField:listShow"
            :name="organisation.id + ':showlist'"
            @change="emitOrganisationUpdate"
            v-model="localOrganisation.attributes.showlist"
            :disabled="false === canEdit('showlist')">
          {{ Translator.trans('invitable_institution.list.show.text') }}
        </label>
        <label
          class="u-ml u-mb-0"
          v-if="canEdit('showlistChangeReason') && hasChanged('showlist') && typeof initialOrganisation.attributes !== 'undefined'">
          <span class="inline-block weight--normal u-mb-0_25">
            {{ Translator.trans('reason.change') }}*
          </span>
          <textarea
            class="h-9"
            data-cy="orgaFormField:listShowChange"
            @input="emitOrganisationUpdate"
            :name="organisation.id + ':showlistChangeReason'"
            v-model="localOrganisation.attributes.showlistChangeReason"
            required />
        </label>
      </div>

      <div v-if="hasPermission('field_organisation_agreement_showname')">
        <strong class="u-mb-0">
          {{ Translator.trans('agree.publication') }}
        </strong>
        <div v-if="canEdit('showname')">
          <p class="font-size-6">
            {{ Translator.trans('agree.publication.explanation', { projectName: projectName }) }}
          </p>
          <label>
            <input
              type="checkbox"
              data-cy="orgaFormField:agree"
              :name="organisation.id + ':showname'"
              @change="emitOrganisationUpdate"
              v-model="localOrganisation.attributes.showname">
            {{ Translator.trans('agree.publication.text') }}
          </label>
        </div>
        <div v-else>
          <p class="font-size-6">
            {{ Translator.trans('agree.publication.explanation.extern', { projectName: projectName }) }}
          </p>
          <p
            v-if="localOrganisation.attributes.showname === true"
            class="color--grey">
            {{ Translator.trans('organisation.publication.agreed', { organisation: organisation.attributes.name }) }}
          </p>
          <p
            v-else-if="localOrganisation.attributes.showname === false"
            class="color--grey">
            {{ Translator.trans('organisation.publication.disagreed', { organisation: organisation.attributes.name }) }}
          </p>
        </div>
      </div>
    </fieldset>

    <!-- Imprint -->
    <fieldset v-if="hasPermission('field_imprint_text_customized_edit_orga') && organisation.attributes.isPlanningOrganisation === true">
      <legend class="layout__item u-p-0 u-pb-0_5">
        {{ Translator.trans('procedure.agency.imprint') }}
      </legend>
      <dp-editor
        data-cy="orgaFormField:imprint"
        v-model="localOrganisation.attributes.imprint"
        @input="emitOrganisationUpdate"
        :toolbar-items="{
          fullscreenButton: true,
          headings: [2,3,4],
          linkButton: true
        }" />
    </fieldset>

    <!-- Data Protection -->
    <fieldset v-if="hasPermission('field_data_protection_text_customized_edit_orga') && organisation.attributes.isPlanningOrganisation === true">
      <legend class="layout__item u-p-0 u-pb-0_5">
        {{ Translator.trans('data.protection.organisations') }}
      </legend>
      <dp-editor
        data-cy="orgaFormField:dataProtection"
        v-model="localOrganisation.attributes.dataProtection"
        @input="emitOrganisationUpdate"
        :toolbar-items="{
          fullscreenButton: true,
          headings: [2,3,4],
          linkButton: true
        }" />
    </fieldset>
  </div>
</template>

<script>
import { CleanHtml, DpCheckbox, DpDetails, DpEditor, DpTextArea, hasOwnProp } from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'

export default {
  name: 'DpOrganisationFormFields',

  components: {
    AddonWrapper,
    DpCheckbox,
    DpDetails,
    DpEditor,
    DpTextArea
  },

  inject: [
    'subdomain',
    'submissionTypeDefault',
    'submissionTypeShort',
    'showNewStatementNotification',
    'proceduresDirectLinkPrefix',
    'projectName',
    'writableFields'
  ],

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    additionalFieldOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    availableOrgaTypes: {
      type: Array,
      required: true
    },

    initialOrganisation: {
      type: Object,
      required: false,
      default: () => { return {} }
    },

    organisation: {
      type: Object,
      required: false,
      default: () => {
        return {
          attributes: {
            addressExtension: '',
            canCreateProcedures: false,
            ccEmail2: '',
            city: '',
            dataProtection: '',
            postalcode: '',
            competence: '',
            contactPerson: null,
            copy: 0,
            copySpec: '',
            email2: '',
            emailNotificationEndingPhase: false,
            emailNotificationNewStatement: false,
            imprint: '',
            name: '',
            participationEmail: '',
            phone: '',
            registrationStatuses: [],
            reviewerEmail: null,
            showlist: false,
            showlistChangeReason: '',
            showname: false,
            state: null,
            street: '',
            houseNumber: '',
            submissionType: 'standard',
            type: ''
          },
          relationships: {
            currentSlug: {
              data: {
                id: '',
                type: 'Slug'
              }
            },
            customers: {
              data: [
                {
                  id: '',
                  type: 'Customer'
                }
              ]
            },
            departments: {
              data: [
                {
                  id: '',
                  type: 'Department'
                }
              ]
            }
          }
        }
      }
    },

    organisationId: {
      type: String,
      required: false,
      default: ''
    }
  },

  emits: [
    'addon-update',
    'addonOptions:loaded',
    'organisation-update'
  ],

  data () {
    return {
      localOrganisation: {},
      typeStatuses: [
        {
          value: 'accepted',
          label: Translator.trans('enabled')
        },
        {
          value: 'pending',
          label: Translator.trans('requested')
        },
        {
          value: 'rejected',
          label: Translator.trans('rejected')
        }
      ],
      showAddStatusForm: false,
      statusForm: {
        status: 'pending',
        type: 'TöB'
      }
    }
  },

  computed: {
    availableRegistrationTypes () {
      const activeTypes = this.registrationStatuses.map(status => status.type) || []
      return this.availableOrgaTypes.filter(type => activeTypes.includes(type.value) === false)
    },

    /**
     * A.k.a. Mandanten / Bundesländer
     * @return {String}
     */
    customers () {
      if (hasOwnProp(this.organisation.relationships, 'customers') === false) {
        return ''
      } else if (Object.keys(this.organisation.relationships.customers.data).length && this.organisation.relationships.customers.data[0].id !== '') {
        const allCustomers = Object.values(this.organisation.relationships.customers.list())
        const names = []
        allCustomers.forEach(el => {
          if (typeof el !== 'undefined') {
            names.push(el.attributes.name)
          }
        })
        return names.join(', ')
      } else {
        return ''
      }
    },

    /**
     * Custom slug for the url that will show the start page with the procedures of the organisation
     * By default, this is the organisation id
     * @return {string}
     */
    organisationSlug () {
      let organisationSlug

      if (hasOwnProp(this.organisation.relationships, 'currentSlug') && this.organisation.relationships.currentSlug.data?.id !== '') {
        organisationSlug = this.organisation.relationships.currentSlug.get().attributes.name
      } else {
        organisationSlug = ''
      }

      return organisationSlug
    },

    /**
     * Options for the number of paper copies dropdown
     */
    paperCopyCountOptions () {
      return Array.from(Array(11).keys())
    },

    registrationStatuses () {
      const registrationStatuses = hasOwnProp(this.localOrganisation.attributes, 'registrationStatuses')
        ? Object.values(this.localOrganisation.attributes.registrationStatuses).filter(el => el.subdomain === this.subdomain)
        : []

      return (this.canEdit('registrationStatuses') || hasPermission('area_organisations_applications_manage'))
        ? registrationStatuses
        : []
    }
  },

  methods: {
    canEdit (field) {
      return hasPermission('feature_orga_edit_all_fields') && this.writableFields.includes(field)
    },

    /**
     * On this event DpOrganisationListItem will call the set mutation to update the store so that on save the saveAction
     * can use the data from the store
     */
    emitOrganisationUpdate () {
      // NextTick is needed because the selects do not update the local user before the emitUserUpdate method is invoked
      Vue.nextTick(() => {
        this.$emit('organisation-update', this.localOrganisation)
      })
    },

    hasChanged (field) {
      if (typeof this.initialOrganisation.attributes !== 'undefined') {
        return hasOwnProp(this.initialOrganisation.attributes, field)
          ? this.localOrganisation.attributes[field] !== this.initialOrganisation.attributes[field]
          : false
      }
      return false
    },

    hasOwnProp (obj, prop) {
      return hasOwnProp(obj, prop)
    },

    resetRegistrationStatus () {
      this.statusForm = {
        status: 'pending',
        type: (this.availableRegistrationTypes.length > 0) ? this.availableRegistrationTypes[0].value : ''
      }
      this.showAddStatusForm = false
    },

    registrationStatusLabel (status) {
      return this.typeStatuses.find(type => type.value === status).label
    },

    registrationTypeLabel (type) {
      const orgaType = this.availableOrgaTypes.find(el => el.value === type)
      return Translator.trans(orgaType.label)
    },

    saveNewRegistrationStatus () {
      // Update the local organisation state
      this.localOrganisation.attributes.registrationStatuses.push({
        type: this.statusForm.type,
        status: this.statusForm.status,
        subdomain: this.subdomain
      })
      this.emitOrganisationUpdate()
      this.resetRegistrationStatus()
    },

    setAdditionalFieldOptions (options) {
      this.$emit('addonOptions:loaded', options)
    },

    updateAddonPayload (payload) {
      this.$emit('addon-update', payload)
    }
  },

  created () {
    this.localOrganisation = JSON.parse(JSON.stringify(this.organisation))
    if (this.organisation && typeof this.organisation.hasRelationship === 'function' && this.organisation.hasRelationship('branding')) {
      this.localOrganisation.attributes.cssvars = this.organisation.rel('branding').attributes.cssvars
    }
  },

  mounted () {
    this.$root.$on('organisation-reset', () => {
      this.localOrganisation = JSON.parse(JSON.stringify(this.organisation))
    })
    if (this.registrationStatuses.length === 0) {
      this.showAddStatusForm = true
    }
    this.statusForm.type = this.availableRegistrationTypes.length > 0 ? this.availableRegistrationTypes[0].value : ''
  }
}
</script>
