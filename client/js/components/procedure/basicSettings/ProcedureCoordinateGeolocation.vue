<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="block u-mb-0_5 u-pv-0_5 border--top border--bottom flow-root">
    <p class="lbl__hint">
      {{ Translator.trans('explanation.geolocation') }}
    </p>

    <dp-loading v-if="loading" />

    <div
      v-else
      class="flex">
      <dp-input
        id="postalcode"
        data-cy="procedureCoordinate:postalCode"
        :disabled="readonly"
        :label="{
          text: Translator.trans('postalcode')
        }"
        name="r_locationPostCode"
        :value="locationPostalCode"
        width="w-9" />
      <dp-input
        id="locationName"
        class="u-ml-0_25"
        data-cy="procedureCoordinate:city"
        :disabled="readonly"
        :label="{
          text: Translator.trans('city')
        }"
        name="r_locationName"
        :value="locationName"
        width="w-9" />
      <dp-input
        id="municipalCode"
        class="u-ml-0_25"
        data-cy="procedureCoordinate:municipalCode"
        :disabled="readonly"
        :label="{
          text: Translator.trans('municipal_code')
        }"
        name="r_municipalCode"
        :value="municipalCode"
        width="w-10" />
      <dp-input
        id="ars"
        class="u-ml-0_25"
        data-cy="procedureCoordinate:regionKey"
        :disabled="readonly"
        :label="{
          text: Translator.trans('ars')
        }"
        name="r_ars"
        :value="ars"
        width="w-10" />
    </div>
  </div>
</template>

<script>
import { checkResponse, DpInput, DpLoading, dpRpc } from '@demos-europe/demosplan-ui'

const LookupStatus = {
  NONE: 0,
  LOADING: 1,
  DONE: 2
}

export default {
  name: 'ProcedureCoordinateGeolocation',

  components: {
    DpInput,
    DpLoading
  },

  props: {
    coordinate: {
      required: false,
      type: Array,
      default: () => []
    },

    location: {
      required: false,
      type: Object,
      default: () => { return {} }
    }
  },

  data () {
    return {
      ars: '',
      locationName: '',
      latitude: null,
      longitude: null,
      locationPostalCode: '',
      lookupStatus: LookupStatus.NONE,
      municipalCode: ''
    }
  },

  computed: {
    loading () {
      return LookupStatus.LOADING === this.lookupStatus
    },

    readonly () {
      return LookupStatus.NONE === this.lookupStatus
    }
  },

  watch: {
    coordinate: {
      handler (coordinates) {
        if (coordinates.length === 2) {
          this.latitude = coordinates[0]
          this.longitude = coordinates[1]

          this.queryLocation()
        }
      },
      deep: true

    }
  },

  methods: {
    queryLocation () {
      this.lookupStatus = LookupStatus.LOADING

      dpRpc('procedure.locate', {
        latitude: this.latitude,
        longitude: this.longitude
      })
        .then(checkResponse)
        .then(response => {
          this.lookupStatus = LookupStatus.DONE

          if (response.error) {
            return
          }

          this.ars = response[0].result.ars
          this.locationName = response[0].result.locationName
          this.locationPostalCode = response[0].result.locationPostalCode
          this.municipalCode = response[0].result.municipalCode
        }).catch(() => {
          this.lookupStatus = LookupStatus.DONE
        })
    }
  },

  mounted () {
    if (this.location) {
      this.ars = this.location.ars
      this.locationName = this.location.locationName
      this.locationPostalCode = this.location.locationPostalCode
      this.municipalCode = this.location.municipalCode
    }
  }
}
</script>
