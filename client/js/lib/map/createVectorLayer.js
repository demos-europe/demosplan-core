import Fill from 'ol/style/Fill'
import { GeoJSON } from 'ol/format'
import Style from 'ol/style/Style'
import Stroke from 'ol/style/Stroke'
import VectorLayer from 'ol/layer/Vector'
import VectorSource from 'ol/source/Vector'

// Create a vector layer using the vector source
export const createVectorLayer = (features, style) =>{
  return new VectorLayer({
    source: createVectorSource(features),
    style: vectorStyle(style)
  })
}

// Create a vector source using the features
const createVectorSource = (features) => {
  return new VectorSource({
    features: new GeoJSON().readFeatures(features, {
      featureProjection: 'EPSG:3857' // Ensure the features are in the correct projection
    })
  })
}

// Define a style
const vectorStyle = (style) => {
  return new Style({
    stroke: new Stroke({
      color: style.strokeColor,
      width: 3
    }),
    fill: new Fill({
      color: style.fillColor
    })
  })
}
