import convertExtentToFlatArray from '@DpJs/components/map/map/utils/convertExtentToFlatArray'
import { dpApi } from '@demos-europe/demosplan-ui'

export default {
  namespaced: true,

  name: 'ProcedureMapSettings',

  actions: {
    fetchProcedureMapSettings ({ commit }, procedureId) {
      try {
        const url = Routing.generate('api_resource_get', { resourceId: procedureId, resourceType: 'Procedure' })
        const procedureMapSettingFields = ['availableScales',
          'boundingBox',
          'copyright',
          'defaultBoundingBox',
          'defaultMapExtent',
          'featureInfoUrl',
          'informationUrl',
          'mapExtent',
          'scales'
        ]
        if (hasPermission('area_procedure_adjustments_general_location')) {
          procedureMapSettingFields.push('coordinate')
        }

        if (hasPermission('feature_map_use_territory')) {
          procedureMapSettingFields.push('territory')
        }

        if (hasPermission('feature_layer_groups_alternate_visibility')) {
          procedureMapSettingFields.push('showOnlyOverlayCategory')
        }

        const params = {
          fields: {
            Procedure: [
              'mapSetting'
            ].join(),
            ProcedureMapSetting: procedureMapSettingFields.join()
          },
          include: 'mapSetting'
        }

        return dpApi.get(url, params)
          .then(response => {
            const data = response.data.included[0].attributes
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
                territory: data.territory ?? '{}'
              },
              id: response.data.included[0].id,
              type: 'ProcecdureMapSetting'
            }

            return procedureMapSettings
          })
      } catch (e) {
        console.error(e)
      }
    }
  }
}
