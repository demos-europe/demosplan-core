<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <fieldset
    id="locationFieldset"
    :required="required"
    role="radiogroup"
    aria-labelledby="statementMapReference"
    aria-required="true"
  >
    <p
      id="statementMapReference"
      :class="prefixClass('weight--bold u-mt u-mb-0')"
    >
      {{ Translator.trans('statement.map.reference') }}
      <span
        v-if="required"
        aria-hidden="true"
      >*</span>
    </p>
    <div
      v-if="hasPermission('field_statement_location')"
      ref="mapStatementRadio"
      :class="[
        statement.location_is_set === 'county' ? prefixClass('bg-color--grey-light-2') : '',
        prefixClass('c-statement__formblock layout__item sm:h-8 u-1-of-1-palm u-3-of-10')
      ]"
    >
      <dp-radio
        id="locationcounty"
        :label="{
          text: Translator.trans('statement.map.reference.choose_county')
        }"
        name="r_location"
        :checked="statement.location_is_set === 'county'"
        value="county"
        @change="() => { setStatementData({ r_location: 'county', location_is_set: 'county' }) }"
      />
      <select
        v-if="statement.location_is_set === 'county'"
        id="r_county"
        ref="locationCountySelect"
        name="r_county"
        :class="prefixClass('o-form__control-select')"
        :value="statement.r_county"
        @change="val => setStatementData({r_county: val.target.value})"
      >
        <option
          v-for="county in counties"
          :key="county.value"
          :selected="county.selected"
          :value="county.value"
        >
          {{ county.label }}
        </option>
      </select>
    </div>

    <div
      :class="[
        statement.location_is_set === 'notLocated' ? prefixClass('bg-color--grey-light-2') : '',
        loggedIn ? prefixClass('u-1-of-3') : prefixClass('u-2-of-10'),
        prefixClass('c-statement__formblock layout__item sm:h-8 u-1-of-1-palm')
      ]"
    >
      <dp-radio
        id="locationNone"
        :label="{
          text: Translator.trans('statement.map.no_reference')
        }"
        name="r_location"
        :checked="statement.location_is_set === 'notLocated'"
        value="notLocated"
        @change="() => { setStatementData({r_location: 'notLocated', location_is_set: 'notLocated'}) }"
      />
    </div>
  </fieldset>
</template>

<script>
import { DpRadio } from '@demos-europe/demosplan-ui'
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupCountyReference',

  components: {
    DpRadio,
  },

  mixins: [formGroupMixin],

  props: {
    counties: {
      type: Array,
      required: false,
      default: () => [],
    },

    loggedIn: {
      type: Boolean,
      required: false,
      default: false,
    },

    isMapEnabled: {
      type: Boolean,
      required: false,
      default: false,
    },
  },
}
</script>
