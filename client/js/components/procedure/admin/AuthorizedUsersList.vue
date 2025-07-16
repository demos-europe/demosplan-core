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
      @item:toggle="showCreateForm = !showCreateForm">
      <div class="layout">
        <p class="layout__item u-mt-0_25">
          {{ Translator.trans('authorization.create.hint') }}
        </p>

        <div
          data-dp-validate="createToken"
          class="layout__item u-2-of-7">
          <dp-input
            id="submitterName"
            v-model="newUser.submitterName"
            class="u-mb-0_75"
            :label="{
              text: Translator.trans('name')
            }"
            required />
          <dp-input
            id="submitterEmailAddress"
            v-model="newUser.submitterEmailAddress"
            :label="{
              text: Translator.trans('email.address')
            }"
            type="email" />
        </div><!--

     --><div class="layout__item u-2-of-7">
          <div class="o-form__group u-mb-0_75">
            <dp-input
              id="submitterStreet"
              v-model="newUser.submitterStreet"
              class="o-form__group-item"
              :label="{
                text: Translator.trans('street')
              }" />
            <dp-input
              id="submitterHouseNumber"
              v-model="newUser.submitterHouseNumber"
              class="o-form__group-item"
              :size="5"
              :label="{
                text: Translator.trans('street.number.short')
              }" />
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
              :size="5" />
            <dp-input
              id="submitterCity"
              v-model="newUser.submitterCity"
              class="o-form__group-item"
              :label="{
                text: Translator.trans('city')
              }" />
          </div>
        </div><!--

     --><div class="layout__item u-3-of-7">
          <dp-text-area
            id="memo"
            :label="Translator.trans('memo')"
            maxlength="1000"
            v-model="newUser.note" />
        </div>

        <dp-button-row
          class="u-mt-0_5"
          :busy="isSaving"
          primary
          secondary
          @primary-action="dpValidateAction('createToken', createToken, false)"
          @secondary-action="abortCreate" />
      </div>
    </dp-accordion>

    <div class="u-mt-2">
      <a :href="exportRoute">
        <i
          class="fa fa-share-square u-pr-0_25"
          aria-hidden="true" />
        {{ Translator.trans('export') }}
      </a>
      <dp-contextual-help
        class="inline-block u-ml-0_25 u-mt-0_125"
        :text="Translator.trans('consultation.export.bulk.letter.explanation')" />
    </div>

    <dp-data-table-extended
      ref="dataTable"
      class="u-mb u-mt-0_5 max-w-full"
      :header-fields="headerFields"
      has-flyout
      :default-sort-order="{ direction: 1, key: 'submitterName' }"
      :is-loading="isLoading"
      is-expandable
      is-sortable
      :table-items="tokens"
      @updated:sortOrder="setSortOptions"
      track-by="tokenId">
      <template v-slot:submitterName="rowData">
        <div class="o-hellip__wrapper">
          <div
            v-tooltip="user(rowData.tokenId).submitterName"
            class="o-hellip--nowrap">
            {{ user(rowData.tokenId).submitterName }}
          </div>
        </div>
      </template>
      <template v-slot:submitterEmailAddress="rowData">
        <div class="o-hellip__wrapper">
          <div
            v-if="rowData.authorName && !rowData.anonymous"
            v-tooltip="rowData.submitterEmailAddress"
            class="o-hellip--nowrap">
            {{ user(rowData.tokenId).submitterEmailAddress }}
          </div>
          <div
            v-else
            class="o-hellip--nowrap">
            {{ Translator.trans('anonymous') }}
          </div>
        </div>
      </template>
      <template v-slot:note="rowData">
        <div
          v-tooltip="user(rowData.tokenId).note"
          class="o-hellip__wrapper max-w-[90%]">
          <span class="o-hellip--nowrap block">
            {{ user(rowData.tokenId).note }}
          </span>
        </div>
      </template>
      <template v-slot:expandedContent="rowData">
        <span data-dp-validate="saveEditAuthorisedUser">
          <div class="flex">
            <div class="align-top u-1-of-3">
              <div class="u-ph-0_75 u-pv-0_25 u-mb-0_75 bg-color--grey-light-2 flex w-10">
                <p
                  :id="`userToken:${rowData.tokenId}`"
                  class="u-m-0">
                  {{ rowData.token }}
                </p>
                <button
                  type="button"
                  class="btn-icns u-m-0"
                  :aria-label="Translator.trans('clipboard.copy_to')"
                  @click="copyTokenToClipboard(rowData.tokenId)">
                  <i
                    class="fa fa-copy"
                    aria-hidden="true" />
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
            </div>
            <div class="align-top u-1-of-3 u-ph-0_5">
              <dp-input
                :id="`name:${rowData.tokenId}`"
                :disabled="!rowData.isManual || !rowData.isEditable"
                :label="{
                  text: Translator.trans('name')
                }"
                required
                :value="rowData.submitterName"
                @input="val => localUsers.find(user => user.tokenId === rowData.tokenId).submitterName = val" />
              <div
                v-if="!rowData.authorName || rowData.anonymous"
                class="u-mt-0_75 u-mb-0_5">
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
                class="u-mt-0_75"
                :disabled="!rowData.isManual || !rowData.isEditable"
                :label="{
                  text: Translator.trans('email')
                }"
                type="email"
                :value="rowData.submitterEmailAddress"
                @input="val => localUsers.find(user => user.tokenId === rowData.tokenId).submitterEmailAddress = val" />
              <div class="o-form__group u-mb-0_5 u-mt-0_75">
                <dp-input
                  :id="`street:${rowData.tokenId}`"
                  class="o-form__group-item"
                  :disabled="!rowData.isManual || !rowData.isEditable"
                  :label="{
                    text: Translator.trans('street')
                  }"
                  :value="rowData.submitterStreet"
                  @input="val => localUsers.find(user => user.tokenId === rowData.tokenId).submitterStreet = val" />
                <dp-input
                  :id="`houseNumber:${rowData.tokenId}`"
                  class="o-form__group-item"
                  :disabled="!rowData.isManual || !rowData.isEditable"
                  :label="{
                    text: Translator.trans('street.number.short')
                  }"
                  :size="5"
                  :value="rowData.submitterHouseNumber"
                  @input="val => localUsers.find(user => user.tokenId === rowData.tokenId).submitterHouseNumber = val" />
              </div>
              <div class="o-form__group u-mb-0_5 u-mt-0_75">
                <dp-input
                  :id="`postalcode:${rowData.tokenId}`"
                  class="o-form__group-item"
                  :disabled="!rowData.isManual || !rowData.isEditable"
                  :label="{
                    text: Translator.trans('postalcode')
                  }"
                  pattern="^[0-9]{5}$"
                  :size="5"
                  :value="rowData.submitterPostalCode"
                  @input="val => localUsers.find(user => user.tokenId === rowData.tokenId).submitterPostalCode = val" />
                <dp-input
                  :id="`city:${rowData.tokenId}`"
                  class="o-form__group-item"
                  :disabled="!rowData.isManual || !rowData.isEditable"
                  :label="{
                    text: Translator.trans('city')
                  }"
                  :value="rowData.submitterCity"
                  @input="val => localUsers.find(user => user.tokenId === rowData.tokenId).submitterCity = val" />
              </div>
            </div>
            <div class="align-top u-1-of-3 u-pl-0_5">
              <dp-text-area
                class="u-mb-0_75"
                :disabled="!rowData.isEditable"
                :id="`note:${rowData.tokenId}`"
                :label="Translator.trans('memo')"
                :maxlength="rowData.isEditable ? '1000' : false"
                :value="rowData.note"
                @input="val => localUsers.find(user => user.tokenId === rowData.tokenId).note = val" />
              <dp-button-row
                v-if="rowData.isEditable"
                primary
                secondary
                @primary-action="dpValidateAction('saveEditAuthorisedUser', () => saveEditAuthorisedUser({ statementId: rowData.statementId, tokenId: rowData.tokenId }), false)"
                @secondary-action="toggleIsRowEditable({ id: rowData.tokenId, isEditable: false })" />
              <div
                v-else
                class="text-right">
                <dp-button
                  :text="Translator.trans('edit')"
                  @click="toggleIsRowEditable({ id: rowData.tokenId })" />
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
  dpValidateMixin
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
    DpTextArea
  },

  mixins: [dpValidateMixin],

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      consultationTokens: [],
      headerFields: [
        { field: 'submitterName', label: Translator.trans('name') },
        { field: 'submitterEmailAddress', label: Translator.trans('email') },
        { field: 'token', label: Translator.trans('access.token') },
        { field: 'note', label: Translator.trans('memo') }
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
        statementId: ''
      },
      selectedRow: '',
      sendTokenBy: 'email',
      showCreateForm: false,
      sortOptions: {
        direction: 'asc',
        key: 'submitterName'
      },
      statements: []
    }
  },

  computed: {
    exportRoute () {
      return Routing.generate('dplan_admin_procedure_authorized_users_export', {
        procedureId: this.procedureId,
        sort: this.sortOptions
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
    }
  },

  methods: {
    abortCreate () {
      this.resetCreateForm()
      this.closeCreateForm()
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
        submitterCity
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
            'isManual'
          ].join()
        }
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
                  statementId: statement.id
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
        submitterCity: user.submitterCity
      }
      if (user.submitterEmailAddress) {
        statementAttributes.submitterEmailAddress = user.submitterEmailAddress
      }
      const payload = {
        data: {
          id: statementId,
          type: 'Statement',
          attributes: statementAttributes
        }
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
            note: user.note
          }
        }
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
    }
  },

  mounted () {
    this.fetchInitialData()
  }
}
</script>
