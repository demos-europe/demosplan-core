<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <portal to="vueModals">
    <dp-modal
      ref="assignModal"
      @modal:toggled="handleClose"
      content-classes="u-1-of-2">
      <!-- modal header -->
      <template v-slot:header>
        {{ Translator.trans('assignment.entity.assign.to.other', { entity: Translator.trans(entityType) }) }}
      </template>

      <!--the height of the div below (220px) is needed because the multiselect dropdown will cause the modal to have a scroll when opened (see: https://github.com/shentao/vue-multiselect/issues/723). Once this github issue is solved and dropdown will overlay modal content, the height class and button top margin can be removed.
      ATTENTION! To be able to reduce the dropdown's size, I had to change styling in _multiselect.scss (overflow and max-height props in dropdown__content). It may cause the comeback of T11129 bug -->

      <!-- modal content -->
      <div class="h-11">
        <h3>{{ Translator.trans('user.choose') }}:</h3>
        <div>
          <dp-multiselect
            :id="`r_${entityId}`"
            v-model="selected"
            :allow-empty="false"
            class="u-n-ml-0_25"
            :custom-label="option => `${option.name} ${option.id === currentUserId ? '(Sie)' : ''}`"
            :name="`r_${entityId}`"
            :options="[{ id: '', name: '-'}, ...users]"
            :max-height="150"
            track-by="id">
            <template v-slot:option="{ props }">
              {{ props.option.name }} {{ props.option.id === currentUserId ? ` (Sie)` : '' }}
            </template>
          </dp-multiselect>
        </div>
        <dp-button
          class="u-mt float-right"
          :busy="loading"
          :text="Translator.trans('assignment.generic.assign.to.chosen', { entity: Translator.trans(entityType) })"
          @click="assignEntity" />
      </div>
    </dp-modal>
  </portal>
</template>

<script>
import { checkResponse, DpButton, DpModal, DpMultiselect } from '@demos-europe/demosplan-ui'
import { mapGetters, mapMutations } from 'vuex'

export default {
  name: 'AssignEntityModal',

  components: {
    DpButton,
    DpModal,
    DpMultiselect
  },

  props: {
    authorisedUsers: {
      required: false,
      type: Array,
      default: () => ([])
    },

    currentUserId: {
      required: true,
      type: String
    },

    procedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      entityId: '',
      entityType: '',
      parentStatementId: '',
      loading: false,
      users: this.authorisedUsers,
      selected: '',
      initialAssigneeId: ''
    }
  },

  computed: {
    ...mapGetters('AssessmentTable', [
      'assignEntityModal'
    ]),

    actionParams () {
      return this.entityType === 'statement'
        ? { statementId: this.entityId, assigneeId: this.selected.id }
        : this.entityType === 'fragment'
          ? { fragmentId: this.entityId, statementId: this.parentStatementId, ignoreLastClaimed: false, assigneeId: this.selected.id }
          : {}
    },

    confirmationText () {
      return this.entityType === 'statement' ? 'assignment.generic.assign.to.other.confirmation.statement' : 'assignment.generic.assign.to.other.confirmation.fragment'
    }
  },

  methods: {
    ...mapMutations('AssessmentTable', [
      'setModalProperty'
    ]),

    assignEntity () {
      //  Trigger confirm
      if (this.initialAssigneeId && this.initialAssigneeId !== '' && this.initialAssigneeId !== this.currentUserId) {
        if (dpconfirm(Translator.trans(this.confirmationText)) === false) {
          return
        }
      }

      this.loading = true

      //  Fire action from store
      this.$store.dispatch(`${this.capitalizeFirstLetter(this.entityType)}/setAssigneeAction`, this.actionParams)
        .then(checkResponse)
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.api.generic'))
        })
        .finally(() => {
          this.toggleModal()
          this.loading = false
        })
    },

    capitalizeFirstLetter (str) {
      return str.charAt(0).toUpperCase() + str.slice(1)
    },

    handleClose (isOpen) {
      if (!isOpen) {
        this.setModalProperty({ prop: 'assignEntityModal', val: { ...this.assignEntityModal, show: false } })
      }
    },

    toggleModal () {
      if (this.assignEntityModal) {
        this.entityId = this.assignEntityModal.entityId
        this.entityType = this.assignEntityModal.entityType
        const initialUser = this.users.find(user => user.id === this.assignEntityModal.initialAssigneeId)
        this.selected = initialUser || { id: '', name: '-' }
        this.initialAssigneeId = this.assignEntityModal.initialAssigneeId
        this.parentStatementId = this.assignEntityModal.parentStatementId ? this.assignEntityModal.parentStatementId : ''
      }

      this.$refs.assignModal.toggle()
    },

    setInitUsers () {
      this.users = this.authorisedUsers
      this.users.sort((a, b) => a.name.localeCompare(b.name, 'de', { sensitivity: 'base' }))
    }
  },

  created () {
    this.setInitUsers()
  },

  mounted () {
    this.$nextTick(() => {
      this.toggleModal()
    })
  }

}
</script>
