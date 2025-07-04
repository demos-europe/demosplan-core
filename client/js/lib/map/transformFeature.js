import proj4 from 'proj4'

function transformFeatureCollection (featureCollection, sourceProjection, targetProjection = 'EPSG:3857') {
  const transformedFeatures = featureCollection.features.map(feature => {
    console.log('transform Feature', feature)
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



// function transformGeometry (geometry, sourceProjection, targetProjection = 'EPSG:3857') {
//   proj4.defs(sourceProjection[0], sourceProjection[1])
//   const transformer = proj4(sourceProjection[0], targetProjection)
//
//   switch (geometry.type) {
//     case 'Point':
//       return {
//         ...geometry,
//         coordinates: transformer.forward(geometry.coordinates)
//       }
//     case 'LineString':
//     case 'MultiPoint':
//       return {
//         ...geometry,
//         coordinates: geometry.coordinates.map(coord => transformer.forward(coord))
//       }
//     case 'Polygon':
//     case 'MultiLineString':
//       return {
//         ...geometry,
//         coordinates: geometry.coordinates.map(ring => ring.map(coord => transformer.forward(coord)))
//       }
//     case 'MultiPolygon':
//       return {
//         ...geometry,
//         coordinates: geometry.coordinates.map(polygon => {
//           return polygon.map(ring => {
//             console.log('multipolygon ring', ring)
//             return ring.map(coord => transformer.forward(coord))
//           })
//         })
//       }
//     default:
//       return geometry
//   }
// }

export { transformFeatureCollection, transformGeometry }
