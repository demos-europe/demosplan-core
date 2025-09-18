<script>
import {
  DpContextualHelp,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpMultiselect,
  sortAlphabetically
} from '@demos-europe/demosplan-ui'
import DpEmailList from './DpEmailList.vue'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'

export default {
  name: 'ProcedureGeneralSettings',

  components: {
    DpLabel,
    AddonWrapper,
    DpContextualHelp,
    DpEmailList,
    DpInlineNotification,
    DpInput,
    DpMultiselect,
  },

  props: {
    agencies: {
      type: Array,
      required: false,
      default: () => [],
    },

    authorizedUsersOptions: {
      type: Array,
      required: false,
      default: () => [],
    },

    dataInputOrgas: {
      type: Array,
      required: false,
      default: () => [],
    },

    initAgencies: {
      required: false,
      type: Array,
      default: () => [],
    },

    formData: {
      type: Object,
      required: true,
      default: () => ({})
    },

    hasProcedureUserRestrictedAccess: {
      required: false,
      type: Boolean,
      default: false,
    },

    initAuthUsers: {
      required: false,
      type: Array,
      default: () => [],
    },

    initDataInputOrgas: {
      required: false,
      type: Array,
      default: () => [],
    },

    procedureSettings: {
      required: true,
      type: Object,
    },
  },

  data () {
    return {
      selectedAgencies: this.initAgencies,
      selectedAuthUsers: this.initAuthUsers,
      selectedDataInputOrgas: this.initDataInputOrgas,
    }
  },

  computed: {
    authUsersOptions () {
      const users = JSON.parse(JSON.stringify(this.authorizedUsersOptions))
      return sortAlphabetically(users, 'name')
    },
  },

  methods: {
    selectAllAuthUsers () {
      this.selectedAuthUsers = this.authorizedUsersOptions
    },

    unselectAllAuthUsers () {
      this.selectedAuthUsers = []
    },
  }
}
</script>

<template>
  <!-- {# wizard item: Intern #} -->
  <fieldset
    class="o-wizard"
    :data-wizard-finished="procedureSettings.internalComplete ? 'true' : null"
    :data-wizard-topic="Translator.trans('wizard.topic.internal')"
    :data-dp-validate-topic="Translator.trans('wizard.topic.internal')"
    data-dp-validate="internalForm">

    <legend
      data-toggle-id="internal"
      data-cy="internal"
      class="js__toggleAnything">
      <i class="caret"></i>
      {{ Translator.trans('wizard.topic.internal') }}
      <i class="fa fa-check"></i>
    </legend>

    <div
      data-toggle-id="internal"
      class="o-wizard__content">
      <div class="o-wizard__main">
        <div
          v-if="hasPermission('field_procedure_adjustments_planning_agency')"
          class="u-mb">
          <label
            class="inline-block u-mb-0"
            for="r_agency">
            {{ Translator.trans('planningagency.participating') }}
          </label>

          <dp-contextual-help
            class="mb-1"
            :text="Translator.trans('text.procedure.edit.planning_agency')"
          />

          <dp-multiselect
            id="r_agency"
            v-model="selectedAgencies"
            data-cy="internal:selectedAgencies"
            label="name"
            multiple
            :options="agencies"
            track-by="id"
            @select="$emit('update:agency', $event.target.value)">
            <template v-slot:option="{ props }">
              {{ props.option.name }}
            </template>
            <template v-slot:tag="{ props }">
                <span class="multiselect__tag">
                  {{ props.option.name }}
                  <i
                    aria-hidden="true"
                    @click="props.remove(props.option)"
                    tabindex="1"
                    class="multiselect__tag-icon">
                  </i>
                  <input
                    type="hidden"
                    :value="props.option.id"
                    name="r_agency[]"/>
                </span>
            </template>
          </dp-multiselect>
        </div>

        <div class="u-mb">
          <label
            class="inline-block u-mb-0"
            :for="formData.agencyMainEmailAddress.id">
            {{ Translator.trans('email.procedure.agency') }}*
            <p class="weight--normal">
              {{ Translator.trans('explanation.organisation.email.procedure.agency') }}
            </p>
          </label>

          <dp-contextual-help
            class="mb-1"
            :text="Translator.trans('email.procedure.agency.help')"
          />

          <dp-inline-notification
            v-if="formData.agencyMainEmailAddress.errors && formData.agencyMainEmailAddress.errors.length > 0"
            type="error"
            :message="formData.agencyMainEmailAddress.errors.join(', ')"
          />

          <dp-input
            :id="formData.agencyMainEmailAddress.id"
            data-cy="agencyMainEmailAddress"
            :data-dp-validate-error-fieldname="Translator.trans('email.procedure.agency')"
            name="agencyMainEmailAddress[fullAddress]"
            required
            type="email"
            :model-value="formData.agencyMainEmailAddress.fullAddress">
          </dp-input>
        </div>

        <div class="u-mb">
          <label
            class="inline-block u-mb-0"
            :for="formData.agencyExtraEmailAddresses.id">
            {{ Translator.trans('email.address.more') }}
            <p class="weight--normal">
              {{ Translator.trans('email.address.more.explanation') }}
            </p>
          </label>

          <dp-contextual-help
            class="mb-1"
            :text="Translator.trans('email.address.more.explanation.help')"
          />

          <dp-email-list
            data-cy="administrationEdit"
            :init-emails="formData.agencyExtraEmailAddresses">
          </dp-email-list>
        </div>

        <div class="u-mb">
          <addon-wrapper
            hook-name="administration.edit.extra.fields"
            :addon-props="{
              procedureId: procedureSettings.id
            }">
          </addon-wrapper>
        </div>

        <div
          v-if="hasPermission('feature_use_data_input_orga')"
          class="u-mb">
          <label class="inline-block u-mb-0" for="r_dataInputOrga">
            {{ Translator.trans('data.input.orga') }}
          </label>

          <dp-contextual-help
            class="mb-1"
            :text="Translator.trans('text.procedure.edit.data_input_orgas')"
          />

          <dp-multiselect
            id="r_dataInputOrgaTest"
            data-cy="internal:selectedDataInputOrgas"
            v-model="selectedDataInputOrgas"
            label="name"
            multiple
            :options="dataInputOrgas"
            track-by="id">
            <template v-slot:option="{ props }">
              {{ props.option.name }}
            </template>
            <template v-slot:tag="{ props }">
              <span class="multiselect__tag">
                {{ props.option.name }}
                <i
                  aria-hidden="true"
                  @click="props.remove(props.option)"
                  tabindex="1"
                  class="multiselect__tag-icon">
                </i>
                <input
                  type="hidden"
                  :value="props.option.id"
                  name="r_dataInputOrga[]" />
              </span>
            </template>
          </dp-multiselect>
        </div>

        <div
          v-if="hasPermission('feature_procedure_user_restrict_access_edit') && hasProcedureUserRestrictedAccess"
          class="u-mb">
          <label
            class="inline-block u-mb-0"
            for="r_authorizedUsers">
            {{ Translator.trans('authorized.users') }}
          </label>

          <dp-contextual-help
            class="mb-1"
            :text="Translator.trans('text.procedure.edit.authorized.users')"
          />

          <dp-multiselect
            id="r_authorizedUsers"
            v-model="selectedAuthUsers"
            data-cy="internal:selectedAuthUsers"
            label="name"
            multiple
            :options="authUsersOptions"
            selection-controls
            track-by="id"
            @selectAll="selectAllAuthUsers"
            @deselectAll="unselectAllAuthUsers">
            <template v-slot:option="{ props }">
              {{ props.option.name }}
            </template>
            <template v-slot:tag="{ props }">
                <span class="multiselect__tag">
                    {{ props.option.name }}
                    <i
                      aria-hidden="true"
                      @click="props.remove(props.option)"
                      tabindex="1" class="multiselect__tag-icon">
                    </i>
                    <input
                      type="hidden"
                      :value="props.option.id"
                      name="r_authorizedUsers[]" />
                </span>
            </template>
          </dp-multiselect>
        </div>

        <!-- The procedure type is set only once, when creating a procedure.
        It can't nbe changed afterwards. Anyhow, the user is informed here which type
        the procedure was created with. -->
        <div class="u-mb">
          <p class="weight--bold u-mb-0">
            {{ Translator.trans('text.procedures.type') }}
          </p>
          <p
            v-if="procedureSettings.procedureType"
            class="u-mb-0">
            {{ procedureSettings.procedureType.name }}<br>
            {{ procedureSettings.procedureType.description }}
          </p>
          <p v-else>
            {{ Translator.trans('procedure.type.not.set') }}
          </p>
        </div>

        <div class="u-mb-0_5">
          <label class="inline-block u-mb-0" for="r_desc">
            {{ Translator.trans('internalnote') }}
          </label>

          <dp-contextual-help
            class="mb-1"
            :text="Translator.trans('text.procedure.edit.note')"
          />

          <!-- Bei textareas muss der Inhalt ohne Leerzeichen zwischen den Tags stehen, sonst werden die Leerzeichen ausgegeben und multiplizieren sich im Frontend. -->
          <dp-text-area
            class="bg-surface"
            data-cy="internal:internalNote"
            grow-to-parent
            id="r_desc"
            name="r_desc"
            reduced-height
            :value="procedureSettings.desc ? procedureSettings.desc : Translator.trans('notspecified')"
          />
        </div>
      </div>

      <label class="o-wizard__mark u-mb-0">
        <input
          data-wizard-cb
          data-cy="fieldInternCompletions"
          name="fieldCompletions[]"
          value="internalComplete"
          type="checkbox"
          v-model="procedureSettings.internalComplete"
        />
        {{ Translator.trans('wizard.mark_as_done') }}
      </label>
    </div>
    <button
      type="button"
      data-cy="wizardNext"
      data-dp-validate-capture-click
      class="btn btn--primary o-wizard__btn o-wizard__btn--next hidden submit">
      {{ Translator.trans('wizard.next') }}
    </button>
  </fieldset>
</template>

<style scoped>

</style>
