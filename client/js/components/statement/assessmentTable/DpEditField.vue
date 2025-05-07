<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dl class="u-pt-0_25">
    <!--@improve T15132-->
    <dt
      v-if="label !== ''"
      class="layout__item weight--bold u-pb-0_25"
      :class="[labelGrid]"
      :data-cy="label">
      {{ Translator.trans(label) }}:
    </dt><!--
   --><dd
        v-if="false === readonly"
        class="c-edit-field layout__item"
        data-cy="editingEnabled"
        :class="[(editingEnabled || loading) ? 'is-editing': '', inputGrid, {'u-ml-0': noMargin, 'u-pl-0': noMargin}]">
        <!-- Editing -->
        <div
          class="c-edit-field__editing"
          v-if="editable && editingEnabled">
          <slot name="edit" />
        </div>
        <!-- Displaying value in non-edit mode, also toggles edit mode -->
        <div
          :class="{'cursor-pointer': editable}"
          :title="Translator.trans('edit.entity', { entity: translatedLabel })"
          @click="toggleEditing"
          v-if="!editable || !editingEnabled">
          <slot name="display" />
        </div>
        <!-- Edit Trigger -->
        <div
          class="c-edit-field__trigger"
          :class="{ 'block': persistIcons }">
          <dp-loading
            v-if="loading"
            hide-label />
          <template v-else>
            <template v-if="editable && editingEnabled">
              <button
                type="button"
                :title="Translator.trans('save')"
                class="btn--blank o-link--default"
                @click="save"
                data-cy="saveField">
                <i
                  aria-hidden="true"
                  class="fa fa-check" />
              </button>
              <button
                type="button"
                :title="Translator.trans('reset')"
                class="btn--blank o-link--default"
                @click="reset">
                <i
                  aria-hidden="true"
                  class="fa fa-times" />
              </button>
            </template>
            <button
              type="button"
              data-cy="toggleEditing"
              :disabled="!editable"
              v-if="false === editingEnabled"
              :title="editable ? Translator.trans('edit.entity', {entity: translatedLabel}) : Translator.trans('locked.title')"
              class="btn--blank o-link--default"
              @click="toggleEditing">
              <i
                aria-hidden="true"
                class="fa fa-pencil" />
            </button>
          </template>
        </div>
    </dd>
    <dd
      v-else
      class="c-edit-field layout__item"
      :class="[inputGrid, {'u-ml-0': noMargin, 'u-pl-0': noMargin}]">
      <slot name="display" />
    </dd>
  </dl>
</template>

<script>
import { DpLoading } from '@demos-europe/demosplan-ui'
import { mapMutations } from 'vuex'

export default {
  name: 'DpEditField',

  components: {
    DpLoading
  },

  props: {
    /*
     *  Is there the overall possibility to edit the item?
     */
    editable: {
      required: false,
      type: Boolean,
      default: true
    },

    //  Sets the label and some titles on buttons
    label: {
      required: true,
      type: String
    },

    labelGridCols: {
      required: false,
      type: Number,
      default: 2
    },

    readonly: {
      required: false,
      type: Boolean,
      default: false
    },

    noMargin: {
      required: false,
      type: Boolean,
      default: false
    },

    persistIcons: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  emits: [
    'reset',
    'save',
    'toggleEditing'
  ],

  data () {
    return {
      //  Is the item currently in editing mode (vs. just displaying its contents)?
      editingEnabled: false,
      loading: false
    }
  },

  computed: {
    /*
     *  Passing translated strings into trans params does not work,
     *  eg. Translator.trans('edit.entity', { entity: Translator.trans(label) })
     *  Maybe because they are not evaluated in order of appearance?
     *  Anyhow, moving the param translation to `computed` "solves" this.
     */
    translatedLabel () {
      return Translator.trans(this.label)
    },

    labelGrid () {
      // @improve T15132
      return 'u-' + this.labelGridCols + '-of-12'
    },

    inputGrid () {
      return 'u-' + (12 - this.labelGridCols) + '-of-12'
    }
  },

  watch: {
    /*
     * When `editable` being set to false from outside, editing is also being disabled.
     */
    editable: {
      handler (newVal) {
        if (newVal === false) {
          this.editingEnabled = false
        }
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    }
  },

  methods: {
    ...mapMutations('AssessmentTable', [
      'setRefreshButtonVisibility'
    ]),

    reset () {
      this.$emit('reset')
    },

    toggleEditing () {
      if (!this.editable) {
        return
      }
      this.editingEnabled = !this.editingEnabled
      this.$emit('toggleEditing', this.editingEnabled)
    },

    save () {
      this.setRefreshButtonVisibility(true)
      this.loading = true
      this.$emit('save')
    }
  },

  mounted () {
    this.$root.$on('saveSuccess', () => {
      this.loading = false
      this.editingEnabled = false
    })
  }
}
</script>
