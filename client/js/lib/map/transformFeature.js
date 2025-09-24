import proj4 from 'proj4'

function transformFeatureCollection (featureCollection, sourceProjection, targetProjection = 'EPSG:3857') {
  const transformedFeatures = featureCollection.features.map(feature => {
    const transformedGeometry = transformGeometry(feature.geometry, sourceProjection, targetProjection)

    return {
      ...feature,
      geometry: transformedGeometry
    }
  })

  return {
    ...featureCollection,
    features: transformedFeatures
  }
}

function transformGeometry (geometry, sourceProjection, targetProjection = 'EPSG:3857') {
  const transformer = proj4(sourceProjection, targetProjection)

  switch (geometry.type) {
    case 'Point':
      return {
        ...geometry,
        coordinates: transformer.forward([...geometry.coordinates])
      }
    case 'LineString':
    case 'MultiPoint':
      return {
        ...geometry,
        coordinates: geometry.coordinates.map(coord => transformer.forward(coord))
      }
    case 'Polygon':
    case 'MultiLineString':
      return {
        ...geometry,
        coordinates: geometry.coordinates.map(ring => ring.map(coord => transformer.forward(coord)))
      }
    case 'MultiPolygon':
      return {
        ...geometry,
        coordinates: geometry.coordinates.map(polygon =>
          polygon.map(ring => ring.map(coord => transformer.forward(coord)))
        )
      }
    default:
      return geometry
  }
}

function transformExtent (extent, sourceProjection, targetProjection = 'EPSG:3857') {
  if (!extent || extent.length !== 4) {
    return []
  }

  const transformer = proj4(sourceProjection, targetProjection)
  const [minX, minY, maxX, maxY] = extent

  const [minTransformedX, minTransformedY] = transformer.forward([minX, minY])
  const [maxTransformedX, maxTransformedY] = transformer.forward([maxX, maxY])

  return [
    Math.min(minTransformedX, maxTransformedX),
    Math.min(minTransformedY, maxTransformedY),
    Math.max(minTransformedX, maxTransformedX),
    Math.max(minTransformedY, maxTransformedY)
  ]
}

export { transformFeatureCollection, transformGeometry, transformExtent }
