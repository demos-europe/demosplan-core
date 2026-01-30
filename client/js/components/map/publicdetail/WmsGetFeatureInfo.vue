<template>
  <dp-slidebar data-cy="layerFeatureInfoSidebar">
    <div
      v-if="layersFeatureInfoResults?.length >= 1"
      class="flex items-baseline justify-between gap-2 mr-3 mb-4 max-w-full"
    >
      <dp-button
        :aria-label="Translator.trans('map.next.feature.info')"
        :class="{ invisible: currentLayerFeatureInfoPage === 1 }"
        color="primary"
        icon="chevron-left"
        variant="outline"
        hide-text
        @click="showPreviousLayerFeatureInfo"
      />

      <h3 class="flex-1 text-center font-bold truncate">
        {{ currentLayerFeatureInfoResult.layerName }}
      </h3>

      <dp-button
        :aria-label="Translator.trans('map.previous.feature.info')"
        :class="{ invisible: currentLayerFeatureInfoPage === layersFeatureInfoResults.length }"
        color="primary"
        icon="chevron-right"
        variant="outline"
        hide-text
        @click="showNextLayerFeatureInfo"
      />
    </div>

    <div
      v-if="currentLayerFeatureInfoResult"
      class="mb-4 mr-2"
      v-html="currentLayerFeatureInfoResult.content"
    />
  </dp-slidebar>
</template>

<script>
import { DpButton, DpSlidebar, externalApi } from '@demos-europe/demosplan-ui'
import DomPurify from 'dompurify'
import { TileWMS } from 'ol/source'

export default {
  name: 'WmsGetFeatureInfo',

  components: {
    DpButton,
    DpSlidebar,
  },

  data () {
    return {
      currentLayerFeatureInfoPage: 1,
      layersFeatureInfoResults: null,
    }
  },

  computed: {
    currentLayerFeatureInfoResult () {
      if (!this.layersFeatureInfoResults?.length) return null
      return this.layersFeatureInfoResults[this.currentLayerFeatureInfoPage - 1]
    },

    visibleOverlayLayers () {
      const allOverlayLayers = this.$store.getters['Layers/gisLayerList']('overlay')

      return allOverlayLayers.filter(layer => {
        return this.$store.getters['Layers/isLayerVisible'](layer.id) &&
          (layer.attributes.serviceType === 'wms')
      })
    },
  },

  watch: {
    layersFeatureInfoResults () {
      this.currentLayerFeatureInfoPage = 1
    },
  },

  methods: {
    /**
     * Builds a WMS GetFeatureInfo URL for a layer at a specific coordinate
     * Creates a temporary TileWMS to build the URL (not added to map)
     *
     * @param {object} storeLayer - Layer object from Vuex store
     * @param {Array} coordinate - Map coordinate [x, y]
     * @param {number} viewResolution - Current map view resolution
     * @param {Projection} mapProjection - OpenLayers Projection instance
     * @returns {string|null} GetFeatureInfo request URL, or null if data is missing
     */
    buildLayerFeatureInfoUrl (storeLayer, coordinate, viewResolution, mapProjection) {
      const { layers, layerVersion = '1.3.0', url } = storeLayer?.attributes || {}

      if (!layers || !url) {
        return null
      }

      let baseUrl

      try {
        const parsedUrl = new URL(url)
        const paramsToRemove = ['REQUEST', 'SERVICE', 'VERSION']

        paramsToRemove.forEach(param => {
          parsedUrl.searchParams.delete(param)
          parsedUrl.searchParams.delete(param.toLowerCase())
        })

        baseUrl = parsedUrl.toString()
      } catch {
        baseUrl = url
      }


      const tempSource = new TileWMS({
        url: baseUrl,
        projection: mapProjection,
        params: {
          LAYERS: layers,
          VERSION: layerVersion,
        },
      })

      return tempSource.getFeatureInfoUrl(
        coordinate,
        viewResolution,
        mapProjection,
        {
          INFO_FORMAT: 'text/html',
          QUERY_LAYERS: layers,
        },
      )
    },

    /**
     * Checks if a single GetFeatureInfo response contains tables and if they are empty
     */
    isEmptyFeatureInfoResponse (content) {
      if (!content || content.trim().length === 0) {
        return true
      }

      const parser = new DOMParser()
      const doc = parser.parseFromString(content, 'text/html')
      const tables = doc.querySelectorAll('table')

      // Response contains other elements than table - keep
      if (tables.length === 0) {
        return false
      }

      // Check if any table contains meaningful content
      for (const table of tables) {
        const cells = table.querySelectorAll('td, th')

        for (const cell of cells) {
          if (cell.textContent.trim().length > 0) {
            return false // Table has data - keep
          }
        }
      }

      // All tables are empty
      return true
    },

    /**
     * Handles GetFeatureInfo queries for all visible WMS overlay layers
     * @param {MapBrowserEvent} evt - OpenLayers map click event (in Map.vue)
     * @param {View} mapView - OpenLayers View instance
     * @param {Projection} mapProjection - OpenLayers Projection instance
     */
    queryLayerFeatureInfo (evt, mapView, mapProjection) {
      const coordinate = evt.coordinate
      const viewResolution = mapView.getResolution()

      if (this.visibleOverlayLayers.length === 0) {
        dplan.notify.notify('warning', Translator.trans('map.getfeatureinfo.no.visible.wms.layers'))

        return
      }

      const promises = this.visibleOverlayLayers.map(layer => {
        const layerFeatureInfoUrl = this.buildLayerFeatureInfoUrl(layer, coordinate, viewResolution, mapProjection)

        if (!layerFeatureInfoUrl) {
          return Promise.resolve(null)
        }

        // Each request catches its own errors
        return externalApi(layerFeatureInfoUrl)
          .then(response => response.text())
          .then(content => ({
            layerName: layer.attributes.name,
            layerId: layer.id,
            content: content,
            success: true,
          }))
          .catch(error => {
            console.error(`GetFeatureInfo failed for ${layer.attributes.name}:`, error)
            return {
              success: false,
              error: error.message,
            }
          })
      })

      Promise.all(promises)
        .then(results => {
          const allResults = results.filter(result => result !== null)

          // Filter out responses with empty tables
          const validResults = allResults.filter(result =>
            result.success &&
            result.content &&
            !this.isEmptyFeatureInfoResponse(result.content)
          )

          const failedResults = allResults.filter(result => !result.success)

          // All requests failed
          if (allResults.length > 0 && failedResults.length === allResults.length) {
            dplan.notify.notify('error', Translator.trans('error.map.getfeatureinfo.request.failed'))

            return
          }

          const sanitizedResults = this.sanitizeFeatureInfoResults(validResults)

          this.layersFeatureInfoResults = sanitizedResults

          if (this.layersFeatureInfoResults.length > 0) {
            this.$root.$emit('show-slidebar')
          } else {
            this.$root.$emit('hide-slidebar')
            dplan.notify.notify('info', Translator.trans('map.getfeatureinfo.none'))
          }
        })
    },

    /**
     * Sanitizes WMS feature info HTML content and converts plain URLs to clickable safe links
     * @param {Array} validResults - Array of feature info results [{layerName, layerId, content}]
     * @returns {Array} Sanitized results
     */
    sanitizeFeatureInfoResults (validResults) {
      const processTextNodeUrls = (node) => {
        const text = node.textContent
        const urlRegex = /https?:\/\/[^\s<>]+/g
        const matches = [...text.matchAll(urlRegex)]

        if (matches.length === 0) {
          return
        }

        const fragment = document.createDocumentFragment()
        let lastIndex = 0

        matches.forEach(match => {
          let url = match[0]
          const index = match.index
          const trailingPunctuation = '.,;!?'
          let endIndex = url.length
          while (endIndex > 0 && trailingPunctuation.includes(url[endIndex - 1])) {
            endIndex--
          }

          if (endIndex < url.length) {
            url = url.slice(0, endIndex)
          }

          // Keep text before URL
          if (index > lastIndex) {
            fragment.appendChild(
              document.createTextNode(text.slice(lastIndex, index))
            )
          }

          const link = document.createElement('a')
          link.href = url
          link.textContent = 'Link'
          link.target = '_blank'
          link.rel = 'noopener noreferrer'
          fragment.appendChild(link)
          lastIndex = index + url.length
        })

        // Keep remaining text
        if (lastIndex < text.length) {
          fragment.appendChild(
            document.createTextNode(text.slice(lastIndex))
          )
        }

        node.parentNode.replaceChild(fragment, node)
      }

      // Recursively process all nodes
      const processTextNodes = (node) => {
        if (node.nodeType === Node.TEXT_NODE) {
          processTextNodeUrls(node)

          return
        }

        if (node.nodeType === Node.ELEMENT_NODE) {
          Array.from(node.childNodes).forEach(processTextNodes)
        }
      }

      // Main processing
      return validResults.map(result => {
        const cleanContent = DomPurify.sanitize(result.content, {
          ADD_ATTR: ['target'],
          FORBID_TAGS: ['style', 'script', 'a'],
        })
        const parser = new DOMParser()
        const doc = parser.parseFromString(cleanContent, 'text/html')

        processTextNodes(doc.body)

        return {
          layerName: result.layerName,
          layerId: result.layerId,
          content: doc.body.innerHTML,
        }
      })
    },

    showNextLayerFeatureInfo () {
      if (this.currentLayerFeatureInfoPage < this.layersFeatureInfoResults.length) {
        this.currentLayerFeatureInfoPage++
      }
    },

    showPreviousLayerFeatureInfo () {
      if (this.currentLayerFeatureInfoPage > 1) {
        this.currentLayerFeatureInfoPage--
      }
    },
  },
}
</script>
