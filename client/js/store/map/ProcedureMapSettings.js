import { checkResponse, dpApi } from '@demos-europe/demosplan-ui'
import convertExtentToFlatArray from '@DpJs/components/map/map/utils/convertExtentToFlatArray'

export default {
  namespaced: true,

  name: 'ProcedureMapSettings',

  state: {
    procedureMapSettings: {}
  },

  mutations: {
    setItem (state, { key, value }) {
      state[key] = value
    }
  },

  actions: {
    fetchLayers ({ commit }, procedureId) {
      const url = Routing.generate('api_resource_list', { resourceType: 'GisLayer' })

      const params = {
        fields: {
          GisLayer: [
            'name',
            'url',
            'isEnabled',
            'mapOrder',
            'opacity',
            'hasDefaultVisibility',
            'layers',
            'projectionValue'
          ].join()
        }
      }

      return dpApi.get(url, params)
        .then(response => checkResponse(response))
    },

    fetchProcedureMapSettings ({ commit }, procedureId) {
      try {
        const url = Routing.generate('api_resource_get', { resourceId: procedureId, resourceType: 'ProcedureTemplateResourceType' })
        const procedureMapSettingFields = ['availableScales',
          'boundingBox',
          'defaultBoundingBox',
          'defaultMapExtent',
          'scales'
        ]
        if (hasPermission('area_procedure_adjustments_general_location')) {
          procedureMapSettingFields.push('coordinate')
        }

        if (hasPermission('feature_map_max_extent')) {
          procedureMapSettingFields.push('mapExtent')
        }

        if (hasPermission('feature_map_feature_info')) {
          procedureMapSettingFields.push('informationUrl')
          procedureMapSettingFields.push('featureInfoUrl')
        }

        if (hasPermission('feature_map_attribution')) {
          procedureMapSettingFields.push('copyright')
        }

        if (hasPermission('feature_map_use_territory')) {
          procedureMapSettingFields.push('territory')
        }

        if (hasPermission('feature_layer_groups_alternate_visibility')) {
          procedureMapSettingFields.push('showOnlyOverlayCategory')
        }

        const params = {
          fields: {
            ProcedureTemplate: [
              'mapSetting'
            ].join(),
            ProcedureMapSetting: procedureMapSettingFields.join()
          },
          include: 'mapSetting'
        }

        return dpApi.get(url, params)
          .then(response => checkResponse(response))
          .then(response => {
            const data = response.included[0].attributes
            const defaultBoundingBox = convertExtentToFlatArray(data.defaultBoundingBox) ?? []
            const defaultMapExtent = convertExtentToFlatArray(data.defaultMapExtent) ?? []

            const procedureMapSettings = {
              attributes: {
                availableScales: data.availableScales.map(scale => ({ label: `1:${scale.toLocaleString('de-DE')}`, value: scale })) ?? [],
                coordinate: convertExtentToFlatArray(data.coordinate) ?? '',
                copyright: data.copyright ?? '',
                defaultBoundingBox: defaultBoundingBox,
                defaultMapExtent: defaultMapExtent,
                featureInfoUrl: data.featureInfoUrl ?? { global: false },
                informationUrl: data.informationUrl ?? '',
                showOnlyOverlayCategory: data.showOnlyOverlayCategory ?? false,
                mapExtent: convertExtentToFlatArray(data.mapExtent) ?? defaultMapExtent, // Maximum extent of the map
                boundingBox: convertExtentToFlatArray(data.boundingBox) ?? defaultBoundingBox, // Extent on load of the map
                scales: data.scales?.map(scale => ({ label: `1:${scale.toLocaleString()}`, value: scale })) ?? [],
                territory: data.territory ?? {}
              },
              id: response.included[0].id,
              type: 'ProcecdureMapSetting'
            }

            commit('setItem', { key: 'procedureMapSettings', value: procedureMapSettings })
            return procedureMapSettings
          })
      } catch (e) {
        console.error(e)
      }
    }
  }
}
