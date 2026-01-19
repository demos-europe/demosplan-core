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
          class="u-mb-0_25"
        >
          {{ Translator.trans('name.legal') }}*
        </label>
        <label
          v-else
          :for="organisation.id + ':orgaName'"
          class="u-mb-0_25"
        >
          {{ Translator.trans('name.legal') }}
        </label>
        <input
          v-if="canEdit('name')"
          :id="organisation.id + ':orgaName'"
          v-model="localOrganisation.attributes.name"
          type="text"
          class="w-full u-mb-0_5 block"
          style="height: 27px;"
          data-cy="orgaFormField:orgaName"
          required
          @input="emitOrganisationUpdate"
        >
        <p
          v-else-if="false === canEdit('name')"
          class="color--grey u-mb-0_5"
        >
          {{ organisation.attributes.name }}
        </p>
      </div>

      <div class="layout__item u-4-of-12">
        <label
          :for="organisation.id + 'addressStreet'"
          class="u-mb-0_25"
        >
          {{ Translator.trans('street') }}
        </label>
        <input
          v-if="canEdit('street')"
          :id="organisation.id + 'addressStreet'"
          v-model="localOrganisation.attributes.street"
          type="text"
          class="w-full u-mb-0_5"
          style="height: 27px;"
          data-cy="orgaFormField:addressStreet"
          @input="emitOrganisationUpdate"
        >
        <p
          v-else-if="false === canEdit('street') && organisation.attributes.street !== ''"
          class="color--grey u-mb-0_5"
        >
          {{ organisation.attributes.street }}
        </p>
        <p
          v-else-if="false === canEdit('street') && organisation.attributes.street === ''"
          class="color--grey u-mb-0_5"
        >
          -
        </p>
      </div><!--

   --><div
        v-if="canEdit('houseNumber') || organisation.attributes.houseNumber !== ''"
        class="layout__item u-2-of-12"
      >
        <label
          :for="organisation.id + 'addressHouseNumber'"
          class="u-mb-0_25"
        >
          {{ Translator.trans('street.number') }}
        </label>
        <input
          v-if="canEdit('houseNumber')"
          :id="organisation.id + 'addressHouseNumber'"
          v-model="localOrganisation.attributes.houseNumber"
          type="text"
          class="w-full u-mb-0_5"
          style="height: 27px;"
          data-cy="orgaFormField:addressHouseNumber"
          @input="emitOrganisationUpdate"
        >
        <p
          v-else-if="false === canEdit('houseNumber') && organisation.attributes.houseNumber !== ''"
          class="color--grey u-mb-0_5"
        >
          {{ organisation.attributes.houseNumber }}
        </p>
      </div><!--
   -->
      <div
        v-if="canEdit('addressExtension') || organisation.attributes.addressExtension !== ''"
        class="layout__item u-2-of-12"
      >
        <label
          :for="organisation.id + 'addressExtension'"
          class="u-mb-0_25"
        >
          {{ Translator.trans('address.extension') }}
        </label>
        <input
          v-if="canEdit('addressExtension')"
          :id="organisation.id + 'addressExtension'"
          v-model="localOrganisation.attributes.addressExtension"
          type="text"
          class="w-full u-mb-0_5"
          style="height: 27px;"
          data-cy="orgaFormField:addressExtension"
          @input="emitOrganisationUpdate"
        >
        <p
          v-else-if="false === canEdit('addressExtension') && organisation.attributes.addressExtension !== ''"
          class="color--grey u-mb-0_5"
        >
          {{ organisation.attributes.addressExtension }}
        </p>
      </div>
      <div class="u-1-of-2 layout__item">
        <div class="layout">
          <div class="layout__item u-2-of-6">
            <label
              :for="organisation.id + ':addressPostalCode'"
              class="u-mb-0_25"
            >
              {{ Translator.trans('postalcode') }}
            </label>
            <input
              v-if="canEdit('postalcode')"
              :id="organisation.id + ':addressPostalCode'"
              v-model="localOrganisation.attributes.postalcode"
              type="text"
              class="w-full"
              style="height: 27px;"
              data-cy="orgaFormField:addressPostalCode"
              pattern="^[0-9]{5}$"
              @input="emitOrganisationUpdate"
            >
            <p
              v-else-if="false === canEdit('postalcode') && organisation.attributes.postalcode !== ''"
              class="color--grey u-mb-0_5"
            >
              {{ organisation.attributes.postalcode }}
            </p>
            <p
              v-else-if="false === canEdit('postalcode') && organisation.attributes.postalcode === ''"
              class="color--grey u-mb-0_5"
            >
              -
            </p>
          </div><!--
       --><div class="layout__item u-4-of-6">
            <label
              :for="organisation.id + ':addressCity'"
              class="u-mb-0_25"
            >
              {{ Translator.trans('city') }}
            </label>
            <input
              v-if="canEdit('city')"
              :id="organisation.id + ':addressCity'"
              v-model="localOrganisation.attributes.city"
              type="text"
              class="w-full"
              style="height: 27px;"
              data-cy="orgaFormField:addressCity"
              @input="emitOrganisationUpdate"
            >
            <p
              v-else-if="false === canEdit('city') && organisation.attributes.city !== ''"
              class="color--grey u-mb-0_5"
            >
              {{ organisation.attributes.city }}
            </p>
            <p
              v-else-if="false === canEdit('city') && organisation.attributes.city === ''"
              class="color--grey u-mb-0_5"
            >
              -
            </p>
          </div>
        </div>
      </div>
      <div
        v-if="hasPermission('field_organisation_phone')"
        class="layout__item u-2-of-6"
      >
        <label
          :for="organisation.id + ':addressPhone'"
          class="u-mb-0_25"
        >
          {{ Translator.trans('phone') }}
        </label>
        <input
          v-if="canEdit('phone')"
          :id="organisation.id + ':addressPhone'"
          v-model="localOrganisation.attributes.phone"
          type="text"
          class="w-full u-mb-0_5"
          style="height: 27px;"
          data-cy="orgaFormField:addressPhone"
          @input="emitOrganisationUpdate"
        >
        <p
          v-else-if="false === canEdit('phone') && organisation.attributes.phone !== ''"
          class="color--grey u-mb-0_5"
        >
          {{ organisation.attributes.phone }}
        </p>
        <p
          v-else-if="false === canEdit('phone') && organisation.attributes.phone === ''"
          class="color--grey u-mb-0_5"
        >
          -
        </p>
      </div>

      <addon-wrapper
        hook-name="interface.fields.to.transmit"
        :addon-props="{
          additionalFieldOptions,
          class: 'ml-4',
          isValueRemovable: true,
          relationshipId: organisationId,
          relationshipKey: 'orga',
          userOrgaId: organisationId,
          userMeinBerlinOrgId: ''
        }"
        class="w-1/2"
        @resource-list:loaded="setAdditionalFieldOptions"
        @selected="updateAddonPayload"
        @blur="updateAddonPayload"
      />

      <div class="layout__item u-1-of-1 u-mt">
        <legend class="u-pb-0_5">
          {{ Translator.trans('organisation.types.permissions') }}
        </legend>

        <!-- Currently assigned or requested permissions -->
        <template v-if="registrationStatuses.length > 0 && canEdit('registrationStatuses') || hasPermission('area_organisations_applications_manage')">
          <div
            v-for="(registrationStatus, idx) in registrationStatuses"
            :key="`lbl${idx}`"
            class="layout"
          >
            <div class="layout__item u-1-of-4">
              <label
                class="u-mb-0_5"
                :for="`type_${registrationStatus.type}:${organisation.id}`"
              >
                {{ registrationTypeLabel(registrationStatus.type) }}
              </label>
            </div><!--
         --><div class="layout__item u-1-of-4">
              <select
                :id="`type_${registrationStatus.type}:${organisation.id}`"
                v-model="registrationStatuses[idx].status"
                class="u-1-of-1"
                :name="`type_${registrationStatus.type}:${organisation.id}`"
                data-cy="orgaFormField:editRegistrationStatus"
                @change="emitOrganisationUpdate"
              >
                <option
                  v-for="typeStatus in typeStatuses"
                  :key="typeStatus.value"
                  :value="typeStatus.value"
                  :selected="typeStatus.value === registrationStatus.status"
                >
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
            class="layout u-mb-0_25"
          >
            <div
              class="layout__item u-1-of-4 weight--bold"
            >
              {{ registrationStatus.label }}
            </div><!--
         --><div
              class="layout__item u-1-of-4"
            >
              {{ registrationStatusLabel(registrationStatus.status) }}
            </div>
          </div>
        </template>

        <!-- Assign new permissions -->
        <template
          v-if="availableRegistrationTypes.length > 0 && (canEdit('registrationStatuses') || hasPermission('area_organisations_applications_manage'))"
        >
          <button
            v-if="showAddStatusForm === false"
            class="btn btn--primary u-mt-0_25 u-mb-0_5"
            data-cy="orgaFormField:showAddStatusForm"
            type="button"
            @click="showAddStatusForm = true"
          >
            {{ Translator.trans('permission.new') }}
          </button>

          <div
            v-if="showAddStatusForm"
            class="layout"
          >
            <!-- Select row  -->
            <div class="layout__item u-1-of-4">
              <select
                v-model="statusForm.type"
                class="u-1-of-1"
                :title="Translator.trans('organisation.type')"
                data-cy="orgaFormField:organisationType"
              >
                <option
                  v-for="type in availableRegistrationTypes"
                  :key="type.value"
                  :value="type.value"
                >
                  {{ Translator.trans(type.label) }}
                </option>
              </select>
            </div><!--
         --><div class="layout__item u-1-of-4">
              <select
                v-model="statusForm.status"
                class="u-1-of-1"
                :title="Translator.trans('permission.status')"
                data-cy="orgaFormField:permissionStatus"
              >
                <option
                  v-for="typeStatus in typeStatuses"
                  :key="typeStatus.value"
                  :value="typeStatus.value"
                >
                  {{ typeStatus.label }}
                </option>
              </select>
            </div>

            <!-- Button row  -->
            <div class="layout__item u-1-of-2 u-mt-0_5 space-inline-m">
              <button
                class="btn btn--primary"
                data-cy="orgaFormField:saveNewRegistrationStatus"
                type="button"
                @click="saveNewRegistrationStatus"
              >
                {{ Translator.trans('permission.add') }}
              </button><!--
           --><button
                v-if="registrationStatuses.length > 0"
                class="btn btn--secondary"
                type="button"
                @click="resetRegistrationStatus"
              >
                {{ Translator.trans('abort') }}
              </button>
            </div>
          </div>
        </template>

        <dp-checkbox
          v-if="hasPermission('feature_manage_procedure_creation_permission')"
          :id="`${organisation.id}:procedureCreatePermission`"
          v-model="localOrganisation.attributes.canCreateProcedures"
          class="mt-2"
          data-cy="orgaFormField:procedureCreatePermission"
          :label="{
            text: Translator.trans('procedure.canCreate'),
            bold: true
          }"
          @change="emitOrganisationUpdate"
        />
      </div>

      <div
        v-if="canEdit('cssvars') && hasPermission('feature_orga_branding_edit')"
        class="layout__item u-1-of-1 u-mt"
      >
        <legend class="u-pb-0_5">
          {{ Translator.trans('branding.label') }}
        </legend>
        <dp-text-area
          :id="`${organisation.id}:cssvars`"
          v-model="localOrganisation.attributes.cssvars"
          data-cy="orgaFormField:styling"
          :hint="Translator.trans('branding.styling.hint')"
          :label="Translator.trans('branding.styling.input')"
          reduced-height
          @input="emitOrganisationUpdate"
        />
        <dp-details
          data-cy="organisationFormFields:brandingStyling"
          :summary="Translator.trans('branding.styling.details')"
        >
          <span v-html="Translator.trans('branding.styling.details.description')" />
        </dp-details>
      </div>

      <!-- Orga slug -->
      <div
        v-if="hasPermission('feature_orga_slug_edit') && canEdit('slug')"
        class="layout__item u-mt"
      >
        <label
          :for="organisation.id + ':slug'"
          class="u-mb-0_25"
        >
          {{ Translator.trans('organisation.procedurelist.slug') }}
        </label>
        <p class="lbl__hint">
          {{ Translator.trans('organisation.procedurelist.slug.explanation') }}
        </p>
        <input
          v-if="canEdit('slug')"
          :id="organisation.id + ':slug'"
          class="w-full inline u-mb-0_5 u-1-of-3 organisationSlug"
          style="height: 27px;"
          type="text"
          :data-organisation-id="organisation.id"
          data-slug
          :value="organisationSlug"
        >

        <div>
          <strong v-if="canEdit('slug')">{{ Translator.trans('preview') }}:</strong>
          <p
            :id="organisation.id + ':urlPreview'"
            :data-shorturl="proceduresDirectLinkPrefix + '/'"
          >
            {{ proceduresDirectLinkPrefix }}/{{ organisationSlug }}
          </p>
        </div>
      </div>

      <div
        v-if="organisationId !== ''"
        class="layout__item u-mt"
      >
        <label class="u-mb-0_25">
          {{ Translator.trans('customer', { count: hasOwnProp(organisation.relationships, 'customers') ? organisation.relationships.customers.data.length : 0 }) }}
        </label>
        <p
          v-if="hasOwnProp(organisation.relationships,'customers') && organisation.relationships.customers.data.length !== 0"
          class="color--grey"
        >
          {{ customers }}
        </p>
        <p
          v-else
          class="u-p-0 color--grey"
        >
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
          :checked="localOrganisation.attributes.submissionType === submissionTypeShort"
          :disabled="false === canEdit('submissionType')"
          @input="emitOrganisationUpdate"
        >
        {{ Translator.trans('statement.submission.shorthand') }}

        <p class="u-ml-0_75 weight--normal">
          {{ Translator.trans('explanation.statement.submit.process.short') }}
        </p>
      </label>

      <label class="u-mb-0_25">
        <input
          type="radio"
          :value="submissionTypeDefault"
          :checked="localOrganisation.attributes.submissionType === submissionTypeDefault"
          :disabled="false === canEdit('submissionType')"
          @input="emitOrganisationUpdate"
        >
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
            class="u-mb-0_25"
          >
            {{ Translator.trans('email.participation') }}
            <p
              v-cleanhtml="Translator.trans('explanation.organisation.email.participation')"
              class="weight--normal lbl__hint"
            />
          </label>
          <input
            v-if="canEdit('email2')"
            :id="organisation.id + ':email2'"
            v-model="localOrganisation.attributes.email2"
            class="layout__item u-1-of-2 u-mb-0_5 u-pr-0_5"
            style="height: 27px;"
            data-cy="orgaFormField:emailParticipation"
            type="email"
            @input="emitOrganisationUpdate"
          >
        </div>

        <div v-else-if="false === canEdit('email2')">
          <strong
            class="u-mb-0_25"
          >
            {{ Translator.trans('email.participation') }}
          </strong>
          <p
            v-if="organisation.attributes.email2 !== ''"
            class="color--grey"
          >
            {{ organisation.attributes.email2 }}
          </p>
          <p
            v-if="organisation.attributes.email2 === ''"
            class="color--grey"
          >
            -
          </p>
        </div>

        <div v-if="hasPermission('field_organisation_email2_cc')">
          <div v-if="canEdit('ccEmail2')">
            <label>
              {{ Translator.trans('email.cc.participation') }}
              <p
                v-cleanhtml="Translator.trans('explanation.organisation.email.cc')"
                class="weight--normal lbl__hint"
              />
              <input
                v-model="localOrganisation.attributes.ccEmail2"
                class="layout__item u-1-of-2"
                style="height: 27px;"
                type="email"
                data-cy="orgaFormField:emailCC"
                @input="emitOrganisationUpdate"
              >
            </label>
          </div>

          <div v-else-if="false === canEdit('ccEmail2')">
            <label>
              {{ Translator.trans('email.cc.participation') }}
              <p
                v-if="organisation.attributes.ccEmail2 !== ''"
                class="color--grey"
              >
                {{ organisation.attributes.ccEmail2 }}
              </p>
              <p
                v-else-if="organisation.attributes.ccEmail2 === ''"
                class="color--grey"
              >
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
            v-cleanhtml="Translator.trans('explanation.organisation.email.reviewer.admin')"
            class="weight--normal"
          />
          <input
            v-model="localOrganisation.attributes.reviewerEmail"
            class="layout__item u-1-of-2"
            style="height: 27px;"
            type="email"
            @input="emitOrganisationUpdate"
          >
        </label>
      </div>

      <!-- show 'notification for new statements' & 'notification for ending procedure' fields only if editable -->
      <div
        v-if="hasPermission('feature_orga_edit_all_fields') && (hasPermission('feature_notification_statement_new') || hasPermission('feature_notification_ending_phase'))"
        class="u-mt-0_5"
      >
        <strong class="u-mb-0_25">
          {{ Translator.trans('email.notifications') }}
        </strong>
        <div v-if="hasPermission('feature_notification_statement_new') && showNewStatementNotification">
          <label
            class="weight--normal u-mb-0_25"
          >
            <input
              v-model="localOrganisation.attributes.emailNotificationNewStatement"
              type="checkbox"
              :name="organisation.id + ':emailNotificationNewStatement'"
              @change="emitOrganisationUpdate"
            >
            {{ Translator.trans('explanation.notification.new.statement') }}
          </label>
        </div>

        <div v-if="hasPermission('feature_notification_ending_phase')">
          <label class="weight--normal u-mb-0_25">
            <input
              v-model="localOrganisation.attributes.emailNotificationEndingPhase"
              type="checkbox"
              :name="organisation.id + ':emailNotificationEndingPhase'"
              data-cy="orgaFormField:notificationEndingPhase"
              @change="emitOrganisationUpdate"
            >
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
        <dp-select
          v-model="localOrganisation.attributes.copy"
          :classes="'w-fit'"
          :label="{
            text: Translator.trans('quantity'),
            hint: Translator.trans('explanation.organisation.copies.paper')
          }"
          :options="paperCopyCountOptions"
          :show-placeholder="false"
          data-cy="orgaFormField:organisationCopiesPaper"
          @select="emitOrganisationUpdate"
        />
      </div>

      <label v-if="hasPermission('field_organisation_paper_copy_spec') && canEdit('paperCopySpec')">
        {{ Translator.trans('copies.kind') }}
        <p
          v-cleanhtml="Translator.trans('explanation.organisation.copies.kind')"
          class="font-size-6 weight--normal"
        />

        <textarea
          v-model="localOrganisation.attributes.copySpec"
          class="h-9"
          data-cy="orgaFormField:copiesKind"
          @input="emitOrganisationUpdate"
        />
      </label>
    </fieldset>

    <label v-if="hasPermission('field_organisation_competence') && canEdit('competence')">
      {{ Translator.trans('competence.explanation') }}
      <p
        class="font-size-6 weight--normal"
      >
        {{ Translator.trans('explanation.organisation.competence') }}
      </p>

      <textarea
        v-model="localOrganisation.attributes.competence"
        class="h-9"
        data-cy="orgaFormField:competence"
        @input="emitOrganisationUpdate"
      />
    </label>

    <div
      v-else-if="hasPermission('field_organisation_competence') && false === canEdit('competence')"
      class="u-mt-0"
    >
      <strong>
        {{ Translator.trans('competence.explanation') }}
      </strong>
      <p
        class="font-size-6 weight--normal"
      >
        {{ Translator.trans('explanation.organisation.competence.extern') }}
      </p>
      <p
        v-if="localOrganisation.attributes.competence !== ''"
        class="color--grey weight--normal"
      >
        {{ localOrganisation.attributes.competence }}
      </p>
      <p
        v-else-if="localOrganisation.attributes.competence === ''"
        class="color--grey weight--normal"
      >
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
            v-model="localOrganisation.attributes.showlist"
            type="checkbox"
            data-cy="orgaFormField:listShow"
            :name="organisation.id + ':showlist'"
            :disabled="false === canEdit('showlist')"
            @change="emitOrganisationUpdate"
          >
          {{ Translator.trans('invitable_institution.list.show.text') }}
        </label>
        <label
          v-if="canEdit('showlistChangeReason') && hasChanged('showlist') && typeof initialOrganisation.attributes !== 'undefined'"
          class="u-ml u-mb-0"
        >
          <span class="inline-block weight--normal u-mb-0_25">
            {{ Translator.trans('reason.change') }}*
          </span>
          <textarea
            v-model="localOrganisation.attributes.showlistChangeReason"
            class="h-9"
            data-cy="orgaFormField:listShowChange"
            :name="organisation.id + ':showlistChangeReason'"
            required
            @input="emitOrganisationUpdate"
          />
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
              v-model="localOrganisation.attributes.showname"
              type="checkbox"
              data-cy="orgaFormField:agree"
              :name="organisation.id + ':showname'"
              @change="emitOrganisationUpdate"
            >
            {{ Translator.trans('agree.publication.text') }}
          </label>
        </div>
        <div v-else>
          <p class="font-size-6">
            {{ Translator.trans('agree.publication.explanation.extern', { projectName: projectName }) }}
          </p>
          <p
            v-if="localOrganisation.attributes.showname === true"
            class="color--grey"
          >
            {{ Translator.trans('organisation.publication.agreed', { organisation: organisation.attributes.name }) }}
          </p>
          <p
            v-else-if="localOrganisation.attributes.showname === false"
            class="color--grey"
          >
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
        v-model="localOrganisation.attributes.imprint"
        data-cy="orgaFormField:imprint"
        :toolbar-items="{
          fullscreenButton: true,
          headings: [2,3,4],
          linkButton: true
        }"
        @input="emitOrganisationUpdate"
      />
    </fieldset>

    <!-- Data Protection -->
    <fieldset v-if="hasPermission('field_data_protection_text_customized_edit_orga') && organisation.attributes.isPlanningOrganisation === true">
      <legend class="layout__item u-p-0 u-pb-0_5">
        {{ Translator.trans('data.protection.organisations') }}
      </legend>
      <dp-editor
        v-model="localOrganisation.attributes.dataProtection"
        data-cy="orgaFormField:dataProtection"
        :toolbar-items="{
          fullscreenButton: true,
          headings: [2,3,4],
          linkButton: true
        }"
        @input="emitOrganisationUpdate"
      />
    </fieldset>
  </div>
</template>

<script>
import { CleanHtml, DpCheckbox, DpDetails, DpEditor, DpSelect, DpTextArea, hasOwnProp } from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'

export default {
  name: 'DpOrganisationFormFields',

  components: {
    AddonWrapper,
    DpCheckbox,
    DpDetails,
    DpEditor,
    DpTextArea,
    DpSelect,
  },

  inject: [
    'subdomain',
    'submissionTypeDefault',
    'submissionTypeShort',
    'showNewStatementNotification',
    'proceduresDirectLinkPrefix',
    'projectName',
    'writableFields',
  ],

  directives: {
    cleanhtml: CleanHtml,
  },

  props: {
    additionalFieldOptions: {
      type: Array,
      required: false,
      default: () => [],
    },

    availableOrgaTypes: {
      type: Array,
      required: true,
    },

    initialOrganisation: {
      type: Object,
      required: false,
      default: () => { return {} },
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
            type: '',
          },
          relationships: {
            currentSlug: {
              data: {
                id: '',
                type: 'Slug',
              },
            },
            customers: {
              data: [
                {
                  id: '',
                  type: 'Customer',
                },
              ],
            },
            departments: {
              data: [
                {
                  id: '',
                  type: 'Department',
                },
              ],
            },
          },
        }
      },
    },

    organisationId: {
      type: String,
      required: false,
      default: '',
    },

    triggerReset: {
      type: Boolean,
      required: false,
      default: false,
    },
  },

  emits: [
    'addon:update',
    'addonOptions:loaded',
    'organisation:update',
    'reset:complete',
  ],

  data () {
    return {
      localOrganisation: {},
      typeStatuses: [
        {
          value: 'accepted',
          label: Translator.trans('enabled'),
        },
        {
          value: 'pending',
          label: Translator.trans('requested'),
        },
        {
          value: 'rejected',
          label: Translator.trans('rejected'),
        },
      ],
      showAddStatusForm: false,
      statusForm: {
        status: 'pending',
        type: 'TöB',
      },
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
     * @return {Array <{value: number, label: string}>} for 0-10
     */
    paperCopyCountOptions () {
      return Array.from({ length: 11 }, (_, i) => ({ value: i, label: String(i) }))
    },

    registrationStatuses () {
      const registrationStatuses = hasOwnProp(this.localOrganisation.attributes, 'registrationStatuses') ?
        Object.values(this.localOrganisation.attributes.registrationStatuses).filter(el => el.subdomain === this.subdomain) :
        []

      return (this.canEdit('registrationStatuses') || hasPermission('area_organisations_applications_manage')) ?
        registrationStatuses :
        []
    },
  },

  watch: {
    organisation: {
      handler () {
        this.setInitialOrganisation()
      },
      deep: true,
    },

    triggerReset (shouldReset) {
      if (shouldReset) {
        this.setInitialOrganisation()
        this.$emit('reset:complete')
      }
    },
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
        this.$emit('organisation:update', this.localOrganisation)
      })
    },

    hasChanged (field) {
      if (this.initialOrganisation.attributes !== 'undefined') {
        return hasOwnProp(this.initialOrganisation.attributes, field) ?
          this.localOrganisation.attributes[field] !== this.initialOrganisation.attributes[field] :
          false
      }
      return false
    },

    hasOwnProp (obj, prop) {
      return hasOwnProp(obj, prop)
    },

    resetRegistrationStatus () {
      this.statusForm = {
        status: 'pending',
        type: (this.availableRegistrationTypes.length > 0) ? this.availableRegistrationTypes[0].value : '',
      }
      this.showAddStatusForm = false
    },

    registrationStatusLabel (status) {
      return this.typeStatuses.find(type => type.value === status).label
    },

    registrationTypeLabel (type) {
      const orgaType = this.availableOrgaTypes.find(el => el.value === type)

      return orgaType ? Translator.trans(orgaType.label) : type
    },

    saveNewRegistrationStatus () {
      // Update the local organisation state
      this.localOrganisation.attributes.registrationStatuses.push({
        type: this.statusForm.type,
        status: this.statusForm.status,
        subdomain: this.subdomain,
      })
      this.emitOrganisationUpdate()
      this.resetRegistrationStatus()
    },

    setInitialOrganisation () {
      this.localOrganisation = JSON.parse(JSON.stringify(this.organisation))

      if (this.organisation && typeof this.organisation.hasRelationship === 'function' && this.organisation.hasRelationship('branding')) {
        this.localOrganisation.attributes.cssvars = this.organisation.rel('branding').attributes.cssvars
      }
    },

    setAdditionalFieldOptions (options) {
      this.$emit('addonOptions:loaded', options)
    },

    updateAddonPayload (payload) {
      this.$emit('addon:update', payload)
    },
  },

  created () {
    this.setInitialOrganisation()
  },

  mounted () {
    if (this.registrationStatuses.length === 0) {
      this.showAddStatusForm = true
    }
    this.statusForm.type = this.availableRegistrationTypes.length > 0 ? this.availableRegistrationTypes[0].value : ''
  },
}
</script>
