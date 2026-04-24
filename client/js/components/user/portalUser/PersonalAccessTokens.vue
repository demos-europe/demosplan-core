<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="u-pt-0_5">
    <p>{{ Translator.trans('personal.access.tokens.intro') }}</p>

    <section
      v-if="freshToken"
      class="u-mt space-stack-s"
    >
      <div
        class="border border-warning u-pv-0_5 u-ph"
        role="alert"
      >
        <p class="u-mb-0_5">
          <strong>{{ Translator.trans('personal.access.tokens.new.shown.once') }}</strong>
        </p>
        <code class="break-all u-pv-0_25 u-ph-0_25 bg-surface u-inline-block">{{ freshToken }}</code>
        <dp-button-row
          class="u-mt-0_5"
          :primary-text="Translator.trans('copy.to.clipboard')"
          primary
          @primary-action="copyFresh"
        />
      </div>
    </section>

    <h3 class="u-mt u-mb-0_5">
      {{ Translator.trans('personal.access.tokens.create.heading') }}
    </h3>

    <form
      class="space-stack-s"
      @submit.prevent="create"
    >
      <dp-input
        id="pat-name"
        v-model="form.name"
        class="u-2-of-3"
        :label="{ text: Translator.trans('personal.access.tokens.name') }"
        name="name"
        required
      />

      <fieldset class="u-mt-0_5">
        <legend class="font-size-smaller u-mb-0_25">
          {{ Translator.trans('personal.access.tokens.scopes') }}
        </legend>
        <ul class="list-reset">
          <li
            v-for="(definition, scope) in availableScopes"
            :key="scope"
            class="u-pv-0_25"
          >
            <label>
              <input
                type="checkbox"
                :value="scope"
                :checked="form.scopes.includes(scope)"
                @change="toggleScope(scope)"
              >
              <code>{{ scope }}</code> — {{ definition.label }}
            </label>
          </li>
        </ul>
      </fieldset>

      <div class="u-mt-0_5">
        <label for="pat-lifetime">
          {{ Translator.trans('personal.access.tokens.lifetime') }}
        </label>
        <select
          id="pat-lifetime"
          v-model.number="form.lifetimeDays"
          class="u-ml-0_25"
        >
          <option
            v-for="option in lifetimeOptions"
            :key="option"
            :value="option"
          >
            {{ option }} {{ Translator.trans('days') }}
          </option>
        </select>
      </div>

      <div class="u-mt-0_5">
        <label for="pat-procedure-ids">
          {{ Translator.trans('personal.access.tokens.procedure.allowlist') }}
        </label>
        <dp-input
          id="pat-procedure-ids"
          v-model="procedureIdsRaw"
          class="u-2-of-3"
          :label="{ text: Translator.trans('personal.access.tokens.procedure.allowlist.hint'), hidden: true }"
          name="procedureIds"
          :placeholder="Translator.trans('personal.access.tokens.procedure.allowlist.placeholder')"
        />
      </div>

      <dp-button-row
        class="u-mt-0_5"
        primary
        :primary-text="Translator.trans('personal.access.tokens.create')"
        :disabled="!canSubmit"
        @primary-action="create"
      />
      <p
        v-if="formError"
        class="color-error u-mt-0_25"
        role="alert"
      >
        {{ formError }}
      </p>
    </form>

    <h3 class="u-mt u-mb-0_5">
      {{ Translator.trans('personal.access.tokens.existing.heading') }}
    </h3>

    <p
      v-if="!tokens.length"
      class="color-subtle"
    >
      {{ Translator.trans('personal.access.tokens.none') }}
    </p>

    <ul
      v-else
      class="list-reset border-t"
    >
      <li
        v-for="token in tokens"
        :key="token.id"
        class="border-b u-pv-0_5"
      >
        <div class="layout layout--flush">
          <div class="layout__item u-2-of-3">
            <strong>{{ token.name }}</strong>
            <code class="u-ml-0_25 color-subtle">{{ token.prefix }}…</code>
            <div class="font-size-smaller color-subtle">
              <span v-if="token.revoked">{{ Translator.trans('personal.access.tokens.status.revoked') }}</span>
              <span v-else-if="token.expired">{{ Translator.trans('personal.access.tokens.status.expired') }}</span>
              <span v-else>{{ Translator.trans('personal.access.tokens.status.active') }}</span>
              · {{ Translator.trans('personal.access.tokens.expires') }}: {{ formatDate(token.expiresAt) }}
              · {{ Translator.trans('personal.access.tokens.lastUsed') }}:
              {{ token.lastUsedAt ? formatDate(token.lastUsedAt) : Translator.trans('personal.access.tokens.never') }}
            </div>
            <div class="font-size-smaller">
              {{ token.scopes.join(', ') }}
            </div>
          </div>
          <div class="layout__item u-1-of-3 text-right">
            <button
              v-if="!token.revoked && !token.expired"
              type="button"
              class="btn btn--secondary"
              @click="revoke(token)"
            >
              {{ Translator.trans('personal.access.tokens.revoke') }}
            </button>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script>
import { DpButtonRow, DpInput, dpApi } from '@demos-europe/demosplan-ui'

const MIN_LIFETIME = 7
const MAX_LIFETIME = 365

export default {
  name: 'PersonalAccessTokens',

  components: {
    DpButtonRow,
    DpInput,
  },

  data () {
    return {
      availableScopes: {},
      tokens: [],
      freshToken: null,
      form: {
        name: '',
        scopes: [],
        lifetimeDays: 90,
      },
      procedureIdsRaw: '',
      formError: null,
      lifetimeOptions: [30, 60, 90, 180, 365],
    }
  },

  computed: {
    canSubmit () {
      return this.form.name.trim().length > 0
        && this.form.scopes.length > 0
        && this.form.lifetimeDays >= MIN_LIFETIME
        && this.form.lifetimeDays <= MAX_LIFETIME
    },
  },

  mounted () {
    this.loadScopes()
    this.loadTokens()
  },

  methods: {
    loadScopes () {
      return dpApi.get(Routing.generate('DemosPlan_user_pat_scopes'))
        .then(({ data }) => {
          this.availableScopes = data.scopes || {}
        })
    },

    loadTokens () {
      return dpApi.get(Routing.generate('DemosPlan_user_pat_list'))
        .then(({ data }) => {
          this.tokens = data.tokens || []
        })
    },

    toggleScope (scope) {
      const index = this.form.scopes.indexOf(scope)
      if (index === -1) {
        this.form.scopes.push(scope)
      } else {
        this.form.scopes.splice(index, 1)
      }
    },

    create () {
      if (!this.canSubmit) return
      this.formError = null
      const procedureIds = this.parseProcedureIds()
      const payload = {
        name: this.form.name.trim(),
        scopes: [...this.form.scopes],
        lifetimeDays: this.form.lifetimeDays,
        ...(procedureIds.length ? { procedureIds } : {}),
      }
      return dpApi.post(Routing.generate('DemosPlan_user_pat_create'), {}, { data: payload })
        .then(({ data }) => {
          this.freshToken = data.plaintext
          this.form.name = ''
          this.form.scopes = []
          this.form.lifetimeDays = 90
          this.procedureIdsRaw = ''
          return this.loadTokens()
        })
        .catch((error) => {
          this.formError = error?.response?.data?.error
            || Translator.trans('personal.access.tokens.create.error')
        })
    },

    revoke (token) {
      if (!window.confirm(Translator.trans('personal.access.tokens.revoke.confirm', { name: token.name }))) {
        return
      }
      return dpApi.delete(Routing.generate('DemosPlan_user_pat_revoke', { id: token.id }))
        .then(() => this.loadTokens())
    },

    copyFresh () {
      if (!this.freshToken) return
      const text = this.freshToken
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
      }
    },

    parseProcedureIds () {
      return this.procedureIdsRaw
        .split(/[\s,;]+/)
        .map(id => id.trim())
        .filter(id => id.length > 0)
    },

    formatDate (iso) {
      if (!iso) return ''
      const date = new Date(iso)
      return date.toLocaleString()
    },
  },
}
</script>
