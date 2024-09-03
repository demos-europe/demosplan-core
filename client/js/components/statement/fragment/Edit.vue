<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <form
    action="#"
    method="post">
    <!-- save elements and paragraphs -->
    <input
      type="hidden"
      :name="fragmentId+':r_element'"
      :value="elementId">
    <input
      type="hidden"
      :name="fragmentId+':r_paragraph'"
      :value="paragraphId">
    <input
      name="_token"
      type="hidden"
      :value="csrfToken">

    <!-- consideration advice, vote advice -->
    <fieldset class="layout__item u-1-of-2 u-pl-0">
      <legend
        class="sr-only"
        v-text="Translator.trans('fragment.voteAdvice')" />
      <template v-if="hasPermission('feature_statements_fragment_consideration_advice')">
        <label
          class="u-mb-0_25 u-mt-0"
          :for="fragmentId+':r_considerationAdvice'">
          {{ Translator.trans('fragment.consideration') }}
        </label>
        <dp-editor
          ref="tiptap"
          :procedure-id="procedureId"
          v-model="considerationAdvice"
          :entity-id="fragmentId"
          :hidden-input="fragmentId + ':r_considerationAdvice'" />
      </template>

      <template v-if="hasPermission('feature_statements_fragment_advice')">
        <label
          class="u-mb-0_25"
          :for="fragmentId+'r_vote_advice'">
          {{ Translator.trans('fragment.voteAdvice') }}
        </label>
        <dp-multiselect
          v-model="voteAdvice"
          :allow-empty="false"
          label="name"
          :options="computedAdviceValues"
          track-by="value">
          <template v-slot:option="{ props }">
            {{ props.option.name }}
          </template>
        </dp-multiselect>
        <input
          type="hidden"
          :value="voteAdvice.title"
          :name="fragmentId+':r_vote_advice'"
          :id="fragmentId+':r_vote_advice'">
      </template>

      <div class="u-mt space-inline-s">
        <dp-button
          :busy="saving === 'saveButton'"
          :text="Translator.trans('save')"
          @click="save('saveButton')" />
        <button
          type="reset"
          class="btn btn--secondary"
          @click="reset"
          v-text="Translator.trans('discard.changes')" />
      </div>
    </fieldset><!--

    Complete reviewing fragment, assign back to planner
 --><fieldset class="layout__item u-1-of-2">
      <legend
        class="sr-only"
        v-text="Translator.trans('fragment.update.complete.button')" />
      <div
        v-if="hasPermission('feature_statements_fragment_update_complete')"
        class="o-box u-p-0_5 u-mt-1_5">
        <div class="weight--bold">
          {{ Translator.trans('fragment.update.complete') }}
        </div>

        <p class="lbl__hint">
          {{ Translator.trans('fragment.update.complete.hint') }}
        </p>

        <dp-button
          class="u-ml-0"
          :busy="saving === 'notifyButton'"
          :text="Translator.trans('fragment.update.complete.button')"
          @click="save('notifyButton')" />
      </div>
    </fieldset>
  </form>
</template>

<script>
import { checkResponse, DpButton, DpEditor, DpMultiselect, makeFormPost } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpFragmentEdit',

  components: {
    DpButton,
    DpEditor,
    DpMultiselect
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    fragmentId: {
      required: true,
      type: String
    },

    procedureId: {
      required: true,
      type: String
    },

    considerationAdviceInitial: {
      required: false,
      default: '',
      type: String
    },

    voteAdviceInitial: {
      required: false,
      default: '',
      type: String
    },

    adviceValues: {
      required: false,
      type: Object,
      default: () => ({})
    },

    elementId: {
      required: false,
      type: String,
      default: ''
    },

    paragraphId: {
      required: false,
      type: String,
      default: ''
    }
  },

  data () {
    return {
      voteAdvice: Object.entries(this.adviceValues).reduce((acc, val) => [...acc, { value: val[1], name: Translator.trans(val[1]), title: val[0] }], [{ value: '', title: '', name: '-' }]).find(el => el.title === this.voteAdviceInitial),
      considerationAdvice: this.considerationAdviceInitial ? this.considerationAdviceInitial : 'k.A.',
      saving: ''
    }
  },

  computed: {
    computedAdviceValues () {
      return Object.entries(this.adviceValues).reduce((acc, val) => [...acc, { value: val[1], name: Translator.trans(val[1]), title: val[0] }], [{ value: '', title: '', name: '-' }])
    }
  },

  methods: {
    reset () {
      this.voteAdvice = this.voteAdviceInitial !== '' ? { val: this.adviceValues[this.voteAdviceInitial], name: Translator.trans(this.adviceValues[this.voteAdviceInitial]), title: this.voteAdviceInitial } : { value: '', title: '', name: '-' }
      this.considerationAdvice = this.considerationAdviceInitial ? this.considerationAdviceInitial : 'k.A.'
      this.$emit('closeEditMode')
    },
    save (button) {
      const form = $(this.$el).closest('form')

      //  Return if not inside a form
      if (form.length !== 1) {
        return
      }

      this.saving = button

      const saveData = form.serializeArray()

      if (button === 'notifyButton') {
        if (dpconfirm(Translator.trans('check.fragment.save')) === false) {
          this.saving = ''
          return
        } else {
          saveData.push({
            name: this.fragmentId + ':r_notify',
            value: 'r_notify'
          })
        }
      }

      // Under the hood this is an old post-request, though we have to transform the data
      const dataForRequest = {}
      saveData.forEach(el => {
        dataForRequest[el.name] = el.value
      })
      return makeFormPost(dataForRequest, Routing.generate('DemosPlan_statement_fragment_edit_reviewer_ajax', { fragmentId: this.fragmentId }))
        .then(checkResponse)
        .then(response => {
          /*
           *  If fragment has been reassigned to planners by clicking 'fragment.update.complete.button',
           *  remove respective item from DOM
           */
          if (button === 'notifyButton') {
            this.$root.$emit('fragment-reassigned', response.data)
          } else {
            this.$root.$emit('fragment-saved', response.data)

            //  Set this to new data
            this.considerationAdvice = response.data.considerationAdvice
            this.voteAdvice = response.data.voteAdvice || { name: '-', title: '', value: '' }
          }
        })
        .catch(err => {
          console.log(err)
        })
        .then(() => {
          this.saving = ''
        })
    }
  }
}
</script>
