<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <!--
        This component is used to show the state of map settings (what is already done and what's still left to set the map for the procedure).
        At the moment the component shows:
          - if there are any drawings uploaded
          - if there are any overlays
          - if the procedure has location
          - if initExtent is defined
          - if territory is defined
        These things can be edited by clicking on the links leading to other pages. As these data cannot be edited directly in the component, we get the initial state from twig templateVars.

        There is also a possibility in this component to inline-edit following properties:
          - planstand (date) - no permission check, so available for all projects
          - Map status (aktiviert/deaktiviert - DpToggle component)
          - planning area (dropdown with all possible planning areas)
        These properties are build with DpEditField component can be edited here, so we get initial state with axios and we change them also with api call.
     -->
</documentation>

<template>
  <div class="layout--flush border--bottom u-pb">
    <div class="layout__item u-1-of-2">
      <dp-ol-map
        ref="map"
        :options="{
          scaleSelect: false,
          autoSuggest: false,
          controls: [attributionControl],
          initView: false,
          initCenter: false,
          procedureExtent: true
        }"
        :procedure-id="procedureId"
        small>
        <template>
          <dp-ol-map-layer-vector
            v-if="hasPermission('area_procedure_adjustments_general_location') && procedureCoordinate"
            class="u-mb-0_5"
            :features="features.procedureCoordinate"
            name="mapSettingsPreviewCoordinate" />
          <dp-ol-map-layer-vector
            v-if="initExtent"
            class="u-mb-0_5"
            :features="features.initExtent"
            name="mapSettingsPreviewInitExtent"
            zoom-to-drawing />
          <dp-ol-map-layer-vector
            v-if="territory"
            class="u-mb-0_5"
            :draw-style="drawingStyles.territory"
            :features="features.territory"
            name="mapSettingsPreviewTerritory" />
        </template>
      </dp-ol-map>
    </div><!--
 --><div class="layout__item u-1-of-2">
      <ul>
        <li
          v-for="(link, index) in permittedLinks"
          class="layout__item"
          :key="link.tooltipContent">
          <a
            v-tooltip="Translator.trans(link.tooltipContent)"
            class="o-link"
            :class="{'color-status-complete-text': link.done()}"
            :data-cy="`gisLayerLink:${link.label}`"
            :href="href(link)">
            <i
              aria-hidden="true"
              class="w-[20px]"
              :class="{'fa fa-check color-status-complete-fill': link.done(), 'fa fa-plus': !link.done()}" />
            {{ link.done() ?
              Translator.trans(link.labelDone)
              : Translator.trans(link.label)
            }}
          </a>
        </li>
      </ul>
      <div
        v-if="hasPermission('feature_map_planstate')"
        class="layout__item u-mb-0_25 u-mt-0_25">
        <label
          class="inline-block u-1-of-3 u-mb-0"
          for="planstatus">{{ Translator.trans('planstatus') }}
        </label><!--
     --><div class="inline-block u-2-of-3">
        <dp-datepicker
          id="planstatus"
          class="inline-block u-3-of-4"
          v-model="planstatus"
          :calendars-before="2"
          :disabled="!isPlanStatusEditing" /><!--
       --><div class="inline-block u-1-of-4 text-right">
            <button
              v-if="false === isPlanStatusEditing"
              class="btn--blank o-link--default"
              data-cy="planStatusEditing"
              :title="Translator.trans('edit')"
              type="button"
              @click="setEditingStatus('isPlanStatusEditing', true)">
              <i
                aria-hidden="true"
                class="fa fa-pencil" />
            </button>
            <button
              v-if="isPlanStatusEditing"
              class="btn--blank o-link--default"
              :title="Translator.trans('save')"
              type="button"
              @click="updatePlanstatus">
              <i
                aria-hidden="true"
                class="fa fa-check" />
            </button>
            <button
              v-if="isPlanStatusEditing"
              class="btn--blank o-link--default"
              :title="Translator.trans('reset')"
              type="button"
              @click="reset('planstatus', 'isPlanStatusEditing')">
              <i
                aria-hidden="true"
                class="fa fa-times" />
            </button>
          </div>
        </div>
      </div>
      <div
        v-if="hasPermission('feature_map_deactivate')"
        class="layout__item u-mb-0_25">
        <label
          class="inline-block u-1-of-3 u-mb-0"
          for="mapStatus">{{ Translator.trans('map') }}
        </label><!--
     --><div class="inline-block u-2-of-3">
<!--
     --><div class="inline-block u-3-of-4">
          <dp-toggle
            id="mapStatus"
            v-model="isMapEnabled"
            :disabled="!isMapStatusEditing" />
        </div><!--
       --><div class="inline-block u-1-of-4 text-right">
            <button
              v-if="false === isMapStatusEditing"
              class="btn--blank o-link--default"
              data-cy="mapStatusEditing"
              :title="Translator.trans('edit')"
              type="button"
              @click="setEditingStatus('isMapStatusEditing', true)">
              <i
                aria-hidden="true"
                class="fa fa-pencil" />
            </button>
            <button
              v-if="isMapStatusEditing"
              class="btn--blank o-link--default"
              data-cy="mapStatusEditingSave"
              :title="Translator.trans('save')"
              type="button"
              @click="updateIsMapEnabled">
              <i
                aria-hidden="true"
                class="fa fa-check" />
            </button>
            <button
              v-if="isMapStatusEditing"
              class="btn--blank o-link--default"
              data-cy="mapStatusEditingReset"
              :title="Translator.trans('reset')"
              type="button"
              @click="reset('isMapEnabled', 'isMapStatusEditing')">
              <i
                aria-hidden="true"
                class="fa fa-times" />
            </button>
          </div>
        </div>
      </div>
      <div
        v-if="hasPermission('feature_procedure_planning_area_match')"
        class="layout__item">
        <label
          class="inline-block u-1-of-3 u-mb-0"
          for="planningArea">
          {{ Translator.trans('planningArea') }}
        </label><!--
     --><div class="inline-block u-2-of-3">
          <select
            id="planningArea"
            v-model="planningArea"
            class="o-form__control-select u-3-of-4"
            :disabled="!isPlanningAreaEditing">
            <option
              v-for="(option, idx) in planningAreaOptions"
              :key="`planning_area_${idx}`"
              :value="option.value">
              {{ Translator.trans(option.label) }}
            </option>
          </select><!--
       --><div class="inline-block u-1-of-4 text-right">
            <button
              v-if="false === isPlanningAreaEditing"
              :title="Translator.trans('edit')"
              type="button"
              class="btn--blank o-link--default"
              @click="setEditingStatus('isPlanningAreaEditing', true)">
              <i
                aria-hidden="true"
                class="fa fa-pencil" />
            </button>
            <button
              v-if="isPlanningAreaEditing"
              class="btn--blank o-link--default"
              :title="Translator.trans('save')"
              type="button"
              @click="updatePlanningArea">
              <i
                aria-hidden="true"
                class="fa fa-check" />
            </button>
            <button
              v-if="isPlanningAreaEditing"
              class="btn--blank o-link--default"
              :title="Translator.trans('reset')"
              type="button"
              @click="reset('planningArea', 'isPlanningAreaEditing')">
              <i
                aria-hidden="true"
                class="fa fa-times" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { checkResponse, dpApi, DpDatepicker, DpToggle, hasOwnProp } from '@demos-europe/demosplan-ui'
import { Attribution } from 'ol/control'
import DpOlMap from '@DpJs/components/map/map/DpOlMap'
import DpOlMapLayerVector from '@DpJs/components/map/map/DpOlMapLayerVector'
import { fromExtent } from 'ol/geom/Polygon'

export default {
  name: 'DpMapSettingsPreview',

  components: {
    DpDatepicker,
    DpOlMap,
    DpOlMapLayerVector,
    DpToggle
  },

  props: {
    drawing: {
      required: false,
      type: String,
      default: ''
    },

    drawingExplanation: {
      required: false,
      type: String,
      default: ''
    },

    initExtent: {
      required: false,
      type: String,
      default: ''
    },

    isBlueprint: {
      required: false,
      type: Boolean,
      default: false
    },

    procedureCoordinate: {
      required: false,
      type: String,
      default: ''
    },

    procedureDefaultInitialExtent: {
      type: Array,
      required: false,
      default: () => []
    },

    procedureId: {
      required: false,
      type: String,
      default: ''
    },

    territory: {
      required: false,
      type: String,
      default: ''
    }
  },

  data () {
    return {
      drawingStyles: {
        territory: JSON.stringify({
          fillColor: 'rgba(0,0,0,0.1)',
          strokeColor: '#000',
          imageColor: '#fff',
          strokeLineDash: [4, 4],
          strokeLineWidth: 3
        })
      },
      gislayers: false,
      isMapStatusEditing: false,
      isPlanningAreaEditing: false,
      isPlanStatusEditing: false,
      isMapEnabled: false,
      mapIdent: '',
      planningArea: '',
      planningAreaOptions: [],
      planstatus: '',
      previousValues: {
        isMapEnabled: '',
        planstatus: '',
        planningArea: ''
      }
    }
  },

  computed: {
    attributionControl () {
      return new Attribution({ collapsible: false })
    },

    features () {
      /*
       *  Transform the value that is saved as a string into valid GeoJSON
       *  to be able to use it with a generic vector layer component
       */
      return {
        procedureCoordinate: this.procedureCoordinate
          ? {
              type: 'Feature',
              geometry: {
                type: 'Point',
                coordinates: JSON.parse(`[${this.procedureCoordinate}]`)
              }
            }
          : null,
        initExtent: this.initExtent && `[${this.initExtent}]` !== JSON.stringify(this.procedureDefaultInitialExtent)
          ? {
              type: 'Feature',
              geometry: {
                type: 'Polygon',
                coordinates: fromExtent(JSON.parse(`[${this.initExtent}]`)).getCoordinates()
              }
            }
          : null,
        territory: this.territory ? JSON.parse(this.territory) : null
      }
    },

    permittedLinks () {
      return this.links ? this.links.filter(link => (!link.permission || hasPermission(link.permission)) && !link.hide) : []
    }
  },

  methods: {
    href (link) {
      return Routing.generate(link.routeName, {
        procedureId: this.procedureId
      })
    },

    isNotEmptyFeatureCollection (string) {
      try {
        return JSON.parse(string).features.length > 0
      } catch (e) {
        return true
      }
    },

    setEditingStatus (statusKey, status) {
      this[statusKey] = status
    },

    updatePlanstatus () {
      return dpApi({
        method: 'PATCH',
        url: Routing.generate('dp_api_documents_dashboard_update', {
          procedureId: this.procedureId
        }),
        data: {
          data: {
            type: 'DocumentDashboard',
            id: this.procedureId,
            attributes: {
              planText: this.planstatus
            }
          }
        }
      }).then(checkResponse)
        .then(() => {
          this.previousValues.planstatus = this.planstatus
          this.isPlanStatusEditing = false
        })
        .catch(() => dplan.notify.error(Translator.trans('error.api.generic')))
    },

    updateIsMapEnabled () {
      return dpApi({
        method: 'PATCH',
        url: Routing.generate('dp_api_documents_elements_update', {
          procedureId: this.procedureId,
          elementsId: this.mapIdent
        }),
        data: {
          data: {
            type: 'Elements',
            id: this.mapIdent,
            attributes: {
              enabled: this.isMapEnabled
            }
          }
        }
      }).then(checkResponse)
        .then((response) => {
          this.previousValues.isMapEnabled = this.isMapEnabled
          this.isMapStatusEditing = false
        })
        .catch(() => dplan.notify.error(Translator.trans('error.api.generic')))
    },

    updatePlanningArea () {
      return dpApi({
        method: 'PATCH',
        url: Routing.generate('dp_api_documents_dashboard_update', {
          procedureId: this.procedureId
        }),
        data: {
          data: {
            type: 'DocumentDashboard',
            id: this.procedureId,
            attributes: {
              planningArea: this.planningArea
            }
          }
        }
      }).then(checkResponse)
        .then((response) => {
          this.previousValues.planningArea = this.planningArea
          this.isPlanningAreaEditing = false
        })
        .catch(() => dplan.notify.error(Translator.trans('error.api.generic')))
    },

    reset (area, editingStatusKey) {
      this[area] = this.previousValues[area]
      this[editingStatusKey] = false
    },

    getInitialData () {
      return dpApi.get(Routing.generate('dp_api_documents_dashboard_get', { procedureId: this.procedureId, include: 'procedureMapInfo' }))
        .then(this.checkResponse)
        .then((response) => {
          // Get id of the "Elements" item that is the map
          if (hasOwnProp(response.data.data, 'relationships')) {
            this.mapIdent = response.data.data.relationships.procedureMapInfo.data.id
          }

          // Get "Status" (a.k.a. procedure setting, if map is enabled)
          const elem = response.data.included.find((elem) => elem.id === this.mapIdent)
          this.isMapEnabled = (typeof elem !== 'undefined') ? elem.attributes.enabled : false

          // Planstand equals a date (with no further functionality attached)
          this.planstatus = response.data.data.attributes.planText

          this.gislayers = response.data.data.attributes.hasGisLayers

          if (hasPermission('feature_procedure_planning_area_match')) {
            this.planningArea = response.data.data.attributes.planningArea
            this.planningAreaOptions = response.data.data.attributes.availablePlanningAreas
          }
        })
        .catch(e => true)
    }
  },

  created () {
    this.links = [
      {
        permission: 'feature_map_use_plan_draw_pdf',
        tooltipContent: 'drawing.upload',
        routeName: 'DemosPlan_map_administration_gislayer',
        hash: 'drawingData',
        done: () => this.drawing && this.drawing !== '',
        labelDone: 'drawing.uploaded',
        label: 'drawing.upload'
      },
      {
        permission: 'feature_map_use_plan_pdf',
        tooltipContent: 'drawing.explanation.upload',
        routeName: 'DemosPlan_map_administration_gislayer',
        hash: 'drawingData',
        done: () => this.drawingExplanation && this.drawingExplanation !== '',
        labelDone: 'drawing.explanation.uploaded',
        label: 'drawing.explanation.upload'
      },
      {
        permission: 'feature_map_hint',
        hide: this.isBlueprint === true,
        tooltipContent: 'map.hint.add',
        routeName: 'DemosPlan_map_administration_gislayer',
        hash: 'mapHint',
        done: () => true,
        labelDone: 'map.hint.added',
        label: 'map.hint.add'
      },
      {
        permission: false,
        tooltipContent: 'gislayer.define',
        routeName: 'DemosPlan_map_administration_gislayer',
        hash: 'gislayers',
        done: () => !!(this.gislayers),
        labelDone: 'gislayer.defined',
        label: 'gislayer.define'
      },
      {
        permission: 'area_procedure_adjustments_general_location',
        tooltipContent: 'location.procedure.assign',
        routeName: 'DemosPlan_map_administration_map',
        hash: false,
        done: () => this.procedureCoordinate && this.procedureCoordinate !== '',
        labelDone: 'location.procedure.assigned',
        label: 'location.procedure.assign'
      },
      {
        permission: false,
        tooltipContent: 'clipping.set',
        routeName: 'DemosPlan_map_administration_map',
        hash: false,
        done: () => this.initExtent && `[${this.initExtent}]` !== JSON.stringify(this.procedureDefaultInitialExtent),
        labelDone: 'clipping.is.set',
        label: 'clipping.set'
      },
      {
        permission: 'feature_map_use_territory',
        tooltipContent: 'map.territory.define',
        routeName: 'DemosPlan_map_administration_map',
        hash: false,
        done: () => this.territory && this.territory !== '' && this.territory !== JSON.stringify({}) && this.isNotEmptyFeatureCollection(this.territory),
        labelDone: 'map.territory.is.defined',
        label: 'map.territory.define'
      }
    ]
  },

  async mounted () {
    await this.getInitialData()

    this.previousValues.isMapEnabled = this.isMapEnabled
    this.previousValues.planstatus = this.planstatus
    this.previousValues.planningArea = this.planningArea
  }

}
</script>
