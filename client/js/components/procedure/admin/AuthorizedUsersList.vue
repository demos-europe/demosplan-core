<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="c-tokenlist">
    <dp-accordion
      :is-open="showCreateForm"
      :title="Translator.trans('authorization.create')"
      @item:toggle="showCreateForm = !showCreateForm"
    >
      <div class="layout">
        <p class="layout__item u-mt-0_25">
          {{ Translator.trans('authorization.create.hint') }}
        </p>

        <div
          data-dp-validate="createToken"
          class="layout__item u-2-of-7"
        >
          <dp-input
            id="submitterName"
            v-model="newUser.submitterName"
            class="mb-3"
            :label="{
              text: Translator.trans('name')
            }"
            required
          />
          <dp-input
            id="submitterEmailAddress"
            v-model="newUser.submitterEmailAddress"
            :label="{
              text: Translator.trans('email.address')
            }"
            type="email"
          />
        </div><!--

     --><div class="layout__item u-2-of-7">
          <div class="o-form__group mb-3">
            <dp-input
              id="submitterStreet"
              v-model="newUser.submitterStreet"
              class="o-form__group-item"
              :label="{
                text: Translator.trans('street')
              }"
            />
            <dp-input
              id="submitterHouseNumber"
              v-model="newUser.submitterHouseNumber"
              class="o-form__group-item"
              :size="5"
              :label="{
                text: Translator.trans('street.number.short')
              }"
            />
          </div>

          <div class="o-form__group">
            <dp-input
              id="submitterPostalCode"
              v-model="newUser.submitterPostalCode"
              class="o-form__group-item"
              :label="{
                text: Translator.trans('postalcode')
              }"
              pattern="^[0-9]{5}$"
              :size="5"
            />
            <dp-input
              id="submitterCity"
              v-model="newUser.submitterCity"
              class="o-form__group-item"
              :label="{
                text: Translator.trans('city')
              }"
            />
          </div>
        </div><!--

     --><div class="layout__item u-3-of-7">
          <dp-text-area
            id="memo"
            v-model="newUser.note"
            :label="Translator.trans('memo')"
            maxlength="1000"
          />
        </div>

        <dp-button-row
          class="mt-2"
          :busy="isSaving"
          primary
          secondary
          @primary-action="dpValidateAction('createToken', createToken, false)"
          @secondary-action="abortCreate"
        />
      </div>
    </dp-accordion>

    <div class="mt-6">
      <a :href="exportRoute">
        <i
          class="fa fa-share-square pr-1"
          aria-hidden="true"
        />
        {{ Translator.trans('export') }}
      </a>
      <dp-contextual-help
        class="inline-block ml-1"
        :text="Translator.trans('consultation.export.bulk.letter.explanation')"
      />
    </div>

    <dp-data-table-extended
      ref="dataTable"
      class="mb-4 mt-2 max-w-full"
      :header-fields="headerFields"
      :default-sort-order="{ direction: 1, key: 'submitterName' }"
      :is-loading="isLoading"
      is-expandable
      is-sortable
      :table-items="tokens"
      track-by="tokenId"
      @updated:sort-order="setSortOptions"
    >
      <template v-slot:submitterName="rowData">
        <div class="o-hellip__wrapper">
          <div
            v-tooltip="user(rowData.tokenId).submitterName"
            class="o-hellip--nowrap"
          >
            {{ user(rowData.tokenId).submitterName }}
          </div>
        </div>
      </template>
      <template v-slot:submitterEmailAddress="rowData">
        <div class="o-hellip__wrapper">
          <div
            v-if="rowData.authorName && !rowData.anonymous"
            v-tooltip="rowData.submitterEmailAddress"
            class="o-hellip--nowrap"
          >
            {{ user(rowData.tokenId).submitterEmailAddress }}
          </div>
          <div
            v-else
            class="o-hellip--nowrap"
          >
            {{ Translator.trans('anonymous') }}
          </div>
        </div>
      </template>
      <template v-slot:note="rowData">
        <div
          v-tooltip="user(rowData.tokenId).note"
          class="o-hellip__wrapper max-w-[90%]"
        >
          <span class="o-hellip--nowrap block">
            {{ user(rowData.tokenId).note }}
          </span>
        </div>
      </template>
      <template v-slot:expandedContent="rowData">
        <span data-dp-validate="saveEditAuthorisedUser">
          <div class="flex mt-1">
            <div class="align-top u-5-of-9 pr-4">
              <dp-input
                :id="`name:${rowData.tokenId}`"
                :disabled="!rowData.isManual || !rowData.isEditable"
                :label="{
                  text: Translator.trans('name')
                }"
                required
                :model-value="rowData.submitterName"
                @update:model-value="val => updateUserField(rowData.tokenId, 'submitterName', val)"
              />
              <div
                v-if="!rowData.authorName || rowData.anonymous"
                class="mt-3 mb-2"
              >
                <strong :id="`submitterEmailAddressAnonymous:${rowData.tokenId}`">
                  {{ Translator.trans('email') }}
                </strong>
                <p :aria-labelledby="`submitterEmailAddressAnonymous:${rowData.tokenId}`">
                  {{ Translator.trans('anonymous') }}
                </p>
              </div>
              <dp-input
                v-else
                :id="`email:${rowData.tokenId}`"
                class="mt-3"
                :disabled="!rowData.isManual || !rowData.isEditable"
                :label="{
                  text: Translator.trans('email')
                }"
                type="email"
                :model-value="rowData.submitterEmailAddress"
                @update:model-value="val => updateUserField(rowData.tokenId, 'submitterEmailAddress', val)"
              />
              <div class="flex flex-row mb-2 mt-3">
                <dp-input
                  :id="`street:${rowData.tokenId}`"
                  class="w-full mr-2"
                  :disabled="!rowData.isManual || !rowData.isEditable"
                  :label="{
                    text: Translator.trans('street')
                  }"
                  :model-value="rowData.submitterStreet"
                  @update:model-value="val => updateUserField(rowData.tokenId, 'submitterStreet', val)"
                />
                <dp-input
                  :id="`houseNumber:${rowData.tokenId}`"
                  :disabled="!rowData.isManual || !rowData.isEditable"
                  :label="{
                    text: Translator.trans('street.number.short')
                  }"
                  :model-value="rowData.submitterHouseNumber"
                  :size="5"
                  width="auto"
                  @update:model-value="val => updateUserField(rowData.tokenId, 'submitterHouseNumber', val)"
                />
              </div>
              <div class="flex flex-row mb-2 mt-3">
                <dp-input
                  :id="`postalcode:${rowData.tokenId}`"
                  class="mr-2"
                  :disabled="!rowData.isManual || !rowData.isEditable"
                  :label="{
                    text: Translator.trans('postalcode')
                  }"
                  :model-value="rowData.submitterPostalCode"
                  pattern="^[0-9]{5}$"
                  :size="5"
                  width="auto"
                  @update:model-value="val => updateUserField(rowData.tokenId, 'submitterPostalCode', val)"
                />
                <dp-input
                  :id="`city:${rowData.tokenId}`"
                  class="w-full"
                  :disabled="!rowData.isManual || !rowData.isEditable"
                  :label="{
                    text: Translator.trans('city')
                  }"
                  :model-value="rowData.submitterCity"
                  @update:model-value="val => updateUserField(rowData.tokenId, 'submitterCity', val)"
                />
              </div>
            </div>
            <div class="align-top u-4-of-9 mt-4">
              <div class="px-3 py-1 mb-3 bg-surface-medium flex w-10">
                <p
                  :id="`userToken:${rowData.tokenId}`"
                  class="m-0"
                >
                  {{ rowData.token }}
                </p>
                <button
                  type="button"
                  class="btn-icns ml-0.5 my-0"
                  :aria-label="Translator.trans('clipboard.copy_to')"
                  @click="copyTokenToClipboard(rowData.tokenId)"
                >
                  <i
                    class="fa fa-copy"
                    aria-hidden="true"
                  />
                </button>
              </div>
              <div v-if="rowData.usedEmailAddress">
                <strong :id="`emailSent:${rowData.tokenId}`">
                  {{ Translator.trans('following.email.sent') }}
                </strong>
                <p :aria-labelledby="`emailSent:${rowData.tokenId}`">
                  {{ rowData.usedEmailAddress }}
                </p>
              </div>
              <dp-text-area
                :id="`note:${rowData.tokenId}`"
                class="mb-3"
                :disabled="!rowData.isEditable"
                :label="Translator.trans('memo')"
                :maxlength="rowData.isEditable ? '1000' : false"
                :value="rowData.note"
                @input="val => updateUserField(rowData.tokenId, 'note', val)"
              />
              <dp-button-row
                v-if="rowData.isEditable"
                primary
                secondary
                @primary-action="dpValidateAction('saveEditAuthorisedUser', () => saveEditAuthorisedUser({ statementId: rowData.statementId, tokenId: rowData.tokenId }), false)"
                @secondary-action="toggleIsRowEditable({ id: rowData.tokenId, isEditable: false })"
              />
              <div
                v-else
                class="text-right"
              >
                <dp-button
                  :text="Translator.trans('edit')"
                  @click="toggleIsRowEditable({ id: rowData.tokenId })"
                />
              </div>
            </div>
          </div>
        </span>
      </template>
    </dp-data-table-extended>
  </div>
</template>

<script>
import {
  DpAccordion,
  dpApi,
  DpButton,
  DpButtonRow,
  DpContextualHelp,
  DpDataTableExtended,
  DpInput,
  dpRpc,
  DpTextArea,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'AuthorizedUsersList',

  components: {
    DpAccordion,
    DpButton,
    DpButtonRow,
    DpContextualHelp,
    DpDataTableExtended,
    DpInput,
    DpTextArea,
  },

  mixins: [dpValidateMixin],

  props: {
    procedureId: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      consultationTokens: [],
      headerFields: [
        { field: 'submitterName', label: Translator.trans('name') },
        { field: 'submitterEmailAddress', label: Translator.trans('email') },
        { field: 'token', label: Translator.trans('access.token') },
        { field: 'note', label: Translator.trans('memo') },
      ],
      isLoading: false,
      isSaving: false,
      localUsers: {},
      newUser: {
        note: '',
        submitterName: '',
        submitterEmailAddress: '',
        submitterStreet: '',
        submitterHouseNumber: '',
        submitterPostalCode: '',
        submitterCity: '',
        externId: '',
        statementId: '',
      },
      selectedRow: '',
      sendTokenBy: 'email',
      showCreateForm: false,
      sortOptions: {
        direction: 'asc',
        key: 'submitterName',
      },
      statements: [],
    }
  },

  computed: {
    exportRoute () {
      return Routing.generate('dplan_admin_procedure_authorized_users_export', {
        procedureId: this.procedureId,
        sort: this.sortOptions,
      })
    },

    // Needed to be able to pass as tableItems to DpDataTableExtended
    tokens () {
      return [...this.consultationTokens]
    },

    user () {
      return tokenId => {
        const currentUser = this.tokens.find(user => user.tokenId === tokenId)
        return currentUser ? { ...currentUser } : null
      }
    },
  },

  methods: {
    abortCreate () {
      this.resetCreateForm()
      this.closeCreateForm()
    },

    updateUserField (tokenId, fieldName, value) {
      const user = this.localUsers.find(user => user.tokenId === tokenId)

      if (user) {
        user[fieldName] = value
      }
    },

    copyTokenToClipboard (tokenId) {
      const range = document.createRange()
      range.selectNode(document.getElementById('userToken:' + tokenId))
      window.getSelection().removeAllRanges()
      window.getSelection().addRange(range)
      document.execCommand('copy')
      window.getSelection().removeAllRanges()
      dplan.notify.notify('info', Translator.trans('access.token.copied'))
    },

    closeCreateForm () {
      this.showCreateForm = false
    },

    createToken () {
      this.isLoading = true
      this.isSaving = true
      const { note, submitterName, submitterEmailAddress, submitterStreet, submitterHouseNumber, submitterPostalCode, submitterCity } = this.newUser
      const params = {
        note,
        submitterName,
        submitterEmailAddress,
        submitterStreet,
        submitterHouseNumber,
        submitterPostalCode,
        submitterCity,
      }

      return dpRpc('consultationToken.manual.create', params)
        .then(response => {
          if (response.status === 200) {
            this.resetCreateForm()
            this.fetchInitialData()
            dplan.notify.notify('confirm', Translator.trans('confirm.authorization.created'))
          }
        })
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.generic'))
        })
        .finally(() => { this.isSaving = false })
    },

    fetchConsultationTokens () {
      const url = Routing.generate('api_resource_list', { resourceType: 'ConsultationToken' })
      const params = {
        include: 'statement',
        fields: {
          Statement: [
            'anonymous',
            'authorName',
            'submitterName',
            'submitterEmailAddress',
            'submitterStreet',
            'submitterHouseNumber',
            'submitterPostalCode',
            'submitterCity',
            'isManual',
          ].join(),
        },
      }
      return dpApi.get(url, params)
        .then(response => {
          this.consultationTokens = [...response.data.data].map(token => {
            if (token.relationships && token.relationships.statement) {
              const statement = response.data.included.find(el => el.id === token.relationships.statement.data.id) || null
              if (statement) {
                token = {
                  ...statement.attributes,
                  ...token.attributes,
                  tokenId: token.id,
                  isEditable: false,
                  statementId: statement.id,
                }
              }
            }
            return token
          })
          this.isLoading = false
        })
    },

    fetchInitialData () {
      if (!this.isLoading) {
        this.isLoading = true
      }

      this.fetchConsultationTokens()
        .then(() => { this.localUsers = [...this.tokens] })
        .then(() => { this.isLoading = false })
    },

    resetCreateForm () {
      Object.keys(this.newUser).forEach(key => {
        this.newUser[key] = ''
      })
      this.resetValidity()
    },

    resetValidity () {
      const inputsWithErrors = this.$el.querySelector('[data-dp-validate]').querySelectorAll('.is-invalid')
      Array.from(inputsWithErrors).forEach(input => {
        input.classList.remove('is-invalid')
      })
    },

    saveEditAuthorisedUser (args) {
      const token = this.tokens.find(el => el.tokenId === args.tokenId)
      this.updateToken(args.tokenId)

      if (token.isManual) {
        this.updateStatement(args.statementId)
      }
      this.toggleIsRowEditable({ id: args.tokenId, isEditable: false })
    },

    toggleIsRowEditable ({ id: tokenId, isEditable = true }) {
      const token = this.tokens.find(el => el.tokenId === tokenId)
      if (token) {
        token.isEditable = isEditable
      }
    },

    setSortOptions (sortOrder) {
      this.sortOptions = sortOrder
    },

    updateStatement (statementId) {
      const user = this.localUsers.find(el => el.statementId === statementId)
      const statementAttributes = {
        submitterName: user.submitterName,
        submitterStreet: user.submitterStreet,
        submitterHouseNumber: user.submitterHouseNumber,
        submitterPostalCode: user.submitterPostalCode,
        submitterCity: user.submitterCity,
      }
      if (user.submitterEmailAddress) {
        statementAttributes.submitterEmailAddress = user.submitterEmailAddress
      }
      const payload = {
        data: {
          id: statementId,
          type: 'Statement',
          attributes: statementAttributes,
        },
      }
      const url = Routing.generate('api_resource_update', { resourceType: 'Statement', resourceId: statementId })

      return dpApi.patch(url, {}, payload)
        .then(() => {
          this.fetchInitialData()
          dplan.notify.notify('confirm', Translator.trans('confirm.entry.updated'))
        })
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.generic'))
        })
    },

    updateToken (tokenId) {
      const user = this.localUsers.find(el => el.tokenId === tokenId)
      const token = {
        data: {
          id: tokenId,
          type: 'ConsultationToken',
          attributes: {
            note: user.note,
          },
        },
      }

      const url = Routing.generate('api_resource_update', { resourceType: 'ConsultationToken', resourceId: tokenId })

      return dpApi.patch(url, {}, token)
        .then(response => {
          if (response.status === 204) {
            this.selectedRow = ''
            if (!user.isManual) {
              dplan.notify.notify('confirm', Translator.trans('confirm.entry.updated'))
            }
          }
        })
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.generic'))
        })
    },
  },

  mounted () {
    this.fetchInitialData()
  },
}
</script>
