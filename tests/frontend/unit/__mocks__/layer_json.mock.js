/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export const apiData = {
  data: {
    type: 'GisLayerCategory',
    id: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1',
    attributes: {
      name: 'root',
      layerWithChildrenHidden: false,
      treeOrder: 0,
      isVisible: false,
      hasDefaultVisibility: false,
      parentId: null
    },
    relationships: {
      categories: {
        data: [{
          type: 'GisLayerCategory',
          id: '0ccdef11-dc2a-11e8-884a-782bcb0d78b1'
        }, {
          type: 'GisLayerCategory',
          id: '17de9a58-d69f-11e8-b945-51361dec4aad'
        }, {
          type: 'GisLayerCategory',
          id: '24610932-dc3c-11e8-884a-782bcb0d78b1'
        }, {
          type: 'GisLayerCategory',
          id: '6b6c2c08-4a18-11e8-b9d0-782bcb0d78b1'
        }, {
          type: 'GisLayerCategory',
          id: '6bbc345c-dc29-11e8-884a-782bcb0d78b1'
        }, {
          type: 'GisLayerCategory',
          id: '77c9fba8-dc29-11e8-884a-782bcb0d78b1'
        }, {
          type: 'GisLayerCategory',
          id: '929d6a36-dc1e-11e8-884a-782bcb0d78b1'
        }, {
          type: 'GisLayerCategory',
          id: 'ba83fe31-dc28-11e8-884a-782bcb0d78b1'
        }, {
          type: 'GisLayerCategory',
          id: 'd608e785-47a5-11e8-b9d0-782bcb0d78b1'
        }, { type: 'GisLayerCategory', id: 'd7636f26-4f9d-11e8-ba0c-782bcb0d78b1' }]
      },
      gisLayers: {
        data: [{
          type: 'GisLayer',
          id: '4586ce71-f68a-11e5-b083-005056ae0004'
        }, { type: 'GisLayer', id: '458971b7-f68a-11e5-b083-005056ae0004' }, {
          type: 'GisLayer',
          id: '458f3789-f68a-11e5-b083-005056ae0004'
        }, { type: 'GisLayer', id: '45901fa5-f68a-11e5-b083-005056ae0004' }, {
          type: 'GisLayer',
          id: '52ce3870-96d4-11e6-836d-005056ae0004'
        }, { type: 'GisLayer', id: 'e90f4f76-9a33-11e8-bb1e-564d819c8ce7' }]
      }
    }
  },
  included: [{
    type: 'ContextualHelp',
    id: 'c0d38b35-399f-11e8-b9d0-782bcb0d78b1',
    attributes: { key: 'gislayer.c0cf4d07-399f-11e8-b9d0-782bcb0d78b1', text: '' }
  }, {
    type: 'GisLayer',
    id: 'c0cf4d07-399f-11e8-b9d0-782bcb0d78b1',
    attributes: {
      hasDefaultVisibility: true,
      isBplan: true,
      isPrint: false,
      isEnabled: true,
      isXplan: false,
      legend: '',
      layers: 'de_basemapde_web_raster_farbe',
      name: 'testlayer als bplan',
      mapOrder: 1,
      opacity: 100,
      procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
      serviceType: 'wms',
      type: 'overlay',
      isBaseLayer: false,
      tileMatrixSet: '',
      url: 'https://sgx.geodatenzentrum.de/wms_basemapde',
      treeOrder: 1040101,
      categoryId: 'bbac18e1-47a5-11e8-b9d0-782bcb0d78b1',
      canUserToggleVisibility: true,
      visibilityGroupId: null,
      isScope: false,
      createdAt: '2018-04-06T15:39:15+00:00'
    },
    relationships: {
      contextualHelp: {
        data: {
          type: 'ContextualHelp',
          id: 'c0d38b35-399f-11e8-b9d0-782bcb0d78b1'
        }
      }
    }
  }, {
    type: 'ContextualHelp',
    id: '1fa4bf24-53ab-11e8-bcc2-782bcb0d78b1',
    attributes: { key: 'gislayer.1fa1d338-53ab-11e8-bcc2-782bcb0d78b1', text: 'kontext-hilfe' }
  }, {
    type: 'ContextualHelp',
    id: 'b01d50f2-959f-11e8-bb1e-564d819c8ce7',
    attributes: { key: 'gislayer.b019dab9-959f-11e8-bb1e-564d819c8ce7', text: 'find me !' }
  }, {
    type: 'GisLayer',
    id: '1fa1d338-53ab-11e8-bcc2-782bcb0d78b1',
    attributes: {
      hasDefaultVisibility: true,
      isBplan: true,
      isPrint: false,
      isEnabled: true,
      isXplan: false,
      legend: '',
      layers: 'de_basemapde_web_raster_farbe',
      name: 'Sowieso',
      mapOrder: 0,
      opacity: 100,
      procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
      serviceType: 'wms',
      type: 'overlay',
      isBaseLayer: false,
      tileMatrixSet: '',
      url: 'https://sgx.geodatenzentrum.de/wms_basemapde',
      treeOrder: 1080101,
      categoryId: '7b614b15-dc3c-11e8-884a-782bcb0d78b1',
      canUserToggleVisibility: true,
      visibilityGroupId: null,
      isScope: false,
      createdAt: '2018-05-09T19:06:02+00:00'
    },
    relationships: {
      contextualHelp: {
        data: {
          type: 'ContextualHelp',
          id: '1fa4bf24-53ab-11e8-bcc2-782bcb0d78b1'
        }
      }
    }
  }, {
    type: 'GisLayer',
    id: 'b019dab9-959f-11e8-bb1e-564d819c8ce7',
    attributes: {
      hasDefaultVisibility: true,
      isBplan: false,
      isPrint: true,
      isEnabled: true,
      isXplan: false,
      legend: '',
      layers: 'de_basemapde_web_raster_farbe',
      name: 'Vorranggebiete',
      mapOrder: 0,
      opacity: 55,
      procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
      serviceType: 'wms',
      type: 'overlay',
      isBaseLayer: false,
      tileMatrixSet: '',
      url: 'https://sgx.geodatenzentrum.de/wms_basemapde?service=WMS&request=GetCapabilities',
      treeOrder: 1080102,
      categoryId: '7b614b15-dc3c-11e8-884a-782bcb0d78b1',
      canUserToggleVisibility: true,
      visibilityGroupId: null,
      isScope: false,
      createdAt: '2018-08-01T17:29:33+00:00'
    },
    relationships: {
      contextualHelp: {
        data: {
          type: 'ContextualHelp',
          id: 'b01d50f2-959f-11e8-bb1e-564d819c8ce7'
        }
      }
    }
  }, {
    type: 'GisLayerCategory',
    id: '1c6ea341-dc3c-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'TEST D (layerWithChildrenHidden: false)',
      layerWithChildrenHidden: false,
      treeOrder: 10201,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '17de9a58-d69f-11e8-b945-51361dec4aad'
    },
    relationships: { categories: { data: [] }, gisLayers: { data: [] } }
  }, {
    type: 'GisLayerCategory',
    id: '68fac8ed-dc2a-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'TEST D (layerWithChildrenHidden: true)',
      layerWithChildrenHidden: false,
      treeOrder: 10301,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '24610932-dc3c-11e8-884a-782bcb0d78b1'
    },
    relationships: { categories: { data: [] }, gisLayers: { data: [] } }
  }, {
    type: 'GisLayerCategory',
    id: '818aa46a-dc3c-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'TEST G (layerWithChildrenHidden: true) EDITED2 (true) (false)',
      layerWithChildrenHidden: true,
      treeOrder: 10302,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '24610932-dc3c-11e8-884a-782bcb0d78b1'
    },
    relationships: { categories: { data: [] }, gisLayers: { data: [] } }
  }, {
    type: 'GisLayerCategory',
    id: 'bbac18e1-47a5-11e8-b9d0-782bcb0d78b1',
    attributes: {
      name: '454564',
      layerWithChildrenHidden: false,
      treeOrder: 10401,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '6b6c2c08-4a18-11e8-b9d0-782bcb0d78b1'
    },
    relationships: {
      categories: { data: [] },
      gisLayers: { data: [{ type: 'GisLayer', id: 'c0cf4d07-399f-11e8-b9d0-782bcb0d78b1' }] }
    }
  }, {
    type: 'GisLayerCategory',
    id: '7b614b15-dc3c-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'TEST F (layerWithChildrenHidden: false) EDITED2',
      layerWithChildrenHidden: true,
      treeOrder: 10801,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: 'ba83fe31-dc28-11e8-884a-782bcb0d78b1'
    },
    relationships: {
      categories: { data: [] },
      gisLayers: {
        data: [{
          type: 'GisLayer',
          id: '1fa1d338-53ab-11e8-bcc2-782bcb0d78b1'
        }, { type: 'GisLayer', id: 'b019dab9-959f-11e8-bb1e-564d819c8ce7' }]
      }
    }
  }, {
    type: 'ContextualHelp',
    id: '4e2ed57a-4de6-11e8-ba0c-782bcb0d78b1',
    attributes: { key: 'gislayer.458971b7-f68a-11e5-b083-005056ae0004', text: '' }
  }, {
    type: 'ContextualHelp',
    id: 'd4d7b3cc-4c8d-11e8-b9d0-782bcb0d78b1',
    attributes: { key: 'gislayer.458f3789-f68a-11e5-b083-005056ae0004', text: '' }
  }, {
    type: 'ContextualHelp',
    id: '52cff390-96d4-11e6-836d-005056ae0004',
    attributes: { key: 'gislayer.52ce3870-96d4-11e6-836d-005056ae0004', text: '' }
  }, {
    type: 'ContextualHelp',
    id: 'e916937b-9a33-11e8-bb1e-564d819c8ce7',
    attributes: { key: 'gislayer.e90f4f76-9a33-11e8-bb1e-564d819c8ce7', text: '' }
  }, {
    type: 'GisLayerCategory',
    id: '0ccdef11-dc2a-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'TEST C (layerWithChildrenHidden: true)',
      layerWithChildrenHidden: false,
      treeOrder: 101,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: { categories: { data: [] }, gisLayers: { data: [] } }
  }, {
    type: 'GisLayerCategory',
    id: '17de9a58-d69f-11e8-b945-51361dec4aad',
    attributes: {
      name: 'TestKategorie',
      layerWithChildrenHidden: false,
      treeOrder: 102,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: {
      categories: {
        data: [{
          type: 'GisLayerCategory',
          id: '1c6ea341-dc3c-11e8-884a-782bcb0d78b1'
        }]
      },
      gisLayers: { data: [] }
    }
  }, {
    type: 'GisLayerCategory',
    id: '24610932-dc3c-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'TEST E (layerWithChildrenHidden: true) EDITED',
      layerWithChildrenHidden: true,
      treeOrder: 103,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: {
      categories: {
        data: [{
          type: 'GisLayerCategory',
          id: '68fac8ed-dc2a-11e8-884a-782bcb0d78b1'
        }, { type: 'GisLayerCategory', id: '818aa46a-dc3c-11e8-884a-782bcb0d78b1' }]
      },
      gisLayers: { data: [] }
    }
  }, {
    type: 'GisLayerCategory',
    id: '6b6c2c08-4a18-11e8-b9d0-782bcb0d78b1',
    attributes: {
      name: 'hello Welt',
      layerWithChildrenHidden: false,
      treeOrder: 104,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: {
      categories: {
        data: [{
          type: 'GisLayerCategory',
          id: 'bbac18e1-47a5-11e8-b9d0-782bcb0d78b1'
        }]
      },
      gisLayers: { data: [] }
    }
  }, {
    type: 'GisLayerCategory',
    id: '6bbc345c-dc29-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'TEST A (layerWithChildrenHidden: false)',
      layerWithChildrenHidden: false,
      treeOrder: 105,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: { categories: { data: [] }, gisLayers: { data: [] } }
  }, {
    type: 'GisLayerCategory',
    id: '77c9fba8-dc29-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'TEST B (layerWithChildrenHidden: true) EDITED',
      layerWithChildrenHidden: true,
      treeOrder: 106,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: { categories: { data: [] }, gisLayers: { data: [] } }
  }, {
    type: 'GisLayerCategory',
    id: '929d6a36-dc1e-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'Test gruppe -\u003E soll kinder verstecken',
      layerWithChildrenHidden: true,
      treeOrder: 107,
      isVisible: false,
      hasDefaultVisibility: false,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: { categories: { data: [] }, gisLayers: { data: [] } }
  }, {
    type: 'GisLayerCategory',
    id: 'ba83fe31-dc28-11e8-884a-782bcb0d78b1',
    attributes: {
      name: 'ielevl',
      layerWithChildrenHidden: false,
      treeOrder: 108,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: {
      categories: {
        data: [{
          type: 'GisLayerCategory',
          id: '7b614b15-dc3c-11e8-884a-782bcb0d78b1'
        }]
      },
      gisLayers: { data: [] }
    }
  }, {
    type: 'GisLayerCategory',
    id: 'd608e785-47a5-11e8-b9d0-782bcb0d78b1',
    attributes: {
      name: '33333333333333',
      layerWithChildrenHidden: false,
      treeOrder: 110,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: { categories: { data: [] }, gisLayers: { data: [] } }
  }, {
    type: 'GisLayerCategory',
    id: 'd7636f26-4f9d-11e8-ba0c-782bcb0d78b1',
    attributes: {
      name: 'ööööööö',
      layerWithChildrenHidden: false,
      treeOrder: 109,
      isVisible: true,
      hasDefaultVisibility: true,
      parentId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1'
    },
    relationships: { categories: { data: [] }, gisLayers: { data: [] } }
  }, {
    type: 'GisLayer',
    id: '4586ce71-f68a-11e5-b083-005056ae0004',
    attributes: {
      hasDefaultVisibility: true,
      isBplan: false,
      isPrint: false,
      isEnabled: true,
      isXplan: false,
      legend: '',
      layers: 'de_basemapde_web_raster_farbe',
      name: 'Stadtkarte ORKa',
      mapOrder: 3,
      opacity: 100,
      procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
      serviceType: 'wms',
      type: 'base',
      isBaseLayer: true,
      tileMatrixSet: null,
      url: 'https://sgx.geodatenzentrum.de/wms_basemapde??service=WMS&version=1.3.0&request=GetMap',
      treeOrder: 4,
      categoryId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1',
      canUserToggleVisibility: true,
      visibilityGroupId: null,
      isScope: false,
      createdAt: '2016-03-30T17:15:42+00:00'
    }
  }, {
    type: 'GisLayer',
    id: '458971b7-f68a-11e5-b083-005056ae0004',
    attributes: {
      hasDefaultVisibility: true,
      isBplan: false,
      isPrint: false,
      isEnabled: true,
      isXplan: false,
      legend: '',
      layers: 'de_basemapde_web_raster_farbe',
      name: 'Digitale Orthophotos (Luftbilder)',
      mapOrder: 1,
      opacity: 100,
      procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
      serviceType: 'wms',
      type: 'base',
      isBaseLayer: true,
      tileMatrixSet: '',
      url: 'https://sgx.geodatenzentrum.de/wms_basemapde',
      treeOrder: 5,
      categoryId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1',
      canUserToggleVisibility: true,
      visibilityGroupId: null,
      isScope: false,
      createdAt: '2016-03-30T17:15:42+00:00'
    },
    relationships: {
      contextualHelp: {
        data: {
          type: 'ContextualHelp',
          id: '4e2ed57a-4de6-11e8-ba0c-782bcb0d78b1'
        }
      }
    }
  }, {
    type: 'GisLayer',
    id: '458f3789-f68a-11e5-b083-005056ae0004',
    attributes: {
      hasDefaultVisibility: true,
      isBplan: false,
      isPrint: false,
      isEnabled: true,
      isXplan: false,
      legend: '',
      layers: 'de_basemapde_web_raster_farbe',
      name: 'Web-Atlas',
      mapOrder: 2,
      opacity: 100,
      procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
      serviceType: 'wms',
      type: 'base',
      isBaseLayer: true,
      tileMatrixSet: '',
      url: 'https://sgx.geodatenzentrum.de/wms_basemapde',
      treeOrder: 0,
      categoryId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1',
      canUserToggleVisibility: true,
      visibilityGroupId: null,
      isScope: false,
      createdAt: '2016-03-30T17:15:42+00:00'
    },
    relationships: {
      contextualHelp: {
        data: {
          type: 'ContextualHelp',
          id: 'd4d7b3cc-4c8d-11e8-b9d0-782bcb0d78b1'
        }
      }
    }
  }, {
    type: 'GisLayer',
    id: '45901fa5-f68a-11e5-b083-005056ae0004',
    attributes: {
      hasDefaultVisibility: true,
      isBplan: false,
      isPrint: false,
      isEnabled: true,
      isXplan: false,
      legend: '',
      layers: 'de_basemapde_web_raster_grau, de_basemapde_web_raster_farbe',
      name: 'Kreis-,Amts-,Gemeindegrenzen',
      mapOrder: 0,
      opacity: 100,
      procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
      serviceType: 'wms',
      type: 'base',
      isBaseLayer: true,
      tileMatrixSet: null,
      url: 'https://sgx.geodatenzentrum.de/wms_basemapde',
      treeOrder: 6,
      categoryId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1',
      canUserToggleVisibility: true,
      visibilityGroupId: '12345',
      isScope: false,
      createdAt: '2016-03-30T17:15:42+00:00'
    }
  }, {
    type: 'GisLayer',
    id: '52ce3870-96d4-11e6-836d-005056ae0004',
    attributes: {
      hasDefaultVisibility: true,
      isBplan: false,
      isPrint: false,
      isEnabled: true,
      isXplan: false,
      legend: '',
      layers: 'de_basemapde_web_raster_grau',
      name: 'Basemap Grundkarte',
      mapOrder: 4,
      opacity: 100,
      procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
      serviceType: 'wms',
      type: 'base',
      isBaseLayer: true,
      tileMatrixSet: '',
      url: 'https://sgx.geodatenzentrum.de/wms_basemapde',
      treeOrder: 7,
      categoryId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1',
      canUserToggleVisibility: true,
      visibilityGroupId: '123345',
      isScope: false,
      createdAt: '2016-10-20T16:48:54+00:00'
    },
    relationships: {
      contextualHelp: {
        data: {
          type: 'ContextualHelp',
          id: '52cff390-96d4-11e6-836d-005056ae0004'
        }
      }
    }
  }, {
    type: 'GisLayer',
    id: 'e90f4f76-9a33-11e8-bb1e-564d819c8ce7',
    attributes: {
      hasDefaultVisibility: true,
      isBplan: false,
      isPrint: false,
      isEnabled: true,
      isXplan: false,
      legend: '',
      layers: 'de_basemapde_web_raster_farbe',
      name: 'Harte Tabuzonen',
      mapOrder: 0,
      opacity: 100,
      procedureId: '45752f51-f68a-11e5-b083-005056ae0004',
      serviceType: 'wms',
      type: 'base',
      isBaseLayer: true,
      tileMatrixSet: '',
      url: 'https://sgx.geodatenzentrum.de/wms_basemapde',
      treeOrder: 0,
      categoryId: '1b6d38af-4225-11e8-b9d0-782bcb0d78b1',
      canUserToggleVisibility: true,
      visibilityGroupId: '55555',
      isScope: false,
      createdAt: '2018-08-07T13:20:39+00:00'
    },
    relationships: {
      contextualHelp: {
        data: {
          type: 'ContextualHelp',
          id: 'e916937b-9a33-11e8-bb1e-564d819c8ce7'
        }
      }
    }
  }],
  meta: { messages: { confirm: [{ message: 'Die Kartenebenen wurden gespeichert.', severity: 'confirm' }] } },
  jsonapi: { version: '1.0' }
}
