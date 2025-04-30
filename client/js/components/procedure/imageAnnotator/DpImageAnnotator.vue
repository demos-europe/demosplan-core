<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-loading v-if="isLoading" />
    <template v-else>
      <div class="annotator__wrapper">
        <div
          id="map"
          class="annotator__canvas" />
        <dp-label-modal
          ref="labelModal"
          :labels="selectElementLabels"
          @set-label="setLabel" />
        <dp-sticky-element>
          <div class="u-ml w-12">
            <p
              class="weight--bold">
              {{ Translator.trans('tool.active') }}
            </p>
            <div>
              <div class="annotator__button-wrapper is-first">
                <button
                  @click="setInteraction('select')"
                  class="btn annotator__button annotator__button--toggle"
                  :class="{'is-current': currentInteractionName === 'select'}"
                  aria-labelledby="elementSelectLabel">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 10 17"
                    style="width: 20px; height: 20px;">
                    <defs>
                      <clipPath id="selectIcon">
                        <path d="M0 0h12v17H0z" />
                      </clipPath>
                    </defs>
                    <g :clip-path="`url(#selectIcon)`">
                      <path
                        d="M0 0v17l4.849-4.973H12z"
                        :fill="currentInteractionName === 'select' ? '#fff' : '#4d4d4d'" />
                    </g>
                  </svg>
                </button>
              </div>
              <span
                class="align-middle u-ml-0_5"
                id="elementSelectLabel">
                {{ Translator.trans('select.or.edit') }}
              </span>
            </div>
            <div>
              <div class="annotator__button-wrapper is-last">
                <button
                  @click="setInteraction('draw')"
                  class="btn annotator__button annotator__button--toggle"
                  :class="{'is-current': currentInteractionName === 'draw'}"
                  aria-labelledby="elementDrawLabel">
                  <i class="fa fa-plus" />
                </button>
              </div>
              <span
                class="align-middle u-ml-0_5"
                id="elementDrawLabel">
                {{ Translator.trans('element.add') }}
              </span>
            </div>
            <div class="u-mt-2">
              <p
                class="weight--bold"
                :class="{'color--grey-light': currentInteractionName !== 'select' || !editingFeature}">
                {{ Translator.trans('element.selected') }}
                <dp-contextual-help
                  class="float-right u-mt-0_12"
                  :text="Translator.trans('annotator.modify.explanation')" />
              </p>
              <div>
                <button
                  @click="deleteFeature(editingFeature)"
                  class="annotator__button btn btn--warning btn--outline u-ml-0_25"
                  :disabled="currentInteractionName !== 'select' || !editingFeature"
                  aria-labelledby="elementDeleteLabel">
                  <i class="fa fa-trash" />
                </button>
                <span
                  class="align-middle u-ml-0_5"
                  :class="{'color--grey-light': currentInteractionName !== 'select' || !editingFeature}"
                  id="elementDeleteLabel">
                  {{ Translator.trans('element.delete') }}
                </span>
              </div>
              <div>
                <button
                  @click="$refs.labelModal.toggleModal(getFeatureLabel(editingFeature))"
                  class="annotator__button btn btn--primary btn--outline u-ml-0_25"
                  :disabled="currentInteractionName !== 'select' || !editingFeature"
                  aria-labelledby="formatChangeLabel">
                  <i class="fa fa-tag" />
                </button>
                <span
                  class="align-middle u-ml-0_5"
                  :class="{'color--grey-light': currentInteractionName !== 'select' || !editingFeature}"
                  id="formatChangeLabel">
                  {{ Translator.trans('format.change') }}
                </span>
              </div>
            </div>
            <div class="u-mt-2">
              <p>{{ Translator.trans('pages.checked', { doneCount: donePagesCount, totalCount: documentLengthTotal }) }}</p>
              <div>
                <dp-button
                  :busy="isSaving"
                  class="w-11 u-mb-0_25"
                  :disabled="documentLengthTotal === 0"
                  :text="buttonText"
                  @click="save" />
                <dp-button
                  class="w-11"
                  color="secondary"
                  :href="Routing.generate('DemosPlan_procedure_dashboard', { procedure: procedureId })"
                  :text="Translator.trans('abort')" />
              </div>
            </div>
          </div>
        </dp-sticky-element>
      </div>
    </template>
    <dp-send-beacon
      :url="Routing.generate('dplan_annotated_statement_pdf_pause_box_review', {
        documentId: initDocumentId,
        procedureId: procedureId
      })" />
  </div>
</template>

<script>
import { Circle as CircleStyle, Fill, Stroke, Style, Text } from 'ol/style'
import { containsExtent, getCenter } from 'ol/extent'
import { defaults as defaultInteractions, Draw, Modify, Select, Snap } from 'ol/interaction'
import { dpApi, DpButton, DpContextualHelp, DpLoading, DpStickyElement, hasOwnProp } from '@demos-europe/demosplan-ui'
import { createBox } from 'ol/interaction/Draw'
import DpLabelModal from './DpLabelModal'
import DpSendBeacon from './DpSendBeacon'
import GeoJSON from 'ol/format/GeoJSON'
import ImageLayer from 'ol/layer/Image'
import Map from 'ol/Map'
import { MultiPoint } from 'ol/geom'
import Projection from 'ol/proj/Projection'
import Static from 'ol/source/ImageStatic'
import { v4 as uuid } from 'uuid'
import { Vector as VectorLayer } from 'ol/layer'
import { Vector as VectorSource } from 'ol/source'
import View from 'ol/View'

export default {
  name: 'DpImageAnnotator',

  components: {
    DpContextualHelp,
    DpButton,
    DpLabelModal,
    DpLoading,
    DpSendBeacon,
    DpStickyElement
  },

  props: {
    allLabels: {
      validator: (value) => {
        /**
         * Check if all labels have the desired structure:
         * { piName: '<pi-identifier>', color: '<label-color>', translation: '<label-translation>' }
         *
         */
        return Array.isArray(value) && value
          .filter(label => label.piName && label.color && label.translation).length === value.length
      },
      required: true
    },

    initDocumentId: {
      type: String,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      allDocumentPages: [],
      currentInteraction: null,
      currentInteractionName: '',
      documentId: this.initDocumentId,
      editingFeature: null,
      geoJson: null,
      imageUrl: '',
      isLoading: false,
      isSaving: false,
      isStyleApplied: false,
      nextDocumentId: '',
      pageDimension: [],
      pageId: '',
      placeholder: null,
      /**
       * How many pages in the document are still not confirmed,
       * including the currently displayed one
       */
      unconfirmedPagesCount: ''
    }
  },

  computed: {
    buttonText () {
      return this.unconfirmedPagesCount - 1 > 0 // We have to subtract 1 for the currently displayed page
        ? Translator.trans('save.and.show.next.page')
        : this.currentPageNumber === this.documentLengthTotal
          ? Translator.trans('save.and.return.to.list')
          : Translator.trans('save.and.show.next.document')
    },

    currentPageNumber () {
      return this.allDocumentPages.findIndex(el => el.id === this.pageId) + 1
    },

    documentLengthTotal () {
      return this.allDocumentPages.length
    },

    donePagesCount () {
      return this.documentLengthTotal - this.unconfirmedPagesCount
    },

    labels () {
      return this.allLabels.reduce((acc, label) => {
        acc[label.piName] = label.color
        return acc
      }, {})
    },

    labelTranslations () {
      return this.allLabels.reduce((acc, label) => {
        acc[label.piName] = label.translation
        return acc
      }, {})
    },

    selectElementLabels () {
      return Object.entries(this.labelTranslations)
        .map(([key, value]) => {
          return {
            label: Translator.trans(value),
            value: key
          }
        })
        .sort((a, b) => a.label.localeCompare(b.label, 'de', { sensitivity: 'base' }))
    }
  },

  methods: {
    addMapEventListeners () {
      // Add event listener on click on features => toggle label modal
      this.map.on('dblclick', (e) => {
        let isFirstFeature = false
        if (this.currentInteractionName === 'select') {
          this.map.forEachFeatureAtPixel(e.pixel, (feature) => {
            if (isFirstFeature === false) {
              // On double click open label-edit modal. Do it only once, even if there are more features at that pixel
              isFirstFeature = true
              if (this.editingFeature === feature) {
                this.$refs.labelModal.toggleModal(this.getFeatureLabel(feature))
              }
            }
          })
        }
      })

      // Add event listener for backspace/delete - if select interaction is active and delete button or backspace/delete is pressed, remove feature
      document.addEventListener('keydown', e => {
        if ((e.key === 'Backspace' || e.key === 'Delete') && this.currentInteractionName === 'select' && this.editingFeature) {
          e.preventDefault()
          this.deleteFeature(this.editingFeature)
        }
      })

      // Add event listener on esc - if select interaction is on, the selection will be resetted
      document.addEventListener('keydown', e => {
        if ((e.key === 'Escape') && this.currentInteractionName === 'select' && this.editingFeature) {
          e.preventDefault()
          this.clearSelection()
        }
      })

      // Add event listener on mousemove => if delete or modify is active, change cursor pointer on feature hover
      this.map.on('pointermove', (e) => {
        const isFeature = this.map.forEachFeatureAtPixel(e.pixel, () => true)
        if (isFeature) {
          this.map.getTargetElement().style.cursor = 'pointer'
        } else {
          this.map.getTargetElement().style.cursor = ''
        }
      })
    },

    applyFeatureUpdate () {
      const feature = this.editingFeature
      const currentCoordinates = feature.getGeometry().getCoordinates()[0]
      const initCoords = this.editingFeatureInitialCoords

      // Check which vertex of the feature was moved; get the squarified-version of the changed coordinates as its extent
      const getChangedCoordinates = (currentCoords, initCoords) => {
        let hasChanged = false
        currentCoords.forEach((coordpair, idx) => {
          if (coordpair[0] !== initCoords[idx][0] || coordpair[1] !== initCoords[idx][1]) {
            hasChanged = {}
            hasChanged.newCoords = coordpair
            hasChanged.oldCoords = initCoords[idx]
            hasChanged.idx = idx === 0 ? 4 : idx
          }
        })
        return hasChanged
      }
      const changed = getChangedCoordinates(currentCoordinates, initCoords)

      const calculateSquaredCoordinates = (changed, initCoordinates) => {
        const newCorrectCoordinates = initCoordinates
        // First set new coordinates of the vertex that was moved
        newCorrectCoordinates[changed.idx] = changed.newCoords
        // Then set coordinates of the sibling vertices
        if (changed.idx === 1 || changed.idx === 3) {
          const idxBefore = changed.idx === 1 ? 4 : changed.idx - 1
          newCorrectCoordinates[changed.idx + 1][0] = changed.newCoords[0]
          newCorrectCoordinates[idxBefore][1] = changed.newCoords[1]
        } else if (changed.idx === 2 || changed.idx === 4) {
          const idxAfter = changed.idx === 4 ? 1 : changed.idx + 1
          newCorrectCoordinates[changed.idx - 1][0] = changed.newCoords[0]
          newCorrectCoordinates[idxAfter][1] = changed.newCoords[1]
        }
        // At the end set first and last coordinate identical (it is a closed polygon)
        newCorrectCoordinates[0] = newCorrectCoordinates[4]

        return newCorrectCoordinates
      }

      // Check if new feature does not overflow image extent
      const isInMapView = containsExtent(this.imageLayerExtent, feature.getGeometry().getExtent())

      if (changed && isInMapView) {
        const newCoords = calculateSquaredCoordinates(changed, initCoords)
        feature.un('change', this.applyFeatureUpdate)
        feature.getGeometry().setCoordinates([newCoords])
        feature.on('change', this.applyFeatureUpdate)
        // Update manually to prevent inconsistent data
        this.editingFeatureInitialCoords = newCoords
        this.lastAppliedCoords = newCoords
      } else {
        feature.un('change', this.applyFeatureUpdate)
        feature.getGeometry().setCoordinates([this.lastAppliedCoords ? this.lastAppliedCoords : initCoords])
        feature.on('change', this.applyFeatureUpdate)
      }
    },

    clearSelection () {
      this.selectInteraction.getFeatures().clear()
      this.editingFeature = null
    },

    createNewFeature (feature) {
      return {
        type: 'Feature',
        id: feature.getId(),
        geometry: {
          type: 'Polygon',
          coordinates: feature.getGeometry().getCoordinates()
        },
        properties: {
          score: null,
          label: ''
        }
      }
    },

    deleteFeature (feature) {
      const idx = this.geoJson.features.findIndex(el => el.id === feature.getId())
      this.geoJson.features.splice(idx, 1)
      this.boxLayerSource.removeFeature(feature)
      this.setInteraction('select')
    },

    generateFeatureStyle (labelText, isSelected = false) {
      let selectedCircleStyle = null
      if (isSelected) {
        // Display drag circles in the corners of the box in selected state
        selectedCircleStyle = new Style({
          image: new CircleStyle({
            radius: 5,
            fill: new Fill({
              color: '#00aaff'
            }),
            stroke: new Stroke({ color: '#fff', width: 3 / 2 })
          }),
          geometry: (feature) => {
            const coordinates = feature.getGeometry().getCoordinates()[0]
            return new MultiPoint(coordinates)
          }
        })
      }

      const featureStyle = new Style({
        fill: new Fill({
          color: 'rgba(255, 255, 255, 0.2)'
        }),
        stroke: new Stroke({
          color: this.labels[labelText] || 'black',
          width: 2
        })
      })

      if (labelText) {
        featureStyle.setText(new Text({
          text: this.labelTranslations[labelText],
          font: '14px Arial',
          placement: 'line',
          maxAngle: 0,
          textBaseline: 'bottom',
          overflow: true,
          offsetY: -2,
          stroke: new Stroke({ color: 'white', width: 5 })
        }))
      }

      return isSelected ? [selectedCircleStyle, featureStyle] : featureStyle
    },

    getFeatureLabel (feature) {
      let label = null
      try {
        const featureId = feature.getId()
        label = this.geoJson.features.find(el => el.id === featureId).properties.label
        if (label.startsWith('"') || label.endsWith('"')) {
          label.replace('"', '')
        }
      } catch (err) {
        label = null
      }
      return label
    },

    async getInitialData (isNewDocument = true) {
      this.isLoading = true

      // Step 1: get first page to be annotated
      const url = Routing.generate('api_resource_list', { resourceType: 'AnnotatedStatementPdfPage' })
      const params = {
        filter: {
          annotatedStatementPdf: {
            condition: {
              path: 'annotatedStatementPdf.id',
              value: this.documentId
            }
          },
          confirmed: {
            condition: {
              path: 'confirmed',
              value: false
            }
          }
        },
        procedureId: window.dplan.procedureId,
        page: {
          size: 1
        },
        sort: 'pageSortIndex',
        fields: {
          AnnotatedStatementPdfPage: [
            'id',
            'url',
            'width',
            'height',
            'geoJson',
            'annotatedStatementPdf'
          ].join(),
          AnnotatedStatementPdf: [
            'status',
            'text',
            'file',
            'procedure',
            'statement',
            'annotatedStatementPdfPages'
          ].join()
        },
        include: ['annotatedStatementPdf'].join()
      }
      const pageResponse = await dpApi.get(url, params)
      if (hasOwnProp(pageResponse, 'data') && hasOwnProp(pageResponse.data, 'data') && pageResponse.data.data.length) {
        const pageAttrs = pageResponse.data.data[0].attributes
        this.geoJson = pageAttrs.geoJson
        this.geoJson.features.forEach(feature => {
          feature.id = uuid()
        })
        this.pageId = pageResponse.data.data[0].id
        this.pageDimension = [pageAttrs.width, pageAttrs.height]
        this.imageUrl = pageAttrs.url
        this.unconfirmedPagesCount = pageResponse.data.meta.pagination.total_pages

        // Get info about how many pages are in the document
        const documentInclude = pageResponse.data.included.find(el => el.type === 'AnnotatedStatementPdf' && el.id === this.documentId)
        this.allDocumentPages = documentInclude.relationships.annotatedStatementPdfPages.data

        // Step 2: update documentId in url and get next documentId only initially or if we just got a first page of a new document
        if (isNewDocument) {
          this.updateUrl()
          const documentResponse = await dpApi.get(Routing.generate('dplan_next_annotated_statement_pdf_to_review', { procedureId: window.dplan.procedureId, documentId: this.documentId }))
          this.nextDocumentId = documentResponse.data.documentId
        }

        this.isLoading = false
        this.$nextTick(() => this.initMap())
      } else {
        this.isLoading = false
      }
    },

    initInteractions () {
      // SELECT INTERACTION
      this.selectInteraction = new Select({ layers: [this.boxLayer] })

      this.selectInteraction.on('select', e => {
        if (e.deselected.length === 1) {
          // Reset selected style
          this.editingFeature.setStyle(this.generateFeatureStyle(this.getFeatureLabel(e.deselected[0]), false))
          this.editingFeature = null
        }

        if (e.selected.length === 1) {
          this.selectInteraction.getFeatures().clear()
          this.selectInteraction.getFeatures().push(e.selected[0])
          this.editingFeature = e.selected[0]
          // Set selected style of the feature
          this.editingFeature.setStyle(this.generateFeatureStyle(this.getFeatureLabel(e.selected[0]), true))
        }
      })

      // MODIFY INTERACTION
      this.modifyInteraction = new Modify({
        insertVertexCondition: () => false,
        pixelTolerance: 1,
        features: this.selectInteraction.getFeatures()
      })

      this.modifyInteraction.on('modifystart', (e) => {
        // Remove snap during modify action because it tries to snap to invisible features/vertices
        this.map.removeInteraction(this.snapInteraction)
        this.editingFeatureInitialCoords = this.editingFeature.getGeometry().getCoordinates()[0]
        // Register change event listeners on the edited feature so that we can execute code when a feature is modified
        this.editingFeature.on('change', this.applyFeatureUpdate)
      })

      this.modifyInteraction.on('modifyend', (e) => {
        if (this.editingFeature && this.editingFeatureInitialCoords) {
          // Set geometry coords again on feature - I don't exactly know why but otherwise the virtual vertices are not moved to new places and therefore it would not be possible to resize the box again
          this.editingFeature.getGeometry().setCoordinates(this.editingFeature.getGeometry().getCoordinates())
          // Set new coords in geoJson
          const idx = this.geoJson.features.findIndex(el => el.id === this.editingFeature.getId())
          this.geoJson.features[idx].geometry.coordinates = this.editingFeature.getGeometry().getCoordinates()
        }
        // Unregister event listener because they are not needed anymore
        this.editingFeature.un('change', this.applyFeatureUpdate)
        this.editingFeatureInitialCoords = null
        this.lastAppliedCoords = null
        // Re-add snap
        this.map.addInteraction(this.snapInteraction)
      })

      // DRAW INTERACTION
      this.drawInteraction = new Draw({
        source: this.boxLayerSource,
        type: 'Circle',
        geometryFunction: createBox(),
        style: new Style({
          stroke: new Stroke({
            color: '#228B22',
            width: 2
          }),
          image: new CircleStyle({
            radius: 5,
            fill: new Fill({
              color: '#228B22'
            })
          })
        })
      })
      this.drawInteraction.on('drawend', (e) => {
        const newFeature = e.feature
        newFeature.setId(uuid())
        this.editingFeature = newFeature
        this.geoJson.features.push(this.createNewFeature(newFeature))
        this.$refs.labelModal.toggleModal()
      })

      // SNAP INTERACTION
      this.snapInteraction = new Snap({ source: this.boxLayerSource, edge: false, pixelTolerance: 15 })

      // Set select as initial interaction
      this.setInteraction('select')
    },

    fitMap () {
      this.map.getView().fit(this.imageLayerExtent, { padding: [10, 10, 10, 10] })
    },

    handleResize () {
      this.map.updateSize()
      this.fitMap()
    },

    initMap () {
      /*
       * Map views always need a projection.  Here we just want to map image
       * coordinates directly to map coordinates, so we create a projection that uses
       * the image extent in pixels.
       */
      this.imageLayerExtent = [0, 0, ...this.pageDimension]
      const projection = new Projection({
        code: 'image',
        units: 'pixels',
        extent: this.imageLayerExtent
      })

      this.boxLayerSource = new VectorSource({
        features: (new GeoJSON()).readFeatures(this.geoJson),
        format: new GeoJSON()
      })

      this.boxLayer = new VectorLayer({
        source: this.boxLayerSource,
        style: this.generateFeatureStyle()
      })
      // Set initial features' labels
      this.boxLayerSource.getFeatures().forEach((feature) => {
        const label = feature.getProperties().label
        if (label) {
          feature.setStyle(this.generateFeatureStyle(label))
        }
      })

      this.map = new Map({
        interactions: defaultInteractions({
          doubleClickZoom: false,
          dragAndDrop: false,
          dragPan: true,
          keyboardPan: false,
          keyboardZoom: false,
          mouseWheelZoom: true,
          pointer: false,
          select: true
        }),
        controls: [],
        layers: [
          new ImageLayer({
            source: new Static({
              url: this.imageUrl,
              projection,
              imageExtent: this.imageLayerExtent
            })
          }), this.boxLayer
        ],
        target: 'map',
        view: new View({
          projection,
          center: getCenter(this.imageLayerExtent),
          zoom: 2
        })
      })
      this.fitMap()
      // Set max view extent to image extent with padding to disable panning outside of the view
      this.map.setView(new View({
        center: this.map.getView().getCenter(),
        extent: this.map.getView().calculateExtent(this.map.getSize()),
        projection: this.map.getView().getProjection(),
        zoom: this.map.getView().getZoom()
      }))

      this.initInteractions()
      this.addMapEventListeners()

      /*
       * To fit the image into canvas also for small viewports and if layout changes,
       * a ResizeObserver will trigger a map refresh.
       */
      this.elementObserver = new ResizeObserver(this.handleResize.bind(this))
      this.elementObserver.observe(this.$el)
    },

    redirect () {
      if (hasPermission('area_admin_import')) {
        window.location.href = Routing.generate('DemosPlan_procedure_import', { procedureId: window.dplan.procedureId })
      } else {
        window.location.href = Routing.generate('DemosPlan_procedure_dashboard', { procedure: window.dplan.procedureId })
      }
    },

    save () {
      this.isSaving = true
      const payload = {
        data: {
          type: 'AnnotatedStatementPdfPage',
          id: this.pageId,
          attributes: { geoJson: this.geoJson, confirmed: true }
        }
      }

      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'AnnotatedStatementPdfPage', resourceId: this.pageId }), {}, payload)
        .then(() => {
          const isLastPage = Boolean(this.currentPageNumber === this.documentLengthTotal)
          if (isLastPage) {
            // Depending on the current user, redirect to dashboard (FPA) or statement import ("Datenerfassung") if there is no next document
            this.redirect()
          } else {
            // On success fire getInitData to get data for next page/document
            if (isLastPage) {
              this.documentId = this.nextDocumentId
            }
            this.getInitialData(isLastPage)
            this.isSaving = false
          }
        })
        .catch(err => {
          console.err(err)
          this.isSaving = false
        })
    },

    setInteraction (interactionName) {
      let interaction = null
      switch (interactionName) {
        case 'draw':
          interaction = this.drawInteraction
          break
        case 'select':
        default:
          interaction = this.selectInteraction
          break
      }
      if (this.currentInteraction) {
        this.map.removeInteraction(this.currentInteraction)
      }

      this.currentInteraction = interaction
      this.currentInteractionName = interactionName

      this.map.addInteraction(this.currentInteraction)
      this.clearSelection()

      if (interactionName === 'select') {
        this.map.addInteraction(this.modifyInteraction)
        this.map.addInteraction((this.snapInteraction))
      } else if (interactionName === 'draw') {
        this.map.removeInteraction(this.modifyInteraction)
        this.map.removeInteraction(this.snapInteraction)
      }
    },

    setLabel (label) {
      const isCurrentlySelected = this.currentInteractionName === 'select' && this.editingFeature === this.selectInteraction.getFeatures().getArray()[0]
      this.editingFeature.setStyle(this.generateFeatureStyle(label, isCurrentlySelected))
      const featureInArray = this.geoJson.features.find(el => el.id === this.editingFeature.getId())
      featureInArray.properties.label = label
    },

    updateUrl () {
      const regex = /(annotatedStatementPdf\/)(.*?)(\/)/
      const newUrl = window.location.href.replace(regex, '$1' + this.documentId + '$3')
      window.history.pushState({ html: newUrl, pageTitle: document.title }, document.title, newUrl)
    }
  },

  mounted () {
    this.getInitialData()

    /*
     * Display a warning for firefox if "privacy.resistFingerprinting" is enabled,
     * because openLayers' getFeaturesAtPixel() will not behave correctly then.
     */
    useResistFingerprintingDuckTest((isEnabled) => {
      if (isEnabled) {
        dplan.notify.notify('error', Translator.trans('warning.resistFingerPrinting'))
      }
    })
  }
}

const useResistFingerprintingDuckTest = (callback) => {
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')

  // Draw something on the canvas
  ctx.fillStyle = 'rgb(0,0,0)'
  ctx.fillRect(0, 0, 10, 10)

  // Convert canvas to data URL
  const dataUrl = canvas.toDataURL()

  // Check if the produced data URL corresponds to the expected black square
  const image = new Image()

  image.onload = function () {
    // Draw the image onto a new canvas to read the pixel values
    const testCanvas = document.createElement('canvas')
    const testCtx = testCanvas.getContext('2d')
    testCanvas.width = image.width
    testCanvas.height = image.height
    testCtx.drawImage(image, 0, 0)

    // Check the first pixel
    const pixelData = testCtx.getImageData(0, 0, 1, 1).data

    // If the pixel is black, we assume that resistFingerprinting is disabled
    const resistFingerprintingEnabled = !(pixelData[0] === 0 && pixelData[1] === 0 && pixelData[2] === 0)

    callback(resistFingerprintingEnabled)
  }

  image.src = dataUrl
}
</script>
