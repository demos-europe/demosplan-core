import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'
import { GeoJSON } from 'ol/format'

// Create a vector source using the features
const createVectorSource = (features) => {
  return new VectorSource({
    features: new GeoJSON().readFeatures(features, {
      featureProjection: 'EPSG:3857' // Ensure the features are in the correct projection
    })
  })
}

// Create a vector layer using the vector source
export const createVectorLayer = (features) =>{
  return new VectorLayer({
    source: createVectorSource(features)
  })
}
