/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * Leaflet.markercluster's UMD wrapper reads the bare global `L` with no require('leaflet') of
 * its own - it must find `window.L` already set before it's imported, or it throws. Babel hoists
 * every static `import` in a module above any interspersed code, so this assignment has to live
 * in its own single-import module to reliably run before `leaflet.markercluster` is imported.
 */
import L from 'leaflet'

window.L = L
