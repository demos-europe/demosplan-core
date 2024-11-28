<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-modal
      ref="statementModal"
      @modal:toggled="handleModalToggle"
      content-header-classes="border--none">
      <template
        v-if="showHeader"
        v-slot:header>
        <span :class="prefixClass('color-highlight')">
          <i
            aria-hidden="true"
            class="fa"
            :class="commentingIcon" />
          {{ Translator.trans('statement.yours') }}
        </span>
      </template>
      <template v-slot:closeButton>
        <button
          aria-describedby="statementDialogCloseTitle"
          :aria-label="Translator.trans('statement.dialog.close')"
          :class="prefixClass('c-statement__close btn-icns color-highlight u-m-0_25 p-0 absolute u-right-0')"
          :title="Translator.trans('statement.dialog.close')"
          type="button"
          @click="toggleModal(false)">
          <span
            id="statementDialogCloseTitle"
            :class="prefixClass('sr-only')">
            {{ Translator.trans('explanation.statement.autosave') }}
          </span>
          <i
            aria-hidden="true"
            :class="prefixClass('fa fa-compress fa-2x')" />
        </button>
      </template>

      <header
        v-if="loggedIn === false && showHeader"
        role="banner"
        :class="prefixClass('c-statement__header u-mb-0_5')">
        <dp-multistep-nav
          :active-step="step"
          :class="prefixClass('pb-0')"
          :steps="[{
            label: Translator.trans('statement.yours'),
            icon: commentingIcon,
            title: Translator.trans('statement.modal.step.write')
          }, {
            label: Translator.trans('personal.data'),
            icon: 'fa-user',
            title: Translator.trans('statement.modal.step.personal.data')
          }, {
            label: Translator.trans('recheck'),
            icon: 'fa-check',
            title: Translator.trans('statement.modal.step.recheck')
          }]"
          @change-step="val => step = val" />
      </header>

      <dp-inline-notification
        dismissible
        dismissible-key="statementModalCloseExplanation"
        :message="Translator.trans('explanation.statement.autosave')"
        type="info" />

      <!-- Statement form incl. documents and location -->
      <section
        v-show="step === 0"
        data-dp-validate="statementForm">
        <dp-inline-notification
          v-if="loggedIn === false"
          type="info">
          <p
            v-if="statementFormHintStatement"
            v-cleanhtml="statementFormHintStatement" />
          <p v-cleanhtml="Translator.trans('statement.modal.step.write.privacy_policy')" />
          <p>{{ Translator.trans('error.mandatoryfields') }}</p>
        </dp-inline-notification>

        <dp-inline-notification
          v-if="dpValidate.statementForm === false"
          id="statementFormErrors"
          aria-labelledby="statementFormErrorsContent"
          tabindex="0">
          <p
            id="statementFormErrorsContent"
            v-cleanhtml="createErrorMessage('statementForm')" />
        </dp-inline-notification>

        <div
          v-if="loggedIn && hasPermission('feature_elements_use_negative_report') && planningDocumentsHasNegativeStatement"
          class="flex mt-4">
          <dp-radio
            name="r_isNegativeReport"
            id="negative_report_false"
            data-cy="statementModal:publicParticipationParticipate"
            class="u-mr-2"
            :checked="formData.r_isNegativeReport === '0'"
            @change="() => { setStatementData({ r_isNegativeReport: '0'}) }"
            :label="{
              text: Translator.trans('public.participation.participate')
            }"
            value="0" />
          <dp-radio
            :disabled="canNotBeNegativeReport"
            name="r_isNegativeReport"
            id="negative_report_true"
            data-cy="statementModal:indicationerror"
            :checked="formData.r_isNegativeReport === '1'"
            @change="() => { setStatementData({ r_isNegativeReport: '1'}) }"
            :label="{
              hint: Translator.trans('link.title.indicationerror'),
              text: Translator.trans('indicationerror')
            }"
            value="1" />
        </div>

        <div :class="prefixClass('c-statement__text')">
          <dp-label
            :text="Translator.trans('statement.detail.form.statement_text')"
            for="statementText"
            :required="formData.r_isNegativeReport !== '1'" />
          <dp-editor
            :class="prefixClass('u-mb')"
            :data-dp-validate-error-fieldname="Translator.trans('statement.text.short')"
            hidden-input="r_text"
            id="statementText"
            ref="statementEditor"
            :readonly="formData.r_isNegativeReport === '1'"
            :required="formData.r_isNegativeReport !== '1'"
            :toolbar-items="{
              mark: true,
              strikethrough: true
            }"
            :value="formData.r_text || ''"
            @input="val => setStatementData({r_text: val})" />
        </div>
        <div
          v-if="loggedIn === false"
          :class="prefixClass('u-mb')">
          <dp-checkbox
            id="confirmPrivacy"
            :checked="formData.r_privacy === 'on'"
            data-cy="privacyCheck"
            :data-dp-validate-error-fieldname="Translator.trans('confirm.statement.privacy')"
            :label="{
              text: Translator.trans('explanation.statement.privacy')
            }"
            name="r_privacy"
            required
            @change="val => setStatementData({r_privacy: val ? 'on' : 'off'})" />
        </div>
        <div
          :class="prefixClass('u-mb')">
          <dp-checkbox
            v-if="hasPermission('field_statement_public_allowed') && publicParticipationPublicationEnabled && hasPermission('feature_statement_public_allowed_needs_verification')"
            id="r_makePublic"
            data-cy="make_public"
            :checked="formData.r_makePublic === 'on'"
            :label="{
              text: makePublicLabel
            }"
            name="r_makePublic"
            @change="val => setStatementData({r_makePublic: val ? 'on' : 'off'})" />
        </div>

        <template v-if="hasPermission('field_statement_add_assignment') && hasPlanningDocuments">
          <fieldset>
            <legend :class="prefixClass('c-statement__formblock-title')">
              {{ Translator.trans('element.assigned') }}
            </legend>

            <button
              v-if="formData.r_element_id === ''"
              aria-labelledby="documentReference"
              :class="prefixClass('btn--blank o-link--default text-left')"
              data-cy="statementModal:elementAssign"
              :disabled="formData.r_isNegativeReport !== '0'"
              @click="gotoTab('procedureDetailsDocumentlist')">
              <i
                aria-hidden="true"
                :class="prefixClass('fa fa-plus')" />
              {{ Translator.trans('element.assign') }}
            </button>

            <div
              v-if="formData.r_element_id !== ''"
              :class="prefixClass('mb-3')">
              <button
                aria-labelledby="documentReference"
                :class="prefixClass('btn--blank o-link--default u-mr-0_5-lap-up w-fit')"
                @click="gotoTab('procedureDetailsDocumentlist')">
                <i
                  aria-hidden="true"
                  :class="prefixClass('fa fa-pencil')" />
                {{ Translator.trans('document.reference.change') }}
              </button>

              <button
                aria-labelledby="documentReference"
                :class="prefixClass('btn--blank o-link--default u-mr-0_5-lap-up w-fit')"
                :href="Routing.generate( 'DemosPlan_procedure_public_detail', { procedure: procedureId }) + '#procedureDetailsDocumentlist'"
                @click="removeDocumentRelation">
                <i
                  aria-hidden="true"
                  :class="prefixClass('fa fa-trash')" />
                {{ Translator.trans('document.reference.delete') }}
              </button>
            </div>

            <dl
              v-if="formData.r_element_id !== ''"
              :class="[highlighted.documents ? prefixClass('animation--bg-highlight-grey--light-2 space-y-2') : prefixClass('bg-color--grey-light-2'), 'mb-1 py-1 px-2']">
              <div :class="prefixClass('md:flex')">
                <dt :class="prefixClass('font-semibold w-1/6')">
                  {{ Translator.trans('document') }}:
                </dt>
                <dd :class="prefixClass('ml-0')">
                  {{ formData.r_element_title }}
                </dd>
              </div>

              <div
                v-if="formData.r_paragraph_id !== ''"
                :class="prefixClass('md:flex')">
                <dt :class="prefixClass('font-semibold w-1/6')">
                  {{ Translator.trans('paragraph') }}:
                </dt>
                <dd :class="prefixClass('ml-0')">
                  {{ formData.r_paragraph_title }}
                </dd>
              </div>

              <div
                v-if="formData.r_document_id !== ''"
                :class="prefixClass('md:flex')">
                <dt :class="prefixClass('font-semibold w-1/6')">
                  {{ Translator.trans('file') }}:
                </dt>
                <dd :class="prefixClass('ml-0')">
                  {{ formData.r_document_title }}
                </dd>
              </div>
            </dl>
          </fieldset>
        </template>

        <!-- location reference -->
        <template v-if="(isMapEnabled && hasPermission('area_map_participation_area')) || hasPermission('field_statement_location')">
          <component
            v-for="formDefinition in statementFormDefinitions"
            :is="formDefinition.component"
            :key="formDefinition.key"
            :draft-statement-id="draftStatementId"
            :is-map-enabled="isMapEnabled"
            :disabled="formData.r_isNegativeReport !== '0'"
            :required="formDefinition.required && formData.r_isNegativeReport !== '1'"
            :logged-in="loggedIn"
            :counties="counties" />
        </template>

        <template v-if="loggedIn">
          <fieldset>
            <legend
              class="sr-only"
              v-text="Translator.trans('files.upload')" />
            <div
              v-if="hasPermission('field_statement_file')"
              :class="prefixClass('u-mb-0_25 layout')">
              <div
                v-if="initialFiles.length > 0"
                :class="prefixClass('layout__item u-1-of-2')">
                <p
                  :class="prefixClass('weight--bold u-mb-0_25')">
                  {{ Translator.trans('attachments') }}
                </p>

                <div
                  v-for="(file, idx ) in initialFiles"
                  :key="`file_${idx}`"
                  :class="prefixClass('o-hellip')">
                  <a
                    :class="prefixClass('align-top')"
                    :href="Routing.generate('core_file_procedure', { hash: file.hash, procedureId: procedureId })"
                    rel="noopener"
                    target="_blank">
                    {{ file.name }}
                  </a>
                  <label :class="prefixClass('lbl--text float-right')">
                    <input
                      :value="file.hash"
                      name="delete_file[]"
                      @change="() => updateDeleteFile(file.hash)"
                      type="checkbox">
                    {{ Translator.trans('attachment.delete') }}
                  </label>
                </div>
              </div><!--
           --><div :class="[prefixClass(initialFiles.length === 0 ? 'u-1-of-1' : 'u-1-of-2'), prefixClass('layout__item u-mb')]">
                <dp-label
                  :class="prefixClass('mb-2')"
                  :text="Translator.trans('upload.files')"
                  for="r_file" />

                <dp-upload-files
                  id="upload_files"
                  :disabled="formData.r_isNegativeReport !== '0'"
                  allowed-file-types="pdf-img-zip"
                  :basic-auth="dplan.settings.basicAuth"
                  :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
                  :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
                  :max-number-of-files="20"
                  ref="uploadFiles"
                  :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
                  :tus-endpoint="dplan.paths.tusEndpoint"
                  :storage-name="fileStorageName"
                  @file-remove="removeUnsavedFile"
                  @upload-success="addUnsavedFile" />
              </div>
            </div>
            <div
              v-if="hasPermission('feature_statements_represent_orga')"
              :class="prefixClass('layout')">
              <dp-input
                id="r_represents"
                :class="prefixClass('layout__item md:w-1/2')"
                :label="{
                  text: Translator.trans('statement.representation.creation')
                }"
                name="r_represents"
                :placeholder="Translator.trans('institution.represents')"
                :value="formData.r_represents"
                @input="val => setStatementData({r_represents: val})" />
            </div>
          </fieldset>
        </template>
        <div
          v-if="loggedIn"
          :class="prefixClass('text-right sm:text-center md:text-right mb-2 flow-root')">
          <!-- Logged in, existing draft statement -->
          <dp-loading
            v-if="isLoading"
            :class="prefixClass('align-text-bottom inline-block')"
            hide-label />
          <button
            v-if="displayEditSubmit"
            type="submit"
            :disabled="isLoading"
            :class="prefixClass('btn btn--primary u-1-of-1-palm u-1-of-2-lap u-mt-0_5-palm')"
            @click="sendStatement"
            data-cy="saveChangedStatement">
            {{ Translator.trans('save.and.close') }}
          </button>
          <button
            v-if="displayEditSubmit"
            type="submit"
            :disabled="isLoading"
            :class="prefixClass('btn btn--secondary u-1-of-1-palm u-1-of-2-lap u-mt-0_5-palm u-ml-0_5-desk-up')"
            @click="e => sendStatement(e,false, true)"
            data-cy="saveChangedStatementWothoutClosing">
            {{ Translator.trans('save') }}
          </button>

          <!-- logged in, new draft statement -->
          <template v-else>
            <button
              v-if="hasPermission('feature_draft_statement_citizen_immediate_submit') && draftStatementId === ''"
              type="submit"
              :disabled="isLoading"
              data-cy="statementModal:statementSaveImmediate"
              @click="e => sendStatement(e,true)"
              :class="prefixClass('btn btn--primary u-1-of-1-palm u-1-of-2-lap u-mt-0_5-lap-down')">
              {{ Translator.trans('statement.save.immediate') }}
            </button>
            <button
              type="submit"
              :disabled="isLoading"
              :class="[
                hasPermission('feature_draft_statement_citizen_immediate_submit') ? prefixClass('btn--secondary') : prefixClass('btn--primary'),
                prefixClass('btn u-1-of-1-palm u-1-of-2-lap u-mt-0_5-lap-down u-ml-0_5-desk-up')
              ]"
              @click="sendStatement"
              data-cy="statementModal:saveAsDraft">
              <template v-if="draftStatementId === ''">
                {{ Translator.trans('statement.save.as.draft') }}
              </template>
              <template v-else>
                {{ Translator.trans('statement.save.altered') }}
              </template>
            </button>
          </template>
          <button
            type="reset"
            data-cy="statementModal:discardChanges"
            :disabled="isLoading"
            :class="prefixClass('btn btn--secondary u-1-of-1-palm u-1-of-2-lap u-mt-0_5-lap-down u-ml-0_5-desk-up')"
            @click.prevent="() => reset()">
            {{ Translator.trans('discard.changes') }}
          </button>
        </div>
        <!-- for not logged in users -->
        <div
          v-else
          :class="prefixClass('text-right sm:text-center md:text-right mb-2')">
          <dp-loading
            v-if="isLoading"
            :class="prefixClass('align-text-bottom inline-block')"
            hide-label />
          <button
            type="reset"
            :disabled="isLoading"
            :class="prefixClass('btn btn--secondary u-1-of-1-palm u-1-of-2-lap')"
            data-cy="statementModal:discardStatement"
            @click.prevent="() => reset()">
            {{ Translator.trans('discard.statement') }}
          </button>
          <button
            type="submit"
            data-cy="statementFormSubmit"
            :disabled="isLoading"
            :class="prefixClass('btn btn--primary u-1-of-1-palm u-1-of-2-lap u-mt-0_5-lap-down u-ml-0_5-desk-up')"
            form-name="statementForm"
            @click="validateStatementStep">
            {{ Translator.trans('continue.personal_data') }}
          </button>
        </div>
      </section>

      <!-- Personal data step -->
      <form
        autocomplete="on"
        v-show="step === 1"
        data-dp-validate="submitterForm">
        <dp-inline-notification
          type="info">
          <p
            v-if="statementFormHintPersonalData"
            v-cleanhtml="statementFormHintPersonalData" />
          <p>
            {{ Translator.trans('error.mandatoryfields') }}
          </p>
          <p v-if="extraPersonalHint !== ''">
            {{ extraPersonalHint }}
          </p>
        </dp-inline-notification>

        <div
          v-show="dpValidate.submitterForm === false"
          id="submitterFormErrors"
          tabindex="0"
          aria-labelledby="submitterFormErrorsContent"
          :class="prefixClass('c-statement__formhint flash-error mb-2')">
          <i
            aria-hidden="true"
            :class="prefixClass('c-statement__hint-icon fa fa-lg fa-exclamation-circle')" />
          <div
            id="submitterFormErrorsContent"
            :class="prefixClass('ml-4')"
            v-cleanhtml="createErrorMessage('submitterForm')" />
        </div>

        <fieldset
          aria-required="true"
          :class="prefixClass('mt-5')"
          id="personalInfoFieldset"
          role="radiogroup"
          required>
          <div
            aria-live="polite"
            aria-relevant="all"
            :class="[
              formData.r_useName === '1' ? prefixClass('bg-color--grey-light-2') : '',
              prefixClass('c-statement__formblock')
            ]"
            aria-labelledby="statement-detail-post-publicly">
            <dp-radio
              :checked="formData.r_useName === '1'"
              :class="prefixClass('mb-1')"
              data-cy="submitPublicly"
              id="r_useName_1"
              :label="{
                text: Translator.trans('statement.detail.form.personal.post_publicly')
              }"
              name="r_useName"
              value="1"
              @change="val => setPrivacyPreference({r_useName: '1'})" />
            <div
              v-show="formData.r_useName === '1'"
              :class="prefixClass('layout mb-3 ml-2')">
              <component
                v-for="formDefinition in personalDataFormDefinitions"
                :is="formDefinition.component"
                :draft-statement-id="draftStatementId"
                :required="formDefinition.required"
                :form-options="formOptions"
                :class="prefixClass('layout__item u-1-of-1-palm mt-1 ' + formDefinition.width)"
                :key="formDefinition.key" />
            </div>
          </div>
          <div
            :class="[
              formData.r_useName === '0' ? prefixClass('bg-color--grey-light-2') : '',
              prefixClass('c-statement__formblock')
            ]">
            <dp-radio
              aria-labelledby="statement-detail-post-anonymously"
              :checked="formData.r_useName === '0'"
              data-cy="submitAnonymously"
              id="r_useName_0"
              :label="{
                text: Translator.trans('statement.detail.form.personal.post_anonymously')
              }"
              name="r_useName"
              value="0"
              @change="val => setPrivacyPreference({r_useName: '0'})" />
          </div>
        </fieldset>

        <component
          v-for="formDefinition in statementFeedbackDefinitions"
          :is="formDefinition.component"
          :key="formDefinition.key"
          :draft-statement-id="draftStatementId"
          :required="formDefinition.required" />
        <div :class="prefixClass('text-right mt-3')">
          <button
            type="button"
            data-cy="submitterForm"
            :class="prefixClass('btn btn--primary')"
            form-name="submitterForm"
            @click="dpValidateAction('submitterForm', validatePersonalDataStep, true)">
            {{ Translator.trans('continue.submission') }}
          </button>
        </div>
      </form>

      <!-- recheck -->
      <section
        v-show="step === 2"
        data-dp-validate="recheckForm">
        <statement-modal-recheck
          @edit-input="handleEditInput"
          :form-fields="formFields"
          :statement="formData"
          :public-participation-publication-enabled="publicParticipationPublicationEnabled"
          :statement-feedback-definitions="statementFeedbackDefinitions"
          :statement-form-hint-recheck="statementFormHintRecheck" />

        <label
          v-if="hasPermission('feature_statement_data_protection')"
          :class="prefixClass('u-mb-0 weight--normal')"
          id="data_protection_label"
          :title="Translator.trans('statements.required.field')">
          <input
            type="checkbox"
            id="data_protection"
            name="r_data_protection"
            required
            aria-labelledby="explanation-statement-data-protection">
          <span
            id="explanation-statement-data-protection"
            aria-hidden="true">
            {{ Translator.trans('explanation.statement.data.protection') }}
            <a
              :aria-label="Translator.trans('data.protection.more')"
              :class="prefixClass('o-link--default')"
              :href="Routing.generate('DemosPlan_misccontent_static_dataprotection')"
              rel="noopener"
              target="_blank">
              {{ Translator.trans('data.protection.more') }}
            </a>
            <span aria-hidden="true">*</span>
          </span>
        </label>

        <dp-checkbox
          v-if="hasPermission('feature_statement_gdpr_consent_submit')"
          id="gdpr_consent"
          :checked="formData.r_gdpr_consent === 'on'"
          :class="prefixClass('u-mv-0_5')"
          data-cy="gdprCheck"
          :data-dp-validate-error-fieldname="Translator.trans('confirm.statement.data_protection')"
          :label="{
            text: Translator.trans('confirm.gdpr.consent', { link: Routing.generate('DemosPlan_misccontent_static_dataprotection'), orgaId: orgaId })
          }"
          name="r_gdpr_consent"
          required
          @change="val => setStatementData({r_gdpr_consent: val ? 'on' : 'off'})" />

        <div :class="prefixClass('text-right')">
          <dp-loading
            v-if="isLoading"
            :class="prefixClass('align-text-bottom inline-block')"
            hide-label />
          <button
            type="button"
            data-cy="sendStatementNow"
            :disabled="isLoading"
            :class="prefixClass('btn btn--primary')"
            @click.prevent="e => dpValidateAction('recheckForm', () => sendStatement(e))">
            {{ Translator.trans('statement.submit.now') }}
          </button>
        </div>
      </section>
      <section v-show="step === 3">
        <div
          v-if="responseHtml !== ''"
          v-cleanhtml="responseHtml" />
        <template v-else>
          <h2
            :class="prefixClass('color-highlight')"
            id="statementModalTitle"
            data-title="confirmation"
            aria-describedby="successConfirmation">
            <i
              :class="prefixClass('fa fa-comment')"
              aria-hidden="true" />
            {{ Translator.trans('participation.thank.you') }}
          </h2>

          <span
            id="successConfirmation"
            :class="prefixClass('u-mb')">
            <p :class="prefixClass('flash-confirm c-statement__formhint')">
              <i
                :class="prefixClass('fa fa-check fa-lg')"
                aria-hidden="true" />
              <span v-cleanhtml="Translator.trans('confirm.statement.submitted.public', { externId: extId })" />
            </p>

            <p v-cleanhtml="Translator.trans('confirm.statement.submitted.public.mailsent')" />
          </span>
          <p :class="prefixClass('flow-root')">
            <a
              :class="prefixClass('btn btn--primary u-1-of-1-palm')"
              :href="Routing.generate('DemosPlan_statement_single_export_pdf',{ sId: draftStatementId , procedure: procedureId })"
              data-cy="statementModal:downloadPDF"
              rel="noopener"
              target="_blank">
              <i
                :class="prefixClass('fa fa-file')"
                aria-hidden="true" />
              {{ Translator.trans('pdf.download') }}
            </a>

            <span :class="prefixClass('float-right text-right u-1-of-1-palm u-mt-0_5-palm')">
              <a
                :class="prefixClass('btn btn--secondary')"
                @click="toggleModal"
                :href="Routing.generate('DemosPlan_procedure_public_detail', { procedure: procedureId })"
                data-cy="statementModal:close"
                rel="noopener">
                {{ Translator.trans('close') }}
              </a>
            </span>
          </p>
        </template>
      </section>
    </dp-modal>
  </div>
</template>

<script>
import {
  checkResponse,
  CleanHtml,
  dpApi,
  DpCheckbox,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpLoading,
  DpModal,
  DpMultistepNav,
  DpRadio,
  DpUploadFiles,
  dpValidateMixin,
  hasOwnProp,
  isActiveFullScreen,
  makeFormPost,
  prefixClassMixin,
  toggleFullscreen
} from '@demos-europe/demosplan-ui'
import { mapMutations, mapState } from 'vuex'
import dayjs from 'dayjs'
import { defineAsyncComponent } from 'vue'
import StatementModalRecheck from './StatementModalRecheck'

// This is the mapping between form field ids and translation keys, which are displayed in the error message if the field contains an error
const fieldDescriptionsForErrors = {
  r_text: 'statement.text.short',
  confirmPrivacy: 'confirm.statement.privacy',
  locationFieldset: 'statement.map.reference',
  r_county: 'county',
  r_firstname: 'name.first',
  r_lastname: 'name.last',
  r_email_feedback: 'statement.fieldset.emailAddress',
  r_postalCode: 'postalcode',
  r_city: 'city',
  r_email: 'statement.fieldset.emailAddress',
  r_email2: 'email.confirm',
  r_getEvaluation: 'statement.feedback',
  r_phone: 'phone',
  personalInfoFieldset: 'submit.type',
  submitterTypeFieldset: 'submitter',
  r_houseNumber: 'street.number.short',
  r_street: 'street',
  r_userOrganisation: 'institution.name'
}

export default {
  name: 'StatementModal',

  components: {
    DpCheckbox,
    DpInlineNotification,
    DpInput,
    DpLabel,
    DpLoading,
    DpModal,
    DpMultistepNav,
    DpRadio,
    DpEditor: defineAsyncComponent(async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }),
    DpUploadFiles,
    FormGroupCitizenOrInstitution: defineAsyncComponent(() => import('./formGroups/FormGroupCitizenOrInstitution')),
    FormGroupCountyReference: defineAsyncComponent(() => import('./formGroups/FormGroupCountyReference')),
    FormGroupEmailAddress: defineAsyncComponent(() => import('./formGroups/FormGroupEmailAddress')),
    FormGroupEvaluationMailViaEmail: defineAsyncComponent(() => import('./formGroups/FormGroupEvaluationMailViaEmail')),
    FormGroupEvaluationMailViaSnailMailOrEmail: defineAsyncComponent(() => import('./formGroups/FormGroupEvaluationMailViaSnailMailOrEmail')),
    FormGroupMapReference: defineAsyncComponent(() => import('./formGroups/FormGroupMapReference')),
    FormGroupName: defineAsyncComponent(() => import('./formGroups/FormGroupName')),
    FormGroupPhoneNumber: defineAsyncComponent(() => import('./formGroups/FormGroupPhoneNumber')),
    FormGroupPhoneOrEmail: defineAsyncComponent(() => import('./formGroups/FormGroupPhoneOrEmail')),
    FormGroupPostalAndCity: defineAsyncComponent(() => import('./formGroups/FormGroupPostalAndCity')),
    FormGroupStateAndGroupAndOrgaNameAndPosition: defineAsyncComponent(() => import('./formGroups/FormGroupStateAndGroupAndOrgaNameAndPosition')),
    FormGroupStreet: defineAsyncComponent(() => import('./formGroups/FormGroupStreet')),
    FormGroupStreetAndHouseNumber: defineAsyncComponent(() => import('./formGroups/FormGroupStreetAndHouseNumber')),
    StatementModalRecheck
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [dpValidateMixin, prefixClassMixin],

  props: {
    counties: {
      type: Array,
      required: false,
      default: () => []
    },

    currentPage: {
      type: String,
      required: false,
      default: 'publicDetail'
    },

    extId: {
      type: String,
      required: false,
      default: ''
    },

    extraPersonalHint: {
      type: String,
      required: false,
      default: ''
    },

    feedbackFormFields: {
      type: Array,
      required: false,
      default: () => []
    },

    formOptions: {
      type: [Object, Array],
      required: false,
      default: () => ({})
    },

    initHasPlanningDocuments: {
      type: Boolean,
      required: false,
      default: true
    },

    isMapEnabled: {
      type: Boolean,
      required: false,
      default: false
    },

    loggedIn: {
      type: Boolean,
      required: false,
      default: false
    },

    orgaId: {
      type: String,
      required: false,
      default: ''
    },

    personalDataFormFields: {
      type: Array,
      required: false,
      default: () => []
    },

    planningDocumentsHasNegativeStatement: {
      type: Boolean,
      required: false,
      default: false
    },

    procedureId: {
      type: String,
      required: true
    },

    projectName: {
      type: String,
      required: true
    },

    publicParticipationPublicationEnabled: {
      type: Boolean,
      required: false,
      default: false
    },

    initRedirectPath: {
      type: String,
      required: false,
      default: 'DemosPlan_procedure_public_detail'
    },

    statementFormFields: {
      type: Array,
      required: false,
      default: () => []
    },

    statementFormHintPersonalData: {
      type: String,
      required: false,
      default: ''
    },

    statementFormHintRecheck: {
      type: String,
      required: false,
      default: ''
    },

    statementFormHintStatement: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      addToUnsavedDrafts: true,
      availableFormComponents: {
        name: { component: 'FormGroupName' },
        citizenXorOrgaAndOrgaName: { component: 'FormGroupCitizenOrInstitution' },
        postalAndCity: { component: 'FormGroupPostalAndCity' },
        street: { component: 'FormGroupStreet' },
        streetAndHouseNumber: { component: 'FormGroupStreetAndHouseNumber' },
        phoneNumber: { component: 'FormGroupPhoneNumber' },
        emailAddress: { component: 'FormGroupEmailAddress' },
        phoneOrEmail: { component: 'FormGroupPhoneOrEmail' },
        getEvaluationMailViaEmail: { component: 'FormGroupEvaluationMailViaEmail' },
        getEvaluationMailViaSnailMailOrEmail: { component: 'FormGroupEvaluationMailViaSnailMailOrEmail' },
        mapAndCountyReference: { component: 'FormGroupMapReference' },
        countyReference: { component: 'FormGroupCountyReference' },
        stateAndGroupAndOrgaNameAndPosition: {
          component: 'FormGroupStateAndGroupAndOrgaNameAndPosition',
          width: 'u-1-of-1'
        }
      },
      continueWriting: false,
      draftStatementId: '',
      editDraftDataInPublicDetail: true,
      formFields: [...this.statementFormFields, ...this.personalDataFormFields, ...this.feedbackFormFields],
      hasPlanningDocuments: this.initHasPlanningDocuments,
      isLoading: false,
      makePublicLabel: (() => {
        let label = Translator.trans('explanation.statement.public', { projectName: this.projectName })
        /*
         * While published statements of citizens do not show their name, the orga name of institutions is shown on
         * statements published by them.
         */
        label += ' ' + Translator.trans(hasPermission('feature_statement_publish_name') ? 'explanation.statement.public.organame' : 'explanation.statement.public.noname')
        return label
      })(),
      redirectPath: 'DemosPlan_procedure_public_detail',
      responseHtml: '',
      showHeader: true,
      step: 0,
      unsavedFiles: [],
      updateDraftListRequired: false
    }
  },

  computed: {
    ...mapState('Notify', ['messages']),

    ...mapState('PublicStatement', {
      initFormDataJSON: 'initForm',
      initDraftStatements: 'initDraftStatements',
      formData: 'statement',
      highlighted: 'highlighted',
      localStorageName: 'localStorageName',
      unsavedDrafts: 'unsavedDrafts',
      userId: 'userId'
    }),

    canNotBeNegativeReport () {
      return this.formData.r_element_id !== '' ||
        this.formData.r_document_id !== '' ||
        this.formData.r_text !== '' ||
        this.formData.r_location !== '' ||
        this.formData.uploadedFiles !== ''
    },

    commentingIcon () {
      return this.continueWriting ? 'fa-commenting' : 'fa-comment'
    },

    displayEditSubmit () {
      return this.draftStatementId !== ''
    },

    draftStatementIdStorageName () {
      return `draftStatementId:${this.userId}:${this.procedureId}`
    },

    fileStorageName () {
      return `uploadedFiles:${this.userId}:${this.procedureId}:${this.draftStatementId}`
    },

    initialFiles () {
      if (hasOwnProp(this.formData, 'r_files_initial')) {
        return JSON.parse(this.formData.r_files_initial)
          .map(fileString => {
            const fileArray = fileString.split(':')
            return {
              name: fileArray[0],
              hash: fileArray[1],
              size: fileArray[2],
              type: fileArray[3]
            }
          })
      }
      return []
    },

    isUnsaved () {
      return this.unsavedDrafts.includes(this.draftStatementId)
    },

    personalDataFormDefinitions () {
      return this.personalDataFormFields.map(el => {
        this.availableFormComponents[el.name].width = this.availableFormComponents[el.name].width || 'u-1-of-2'
        return { ...el, ...this.availableFormComponents[el.name] }
      })
    },

    statementFormDefinitions () {
      return this.statementFormFields.map(el => {
        return { ...el, ...this.availableFormComponents[el.name] }
      })
    },

    statementFeedbackDefinitions () {
      return this.feedbackFormFields.map(el => {
        return { ...el, ...this.availableFormComponents[el.name] }
      })
    }
  },

  watch: {
    formData: {
      handler (newFormData) {
        const parsed = JSON.stringify(newFormData)
        this.continueWriting = this.initFormDataJSON !== parsed
      },
      deep: true
    }
  },

  methods: {
    ...mapMutations('Notify', ['remove']),

    ...mapMutations('PublicStatement', [
      'addUnsavedDraft',
      'clearDraftState',
      'removeStatementProp',
      'removeUnsavedDraft',
      'resetInitForm',
      'resetStatement',
      'update',
      'updateHighlighted',
      'updateDeleteFile',
      'updateStatement'
    ]),

    // On every successful upload of a file, both `this.unsavedFiles` and `this.statement` are updated.
    addUnsavedFile (file) {
      this.unsavedFiles.push(file)
      this.setStatementData({ uploadedFiles: this.unsavedFiles.map(el => el.hash).join(',') })
    },

    createErrorMessage (formId) {
      if (!this.dpValidate.invalidFields || !this.dpValidate.invalidFields[formId]) {
        return ''
      }

      const invalidFields = this.dpValidate.invalidFields[formId]
      const uniqueFieldDescriptions = Array.from(new Set(invalidFields.map(field => {
        const fieldId = field.getAttribute('id')
        return `<li>${Translator.trans(fieldDescriptionsForErrors[fieldId])}</li>`
      })))
      return `<p>${Translator.trans('error.in.fields')}</p><ul class="list-disc u-ml-0_75">${uniqueFieldDescriptions.join('')}</ul>`
    },

    fieldIsActive (fieldKey) {
      return this.formFields.map(el => el.name).includes(fieldKey)
    },

    getDraftStatement (draftStatementId, openModal = false) {
      this.writeDraftStatementIdToSession(draftStatementId)

      // If the draft already exists. load it from session storage
      const dId = draftStatementId !== '' ? draftStatementId : 'new'
      const existingDataString = localStorage.getItem(`publicStatement:${this.userId}:${this.procedureId}:${dId}`)
      const draftExists = (draftStatementId !== '' && existingDataString !== null)
      if (draftExists) {
        const existingData = JSON.parse(existingDataString)

        this.setStatementData(existingData)
      }

      // Else: get the data via api
      return dpApi({
        method: 'GET',
        url: Routing.generate('DemosPlan_statement_get_ajax', { procedureId: this.procedureId, draftStatementId: this.draftStatementId })
      })
        .then(checkResponse)
        .then(data => {
          const priorityAreaKey = data.draftStatement.statementAttributes.priorityAreaKey || ''
          const priorityAreaType = data.draftStatement.statementAttributes.priorityAreaType || ''
          const statementFiles = data.draftStatement.files ? JSON.stringify(data.draftStatement.files) : ''
          const draft = {
            r_text: data.draftStatement.text,
            r_files_initial: statementFiles || [],
            r_ident: this.draftStatementId,
            r_isNegativeReport: data.draftStatement.negativ ? '1' : '0',
            r_element_id: data.draftStatement.elementId || '',
            r_element_title: data.draftStatement.element?.title ?? '',
            r_paragraph_id: data.draftStatement.paragraphId ?? '',
            r_paragraph_title: data.draftStatement.paragraph?.title ?? '',
            r_document_id: data.draftStatement.document?.id ?? '',
            r_document_title: data.draftStatement.document?.title ?? '',
            r_represents: data.draftStatement.represents ?? '',
            r_location: Object.keys(data.draftStatement.statementAttributes)[0] ?? 'mapLocation',
            r_location_geometry: data.draftStatement.polygon,
            r_location_priority_area_key: priorityAreaKey,
            r_location_priority_area_type: priorityAreaType,
            r_location_point: '',
            location_is_set: priorityAreaKey.length > 0 ? 'priority_area' : 'geometry',
            r_county: data.draftStatement.statementAttributes.county ?? '',
            r_makePublic: !!data.draftStatement.publicAllowed
          }

          this.hasPlanningDocuments = data.hasPlanningDocuments || this.initHasPlanningDocuments
          if (draft.r_location === 'noLocation') draft.r_location = 'notLocated'
          if (draft.r_location === 'mapLocation' && data.draftStatement.polygon) draft.r_location = 'point'

          if (draftExists === false) {
            /*
             * If it is a draft, we set the data from local storage (see above).
             */
            this.setStatementData(draft)
            this.removeStatementProp('immediate_submit')
            sessionStorage.removeItem(this.fileStorageName)

            if (this.initRedirectPath !== 'DemosPlan_procedure_public_detail') {
              sessionStorage.setItem('redirectpath', this.initRedirectPath)
            }
          }

          /*
           * Get the original version from BE, so we can compare the original and the edited version.
           */
          this.resetInitForm(this.draftStatementId)

          if (openModal === true) {
            this.toggleModal(false)
          }
        })
    },

    /*
     * When clicking the little ✎ icon in the "recheck" step, users are sent to the
     * respective multistep step, and afterwards the element they want to edit is focused.
     * The function expects a string with the id of the input to be focused as its argument.
     */
    handleEditInput (input) {
      this.step = {
        r_text: 0,
        r_makePublic: 0,
        r_useName_0: 1,
        r_useName_1: 1,
        r_getFeedback: 1
      }[input] || 0
      this.$nextTick(() => {
        // Focusing of the tiptap instance must be handled separately
        if (input === 'r_text') {
          this.$refs.statementEditor.editor.focus('end')
        } else {
          document.getElementById(input).focus()
        }
      })
    },

    loadDraftListPage () {
      if (window.location.href.includes(Routing.generate('DemosPlan_statement_list_draft', { procedure: this.procedureId })) || window.location.href.includes(Routing.generate('DemosPlan_statement_list_released_group', { procedure: this.procedureId }))) {
        window.location.reload()
      } else {
        window.location.href = Routing.generate(this.redirectPath, { procedure: this.procedureId }) + '#' + this.draftStatementId
      }
    },

    reset () {
      if (window.dpconfirm(Translator.trans('check.statement.discard.changes'))) {
        this.unsavedFiles.forEach(file => {
          this.$refs.uploadFiles.handleRemove(file)
        })
        this.$refs.statementEditor.resetEditor()
        this.setStatementData(JSON.parse(this.initFormDataJSON))
        this.addToUnsavedDrafts = false
        this.toggleModal(false)
        this.step = 0
        this.showHeader = true
        this.$nextTick(() => {
          this.$root.$emit('uploader-reset')

          if (this.draftStatementId !== '') {
            window.location.href = Routing.generate(this.redirectPath, { procedure: this.procedureId, _fragment: this.draftStatementId })
          }
        })

        this.resetSessionStorage()
        sessionStorage.removeItem('redirectpath')
      }
    },

    focusMultistep (step) {
      this.$nextTick(() => {
        const currentMultistepButton = this.$el.querySelectorAll('.c-multistep__step')[step]
        if (currentMultistepButton) {
          currentMultistepButton.focus()
        }
      })
    },

    gotoTab (tab) {
      if (document.getElementById(tab)) {
        this.$emit('toggle-tabs', '#' + tab)
      }

      if (this.currentPage === 'publicDetail') {
        this.toggleModal(false)
      } else {
        window.location.href = Routing.generate('DemosPlan_procedure_public_detail', { procedure: this.procedureId }) + `#${tab}`
      }
    },

    handleModalToggle (open) {
      if (open === false) {
        if (this.editDraftDataInPublicDetail === false && this.currentPage !== 'publicDetail') {
          this.resetSessionStorage()
        }

        if (this.continueWriting && this.addToUnsavedDrafts) {
          this.addUnsavedDraft(this.draftStatementId)
        } else {
          this.removeUnsavedDraft(this.draftStatementId)
        }

        if (this.updateDraftListRequired) {
          this.loadDraftListPage()
        }
      }
    },

    /**
     * Prepare the data to be sent to the backend
     * We have to copy the store state because deleting entries is not reactive atm.
     *
     * @param formData
     *
     * @return {*}
     */
    prepareDataToSend (formData) {
      const dataToSend = { ...formData }

      /*
       * If we have no map/county-reference enabled we can't set it as default, because then this would be preselected
       * which we don't want
       */
      if (dataToSend.location_is_set === '') {
        dataToSend.location_is_set = 'notLocated'
      }

      if (dataToSend.r_location !== 'county') {
        dataToSend.r_county = ''
      }

      /*
       * If no submitter type is selected we assume its a citizen.
       * it can't be preset to prevent the radio options from being preselected
       */
      if (dataToSend.r_submitter_role === '') {
        dataToSend.r_submitter_role = 'citizen'
      }

      if (dataToSend.r_location !== 'point') {
        dataToSend.r_location_point = ''
        dataToSend.r_location_priority_area_key = ''
        dataToSend.r_location_priority_area_type = ''
        dataToSend.r_location_geometry = ''
      }

      /*
       * Remove not used fields
       * thats neccessary because the BE checks for their existance to decide what do show (e.g. in exports)
       *
       */
      if (dataToSend.r_getFeedback === 'off') {
        delete dataToSend.r_getFeedback
      }
      if (dataToSend.r_houseNumber === '') {
        delete dataToSend.r_houseNumber
      }
      if (dataToSend.r_postalCode === '') {
        delete dataToSend.r_postalCode
      }
      if (dataToSend.r_city === '') {
        delete dataToSend.r_city
      }
      if (hasPermission('feature_statements_feedback_check_email') === false) {
        delete dataToSend.r_email2
      }
      /*
       * Tweak e-mail values so they fit to the update request
       * due to the dynamic handling there can be inconsistencies
       */
      if ((hasOwnProp(dataToSend, 'r_getFeedback') === false || dataToSend.r_getEvaluation !== 'email') && dataToSend.r_email === '') {
        delete dataToSend.r_email
      }

      return dataToSend
    },

    removeDocumentRelation () {
      const elementFields = {
        r_element_id: '',
        r_element_title: '',
        r_document_id: '',
        r_document_title: '',
        r_paragraph_id: '',
        r_paragraph_title: ''
      }

      this.setStatementData(elementFields)
    },

    removeNotificationsFromStore () {
      this.messages.forEach(message => {
        this.remove(message)
      })
    },

    removeUnsavedFile (file) {
      const indexToRemove = this.unsavedFiles.findIndex(el => el.hash === file.hash)

      this.unsavedFiles.splice(indexToRemove, 1)
      this.setStatementData({
        uploadedFiles: this.unsavedFiles
          .map(el => el.hash)
          .join(',')
      })
    },

    sendStatement (e, immediateSubmit = false, keepModalOpen = false) {
      e.preventDefault()

      if (this.validateStatementStep() === false || this.validateRecheckStep() === false) {
        return
      }

      this.isLoading = true
      this.setStatementData({ immediate_submit: immediateSubmit })
      this.setStatementData({ r_loadtime: dayjs().unix() })

      const dataToSend = this.prepareDataToSend(this.formData)

      let route = Routing.generate('DemosPlan_statement_public_participation_new_ajax', { procedure: this.procedureId }) + (immediateSubmit ? '?immediate_submit=true' : '')

      // Draft statements
      if (this.draftStatementId !== '') {
        dataToSend.action = 'statementedit'
        route = Routing.generate('DemosPlan_statement_edit', { statementID: this.draftStatementId, procedure: this.procedureId })
      } else {
        dataToSend.action = 'statementpublicnew'
      }

      return makeFormPost(dataToSend, route)
        .then(response => {
          if (response.status === 429) {
            dplan.notify.notify('error', Translator.trans('error.statement.not.saved.throttle'))

            return false
          }
          if (response.status !== 200) {
            dplan.notify.notify('error', Translator.trans('error.statement.not.saved'))

            return false
          }
          /*
           * Handling for successful responses
           * if it's not an HTML-Response like after creating a new one
           */
          if (response.status === 200) {
            dplan.notify.notify('confirm', Translator.trans('confirm.statement.saved'))

            this.updateInitialFilesAfterSave()

            /*
             * If the modal should stay open
             * the init- and unsaved state has to be adjusted, but we don't want to reload the page
             */
            if (keepModalOpen) {
              this.removeUnsavedDraft(this.draftStatementId)
              this.removeStatementProp('immediate_submit')
              this.resetInitForm(this.draftStatementId)
              this.addToUnsavedDrafts = false
              this.updateDraftListRequired = true

              return true
            }

            /*
             * (re)set custom changes to match the current structure of the statement
             * necessary to compare for unsaved changes
             */
            this.setStatementData({ action: 'statementedit', r_submitter_role: '' })
            if (this.draftStatementId !== '') {
              // We have to set it here again because in the meanwhile some fields got resetted which triggered a state change
              this.addToUnsavedDrafts = false
              this.removeUnsavedDraft(this.draftStatementId)
              this.clearDraftState(this.draftStatementId)
            } else {
              this.resetStatement()
            }
            this.removeStatementProp('immediate_submit')
          }

          // @IMPROVE throw success message instead of sending it as Html with the response
          if (response.data && response.data.data && response.data.data.submitRoute) {
            // Go to confirm page to submit draft immediately
            setTimeout(() => {
              window.location.href = response.data.data.submitRoute
            }, 2000)
          } else if (this.draftStatementId !== '') {
            // Go to draft statement list and highlight current draft
            this.toggleModal(false)
            this.resetSessionStorage()
            this.loadDraftListPage()
          } else {
            this.step = 3
            this.showHeader = false
            if (response.data.data && response.data.data.responseHtml) {
              this.responseHtml = response.data.data.responseHtml

              this.resetSessionStorage()
              sessionStorage.removeItem('redirectpath')

              /*
               * We get the complete html for the confirm dialogue from the response,
               * so we have to add the event listener for the close button manually
               */
              this.$nextTick(() => {
                if (document.querySelector('[data-statement-action=resetSuccess]') !== null) {
                  document.querySelector('[data-statement-action=resetSuccess]').addEventListener('click', this.toggleModal)
                }
              })
            }
          }
        })
        .catch(e => {
          console.error('sending statement failed', e)
        })
        .then(() => {
          this.isLoading = false
        })
    },

    setPrivacyPreference (data) {
      this.setStatementData(data)
      this.removeNotificationsFromStore()
    },

    writeDraftStatementIdToSession (draftStatementId) {
      this.draftStatementId = draftStatementId
      sessionStorage.setItem(this.draftStatementIdStorageName, draftStatementId)
    },

    resetSessionStorage () {
      sessionStorage.removeItem(this.draftStatementIdStorageName)
    },

    setStatementData (data) {
      this.addToUnsavedDrafts = true
      this.updateStatement({ r_ident: this.draftStatementId, ...data })
    },

    toggleModal (resetOnClose = true, data = null) {
      // Check if browser is in fullscreen mode
      if (isActiveFullScreen()) {
        toggleFullscreen()
      }
      this.editDraftDataInPublicDetail = resetOnClose
      this.step = 0
      this.showHeader = true
      this.$refs.statementModal.toggle()
      if (data) {
        this.updateStatement(data)
      }
    },

    /*
     * For "normal" way of updating the statement we don't need this, because we reload the page.
     * But if we want to keep the modal open, we have to update the files all by ourselves.
     */
    updateInitialFilesAfterSave () {
      let currentFiles = hasOwnProp(this.formData, 'r_files_initial') ? JSON.parse(this.formData.r_files_initial) : []

      // Remove deleted files from attachments list
      if (this.formData.delete_file.length > 0) {
        currentFiles = currentFiles.filter(file => this.formData.delete_file.includes(file.split(':')[1]) === false) // Compare hashes
        document.querySelectorAll('[name="delete_file[]"]').forEach(el => {
          el.checked = false
        })
      }

      // Add unsaved files from uploader
      this.unsavedFiles.forEach(file => {
        currentFiles.push(`${file.name}:${file.hash}:${file.type}`)
      })

      // Store updated data
      const newFilesArrayString = JSON.stringify(currentFiles)
      this.setStatementData({ r_files_initial: newFilesArrayString })

      // Reset helper
      this.unsavedFiles = []
      if (this.$refs.uploadFiles) {
        this.$refs.uploadFiles.clearFilesList()
      }
      // Reset store data
      this.setStatementData({ delete_file: [] })
      this.setStatementData({ uploadedFiles: '' })
      // Reset session storage - remove uploaded and saved files
      sessionStorage.removeItem(this.fileStorageName)
    },

    validateStatementStep () {
      if (this.formData.r_location === 'point' && (this.formData.r_location_geometry === '' && this.formData.r_location_point === '' && this.formData.r_location_priority_area_key === '')) {
        this.setStatementData({ r_location: '' })
        document.getElementById('locationPoint').checked = false
      }

      const postValidation = () => {
        if (this.dpValidate.statementForm === false) {
          this.$nextTick(() => document.getElementById('statementFormErrors').focus())
          return false
        }
        if (typeof this.dpValidate.invalidFields.statementForm.find(el => el.id === 'check_location_isset') !== 'undefined') {
          this.$refs.mapStatementRadio.classList.add('is-invalid')
        }

        if (this.step === 0 && this.dpValidate.statementForm && this.loggedIn === false) {
          this.step = 1
          this.focusMultistep(1)
        }
        return this.dpValidate.statementForm
      }
      return this.dpValidateAction('statementForm', postValidation, true)
    },

    validatePersonalDataStep () {
      if (this.dpValidate.submitterForm) {
        this.step = 2
        this.focusMultistep(2)
      } else {
        this.$nextTick(() => document.getElementById('submitterFormErrors').focus())
      }
    },

    validateRecheckStep () {
      return this.dpValidate.recheckForm
    }
  },

  mounted () {
    // Set data from map
    this.$root.$on('update-statement-form-map-data', (data = {}, toggle = true) => {
      this.setStatementData(data)
      if (toggle) {
        this.toggleModal(false)
        // We need this to reset the animation so it can be fired again.
        this.updateHighlighted({ key: 'location', val: true })
        setTimeout(() => {
          this.updateHighlighted({ key: 'location', val: false })
        }, 2000)
      }
    })

    this.$root.$on('statement-modal:goto-tab', tabname => {
      this.gotoTab(tabname)
    })

    // Set draft statement Id from href
    this.draftStatementId = sessionStorage.getItem(this.draftStatementIdStorageName) || ''
    this.redirectPath = sessionStorage.getItem('redirectpath') || this.initRedirectPath

    if (this.draftStatementId !== '') {
      this.getDraftStatement(this.draftStatementId)
    } else {
      const sessionStorageBegunStatement = localStorage.getItem(`publicStatement:${this.userId}:${this.procedureId}:new`)
      const sessionStorageBegunStatementParsed = JSON.parse(sessionStorageBegunStatement)
      if (sessionStorageBegunStatement && sessionStorageBegunStatement !== this.initFormDataJSON && sessionStorageBegunStatementParsed.r_ident === '') {
        this.setStatementData(sessionStorageBegunStatementParsed)
      } else {
        this.setStatementData({ r_county: this.counties.find(el => el.selected) ? this.counties.find(el => el.selected).value : '' })
      }
    }
  }
}
</script>
