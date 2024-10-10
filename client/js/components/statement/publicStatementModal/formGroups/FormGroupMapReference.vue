<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <fieldset
    v-if="hasPermission('field_statement_location')"
    :required="required"
    role="radiogroup"
    aria-labelledby="statementMapReference"
    aria-required="true"
    id="locationFieldset">
    <p
      :class="prefixClass('c-statement__formblock-title weight--bold u-mt u-mb-0')"
      id="statementMapReference">
      {{ Translator.trans('statement.map.reference') }}
      <span
        v-if="required"
        aria-hidden="true">*</span>
    </p>
    <div
      v-if="isMapEnabled && hasPermission('area_map_participation_area')"
      :class="[
        isLocationSelected ? prefixClass('bg-color--grey-light-2') : '',
        prefixClass('c-statement__formblock layout__item sm:h-8 u-3-of-10 u-1-of-1-palm'),
        highlighted.location ? prefixClass('animation--bg-highlight-grey--light-2') : ''
      ]"
      ref="mapStatementRadio">
      <dp-radio
        id="locationPoint"
        name="r_location"
        class="u-mb-0_25"
        data-cy="formGroupMap:statementMapReference"
        :checked="isLocationSelected"
        :disabled="disabled"
        @change="() => { const location = (statement.r_location_priority_area_key !== '' ? 'priority_area' :'point'); setStatementData({r_location: 'point', location_is_set: location})}"
        :label="{
          text: Translator.trans('statement.map.reference.add_on_map')
        }"
        value="point" />

      <a
        href="#"
        class="o-link--default"
        data-cy="formGroupMap:procedureDetailsMap"
        v-show="isLocationSelected"
        @click.prevent="gotoTab('procedureDetailsMap')">
        <template v-if="statement.r_location_point !== ''">
          {{ Translator.trans('location.marked.yours') }}
        </template>
        <template v-else-if="statement.r_location_priority_area_key !== ''">
          {{ Translator.trans('potential.areas') }} {{ statement.r_location_priority_area_key }}
        </template>
        <template v-else-if="statement.r_location_geometry !== ''">
          {{ Translator.trans('statement.map.drawing.yours') }}
        </template>
        <template v-else>
          {{ Translator.trans('map.to') }}
        </template>
      </a>
    </div>

    <div
      v-if="hasPermission('field_statement_county')"
      :class="[
        statement.r_location === 'county' ? 'bg-color--grey-light-2' : '',
        'c-statement__formblock layout__item sm:h-8 u-3-of-10 u-1-of-1-palm'
      ]">
      <dp-radio
        id="locationcounty"
        :label="{
          text: Translator.trans('statement.map.reference.choose_county')
        }"
        name="r_location"
        class="u-mb-0_25"
        :checked="statement.r_location === 'county'"
        :disabled="disabled"
        @change="() => { setStatementData({ r_location: 'county', location_is_set: 'county'}) }"
        value="county" />
      <select
        v-if="statement.r_location === 'county'"
        id="r_county"
        name="r_county"
        :required="statement.r_location === 'county'"
        :class="prefixClass('o-form__control-select')"
        ref="locationCountySelect"
        @change="val => setStatementData({r_county: val.target.value})"
        :value="statement.r_county">
        <option
          v-for="county in counties"
          :selected="county.selected"
          :value="county.value"
          :key="county.value">
          {{ county.label }}
        </option>
      </select>
    </div>

    <div
      :class="[
        statement.r_location === 'notLocated' ? prefixClass('bg-color--grey-light-2') : '',
        loggedIn ? prefixClass('u-1-of-3') : prefixClass('u-2-of-10'),
        prefixClass('c-statement__formblock layout__item sm:h-8 u-1-of-1-palm')
      ]">
      <dp-radio
        id="locationNone"
        :label="{
          text: Translator.trans('statement.map.no_reference')
        }"
        name="r_location"
        class="u-mb-0_25"
        data-cy="formGroupMap:notLocated"
        :checked="statement.r_location === 'notLocated'"
        @change="() => { setStatementData({r_location: 'notLocated', location_is_set: 'notLocated'}) }"
        value="notLocated" />
    </div>
  </fieldset>
</template>

<script>
import { mapMutations, mapState } from 'vuex'
import { DpRadio } from '@demos-europe/demosplan-ui'
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupMapReference',

  components: {
    DpRadio
  },

  mixins: [formGroupMixin],

  props: {
    counties: {
      type: Array,
      required: false,
      default: () => []
    },

    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    loggedIn: {
      type: Boolean,
      required: false,
      default: false
    },

    isMapEnabled: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  emits: [
    'statement-modal:goto-tab'
  ],

  computed: {
    ...mapState('PublicStatement', ['activeActionBoxTab', 'highlighted']),

    isLocationSelected () {
      return this.statement.r_location === 'point' || this.statement.r_location === 'priorityAreaType'
    }
  },

  methods: {
    ...mapMutations('PublicStatement', ['update']),

    gotoTab (tabName) {
      this.update({ key: 'activeActionBoxTab', val: 'draw' })
      this.$root.$emit('statement-modal:goto-tab', tabName)
    }
  }
}
</script>
