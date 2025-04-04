<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
      This component lets a user choose a coordinate to add a location relation to a procedure.
      This coordinate is used to display the procedure on the publicindex map of the project.
   -->
</documentation>

<template>
  <div>
    <dp-ol-map
      :procedure-id="procedureId"
      ref="map"
      :map-options="mapOptions"
      :small="small"
      :is-valid="requiredMapIsValid"
      :options="{initCenter: isValidProcedureCoordinate(procedureCoordinate), autoSuggest: { enabled: useOpengeodb }, scaleSelect: small === false, initialExtent: initExtent }">
      <template
        v-slot:controls
        v-if="editable">
        <dp-procedure-coordinate-input
          :class="prefixClass('u-mb-0_5')"
          :coordinate="coordinate"
          v-if="hasPermission('feature_procedure_coordinate_alternative_input')"
          @input="updateFeatures" />

        <procedure-coordinate-geolocation
          :coordinate="coordinate"
          :location="procedureLocation"
          v-if="hasPermission('feature_procedures_located_by_maintenance_service')" />

        <div :class="prefixClass('inline-block')">
          <dp-ol-map-draw-point
            :class="prefixClass('u-mb-0_5')"
            target="layer:procedureCoordinateDrawer"
            :active="isDrawingActive"
            @tool:setPoint="checkProcedureValidation"
            @tool:activated="setDrawingActive" />
          <dp-ol-map-drag-zoom
            :class="prefixClass('u-mb-0_5')"
            ref="dragzoom"
            @tool:activated="newValue => isDrawingActive = !newValue" />
        </div>
      </template>

      <template>
        <dp-ol-map-layer-vector
          :features="featuresFromCoordinate"
          ref="procedureCoordinateDrawer"
          name="procedureCoordinateDrawer"
          @layer:features:changed="updateProcedureCoordinate" />
      </template>
    </dp-ol-map>

    <!-- If adding a location to procedures is enforced, the corresponding validation is added here via `data-dp-validate`.
         However, not implementing the validation in a Vue plugin or other appropriate way is due to the fact that
         other form elements (that do not share the vue context) also need to be validated. -->
    <template v-if="hasPermission('feature_procedure_require_location')">
      <input
        :data-dp-validate-error-fieldname="Translator.trans('public.participation.relation.map')"
        v-model="currentProcedureCoordinate"
        name="r_coordinate"
        required
        type="hidden">
      <span :class="prefixClass('validation-hint')">
        {{ Translator.trans('statement.map.draw.no_drawing_warning') }}
      </span>
    </template>

    <!-- No validation without `feature_procedure_require_location` -->
    <input
      v-else
      v-model="currentProcedureCoordinate"
      name="r_coordinate"
      type="hidden">
  </div>
</template>

<script>
import DpOlMap from '@DpJs/components/map/map/DpOlMap'
import DpOlMapDragZoom from '@DpJs/components/map/map/DpOlMapDragZoom'
import DpOlMapDrawPoint from '@DpJs/components/map/map/DpOlMapDrawPoint'
import DpOlMapLayerVector from '@DpJs/components/map/map/DpOlMapLayerVector'
import DpProcedureCoordinateInput from './DpProcedureCoordinateInput'
import { prefixClassMixin } from '@demos-europe/demosplan-ui'
import ProcedureCoordinateGeolocation from '@DpJs/components/procedure/basicSettings/ProcedureCoordinateGeolocation'

export default {
  name: 'DpProcedureCoordinate',

  components: {
    DpOlMap,
    DpOlMapDragZoom,
    DpOlMapDrawPoint,
    DpOlMapLayerVector,
    DpProcedureCoordinateInput,
    ProcedureCoordinateGeolocation
  },

  mixins: [prefixClassMixin],

  props: {
    procedureId: {
      required: false,
      type: String,
      default: ''
    },

    procedureCoordinate: {
      required: false,
      type: String,
      default: ''
    },

    procedureLocation: {
      required: false,
      type: Object,
      default: () => { return {} }
    },

    mapOptions: {
      required: false,
      type: Object,
      default: () => ({})
    },

    initExtent: {
      required: false,
      type: Array,
      default: () => { return [] }
    },

    // Bobhh does not use the opengeodb; but there is no permission for that atm.
    useOpengeodb: {
      required: false,
      type: Boolean,
      default: true
    },

    editable: {
      required: false,
      type: Boolean,
      default: true
    },

    small: {
      required: false,
      type: Boolean,
      default: false
    }
  },

  data () {
    return {
      currentProcedureCoordinate: '',
      isDrawingActive: true,
      coordinate: [],
      requiredMapIsValid: true
    }
  },

  computed: {
    //  Returns GeoJson format to pass to vector layer
    featuresFromCoordinate () {
      if (JSON.stringify(this.coordinate) !== JSON.stringify([])) {
        return {
          type: 'Feature',
          geometry: {
            type: 'Point',
            coordinates: this.coordinate
          }
        }
      } else {
        return {}
      }
    }
  },

  methods: {
    checkProcedureValidation () {
      setTimeout(() => {
        this.setProcedureCoordinateValidState()
        if (this.requiredMapIsValid && document.querySelector('[name=r_coordinate]')) {
          document.querySelector('[name=r_coordinate]').classList.remove(this.prefixClass('is-invalid'))
        }
      }, 100)
    },

    updateProcedureCoordinate (features) {
      //  Update procedure coordinate if vector layer changed (a.k.a. if user drew point into vector layer)
      if (features.length > 0) {
        //  Assuming the point geometry in [0], because no other interaction changes the vector layer
        this.currentProcedureCoordinate = features[0].getGeometry().getCoordinates().join(',')
        this.coordinate = features[0].getGeometry().getCoordinates()
      }
    },

    updateFeatures (coordinate) {
      this.coordinate = coordinate
      this.currentProcedureCoordinate = coordinate.join(',')
      this.$refs.map.panToCoordinate(coordinate)
    },

    setProcedureCoordinateValidState () {
      this.requiredMapIsValid = true
      const publicParticipationPhaseElement = document.getElementsByName('r_publicParticipationPhase')[0]
      const phaseElement = document.getElementsByName('r_phase')[0]

      if (publicParticipationPhaseElement.value !== 'configuration' || phaseElement.value !== 'configuration') {
        this.requiredMapIsValid = this.currentProcedureCoordinate !== '' && hasPermission('feature_procedure_require_location')
      }
    },

    //  Validate incoming coordinate to be 'Number,Number' or false
    isValidProcedureCoordinate (coordinate) {
      const coordinateArray = coordinate.split(',')
      if (coordinateArray.length !== 2) {
        return false
      }
      if (isNaN(coordinateArray[0]) || isNaN(coordinateArray[1])) {
        return false
      }

      return [Number(coordinateArray[0]), Number(coordinateArray[1])]
    },

    setDrawingActive (newValue) {
      this.isDrawingActive = newValue
      if (newValue === true) {
        this.$refs.dragzoom.deactivateTool()
      }
    }
  },

  mounted () {
    //  Set the current procedure coordinate to the initial value
    this.currentProcedureCoordinate = this.procedureCoordinate

    //  Only assign coordinate from props if it is valid
    this.coordinate = this.isValidProcedureCoordinate(this.procedureCoordinate) || this.coordinate

    const handleWizardShow = data => {
      if (data === Translator.trans('wizard.topic.location') || data === 'procedureLocation') {
        this.$refs.map.updateMapInstance()
      }
    }
    //  Listeners are added because the OpenLayers map needs to be initialized on a visible element
    document.addEventListener('wizard:show', ({ data }) => {
      //  Only fire when relevant wizard step is transmitted
      handleWizardShow(data)
    })

    document.addEventListener('toggleAnything:clicked', ({ data }) => {
      //  Only fire when relevant toggleAnything toggleId is transmitted
      handleWizardShow(data)
    })

    document.addEventListener('customValidationPassed', () => {
      this.setProcedureCoordinateValidState()
    })

    document.addEventListener('customValidationFailed', () => {
      this.setProcedureCoordinateValidState()
    })
  }
}
</script>
