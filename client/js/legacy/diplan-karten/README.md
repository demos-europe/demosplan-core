# DiPlanKarten

DiPlanKarten provides a reusable, self-contained and encapsulated [Web Component (WC)](https://developer.mozilla.org/en-US/docs/Web/API/Web_components). The web component can be embedded into a Vue app or in a pure HTML environment.

> ℹ️ **Caveats:** Be aware that while WCs add CSS isolation between parent and WC rules, there is still a [pretty long list of inherited CSS properties](https://web.dev/learn/css/inheritance#which_properties_are_inherited_by_default) from which elements in the WC inherite unless overwritten in the WC. OTOH, this provides a way for the parent app to pass default values for `color`, `font` etc

<!-- This ToC is auto-generated/updated using the VSCode extension "MarkDown All in One". See https://marketplace.visualstudio.com/items?itemName=yzhang.markdown-all-in-one#available-commands -->

**Table of Contents**:
- [DiPlanKarten](#diplankarten)
  - [Usage](#usage)
    - [Installation](#installation)
    - [Embedding into a Vue App](#embedding-into-a-vue-app)
      - [Register the custom element](#register-the-custom-element)
      - [Integration in `*.vue` component](#integration-in-vue-component)
    - [Embedding into a pure HTML (non-Vue) enviroment](#embedding-into-a-pure-html-non-vue-enviroment)
      - [Passing attributes](#passing-attributes)
      - [Listening to the WC's emitted custom events](#listening-to-the-wcs-emitted-custom-events)
    - [Input / Output parameters](#input--output-parameters)
      - [Input properties](#input-properties)
      - [Output emits / events](#output-emits--events)
    - [Getting `portalConfig` and `layerConfig` from Backend API](#getting-portalconfig-and-layerconfig-from-backend-api)
  - [Development](#development)
    - [Project Setup](#project-setup)
    - [Type Support for `.vue` Imports in TS](#type-support-for-vue-imports-in-ts)
    - [Customize configuration](#customize-configuration)


## Usage

The WC was built using Vue best practices. Depending on whether the WC is supposed to be included in an Vue App or in a pure HTML (non-Vue) environment, the *input* and *output* parameters to communicate with the WC need to be passed in a different way.

If you your environment is a Vue app anyway, then adding the ["Vue way"](https://vuejs.org/guide/extras/web-components) is the recommended way, because passing non-primitive DOM attributes (input) and listening the the emitted events (output) is easier. You can read more about this in [Passing DOM Properties](https://vuejs.org/guide/extras/web-components#passing-dom-properties). This is also the main difference between the two different ways of embedding the WC.

### Installation
If not done yet, follow the documentation on [Setup und Konfiguration NPM für ADO](https://www.dev.diplanung.de/DefaultCollection/E2E-Plattform/_wiki/wikis/E2E-Plattform.wiki/3962/Setup-und-Konfiguration-NPM-f%C3%BCr-ADO).

The npm package [`@init/diplan-karten`](https://www.dev.diplanung.de/DefaultCollection/DiPlanKarten/_packaging?_a=package&feed=karten.feed&package=%40init/diplan-karten&protocolType=Npm) can be installed with:

  ```sh
  npm install @init/diplan-karten
  ```

> ℹ️ **Important:** Vue 3.5 must (currently) be installed in the embedding parent app as a dependency.

### Embedding into a Vue App

#### Register the custom element

Following [Using Custom Elements in Vue](https://vuejs.org/guide/extras/web-components#using-custom-elements-in-vue) the WC's *custom element* must be registered. For Vite this can be done with adding the following to your `vite.config.js`:
```ts
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue({
      template: {
        compilerOptions: {
          // Register the custom element:
          isCustomElement: (tag) => tag === "diplan-karte",
        },
      },
    }),
  ],
});
```

#### Integration in `*.vue` component

In your Vue single file component you can add the WC now with:

```ts
// Some Vue component like App.vue
<script setup lang="ts">
import "@init/diplan-karten";
</script>

<template>
  <!-- Add some height and width to the surrounding container -->
  <div style="height: 80vh; width: 80vw">
    <diplan-karte
      :baseLayer="0"
      xplanWms="https://init.xplan.develop.diplanung.de/xplan-wms/services/planwerkwms/planname/0711dev?request=GetMap&service=WMS&version=1.3.0&format=image/png&transparent=true&exceptions=application/vnd.ogc.se_inimage&crs=epsg:25832&layers=BP_Planvektor,SO_Planvektor,BP_Planraster,SO_Planraster&bbox=643697.61,5358724.99,644042.674,5359014.65&width=346&height=290"
      @diplan-karte:geojson-update="(payload) => console.log('GeoJSON changed. Payload is: ', payload)"
    />
  </div>
</template>
```

> ℹ️ **Info:** Here you can see the main difference compared to embedding in a pure HTML environment: You can use the typical `:` and `@` parameters to pass *props* or listen to *emits*.

### Embedding into a pure HTML (non-Vue) enviroment

You need to install the npm package as outlined above. Then add the WC's javascript with:
```ts
import "@init/diplan-karten";
```

Then in your `*.html` file or whatever template (React, Angular, php, ...) you use, add the *custom element*:
```html
<!-- Add some height and width to the surrounding container -->
<div style="height: 80vh; width: 80vw">
  <diplan-karte
    base-layer="0"
    xplan-wms="https://init.xplan.develop.diplanung.de/xplan-wms/services/planwerkwms/planname/0711dev?request=GetMap&service=WMS&version=1.3.0&format=image/png&transparent=true&exceptions=application/vnd.ogc.se_inimage&crs=epsg:25832&layers=BP_Planvektor,SO_Planvektor,BP_Planraster,SO_Planraster&bbox=643697.61,5358724.99,644042.674,5359014.65&width=346&height=290"
  />
</div>
```

> ℹ️ **Info:** Please note how attributes have changed from Vue style *camelCase* to HTML style *kebab-case*.

#### Passing attributes
In a HTML environment DOM attributes are passed as strings. Vue automatically parses and casts primitive values like `string`, `boolean` or `number`. Please see https://vuejs.org/guide/extras/web-components#props for how this works.

However, non-primitive property values like `object` (including arrays) need to be passed in the following form using JavaScript/TypeScript in your `*.html`:
```html
<script>
  const diplanKarteInHtml = document.querySelector("diplan-karte");

  diplanKarteInHtml.geojson = [
    {
      type: "MultiPolygon",
      coordinates: [
        [
          [
            [10.943816922594532, 48.367342228591106],
            [10.943342033872245, 48.367590569959255],
            [10.942768373115076, 48.36789051249507],
            [10.942652672500834, 48.36779233706371],
            [10.942630640399422, 48.36777364414859],
            [10.94262479411202, 48.367768686473106],
            [10.942568416305498, 48.367720675985005],
            [10.943142115502466, 48.36742069779845],
            [10.942032776151125, 48.366479578162405],
            [10.942028352788736, 48.36647617093923],
            [10.942023310266606, 48.36647317004896],
            [10.942017731257186, 48.366470619081525],
            [10.94201171192662, 48.3664685613989],
            [10.942005361592791, 48.36646703114384],
            [10.94199876224404, 48.36646605392343],
            [10.941992022514215, 48.36646564589752],
            [10.941985263847005, 48.366465805015345],
            [10.941978594876245, 48.36646654743689],
            [10.941972122183993, 48.366467835373456],
            [10.941501893974479, 48.36658415536577],
            [10.94140595672794, 48.36660789012953],
            [10.94128592794548, 48.366637582774075],
            [10.94110707689432, 48.366489898465176],
            [10.940638653195055, 48.36610201674785],
            [10.940151736733235, 48.36569986962953],
            [10.940277496808962, 48.365683532293545],
            [10.940456358276457, 48.365668188242616],
            [10.940589034213938, 48.3656668491863],
            [10.940694608388602, 48.36565229157431],
            [10.940834025640427, 48.3656329340196],
            [10.941079151661857, 48.36559901605754],
            [10.941140470148918, 48.36558529498472],
            [10.941215898872677, 48.36557691396984],
            [10.941412878385158, 48.36555514448795],
            [10.94154890962117, 48.365531974424385],
            [10.941644561151447, 48.36551569404609],
            [10.941729838117936, 48.36550354753185],
            [10.94190727124686, 48.365461323668875],
            [10.941946882137659, 48.3654093706747],
            [10.942045351850195, 48.365357143491714],
            [10.942158856267872, 48.36532391635017],
            [10.942290250555198, 48.36529245630649],
            [10.942496939193468, 48.365469521345034],
            [10.944022054909611, 48.366761661968056],
            [10.94406493117926, 48.366797987683256],
            [10.944711650179523, 48.36734588988497],
            [10.944729067576446, 48.36736064588551],
            [10.9447366829983, 48.36736709586605],
            [10.944761724105607, 48.36738830620011],
            [10.944781097602151, 48.36740471609082],
            [10.944848169823908, 48.367461550852596],
            [10.94487896382951, 48.36748763483025],
            [10.944591393706363, 48.367635400514196],
            [10.944312778684743, 48.367778317522806],
            [10.94428283950366, 48.36775198501537],
            [10.944217652713348, 48.367694659194136],
            [10.944199172416054, 48.36767840685558],
            [10.944196403431905, 48.36767597133706],
            [10.944174723793006, 48.36765690297171],
            [10.944166324637836, 48.36764951519859],
            [10.944214184808695, 48.36762592514484],
            [10.94421347333062, 48.367625325362724],
            [10.944196968470116, 48.367611306024514],
            [10.943856111713682, 48.36732177232196],
            [10.943816922594532, 48.367342228591106],
          ],
        ],
      ],
    },
  ];

  diplanKarteInHtml.addEventListener("diplan-karte:geojson-update", (event) => {
    console.log("GeoJSON update:", event.detail);
  });

  diplanKarteInHtml.addEventListener("diplan-karte:fullscreen-update", (event) => {
    console.log("Fullscreen update:", event.detail);
  });
</script>
```

#### Listening to the WC's emitted custom events

When using the WC in a non-Vue (HTML) enviroment you cannot used the Vue `@` notation like
```ts
@diplan-karte:geojson-update="(payload) => console.log('GeoJSON changed. Payload is: ', payload)"
```

Instead you need the WC will dispatch a [custom event](https://developer.mozilla.org/en-US/docs/Web/API/CustomEvent/CustomEvent) named `"diplan-karte:geojson-update"` with the payload in its [`detail`](https://developer.mozilla.org/en-US/docs/Web/API/CustomEvent/detail) property:

```html
<script>
  const diplanKarteInHtml = document.querySelector("diplan-karte");

  diplanKarteInHtml.addEventListener("diplan-karte:geojson-update", (event) => {
    console.log("GeoJSON update:", event.detail);
  });

  diplanKarteInHtml.addEventListener("diplan-karte:fullscreen-update", (event) => {
    console.log("Fullscreen update:", event.detail);
  });
</script>
```

### Input / Output parameters

#### Input properties

The following input properties / attributes are supported by the WC. The properties align to what is defined in the [`MapFrame.ce.vue`](./src/components/map/MapFrame.ce.vue) source file.
```ts
const props = defineProps({
  enableDraw: { type: Boolean, default: true },
  disableZoom: { type: Boolean, default: false },
  // use just the extracted slices from the area down to the featurecollection array
  baseData: { type: Array as PropType<GeoJSONFeature[]>, default: () => [] },
  // type limited to Multipolygon in geojson notation
  geojson: { type: Array as PropType<GeoJSONMultiPolygon[]>, default: undefined },
  xplanWms: { type: String, default: undefined },
  isReduced: { type: Boolean, default: false },
  profile: { type: String as PropType<MapProfile>, default: MapProfile.COCKPIT },
  baseLayer: {
    type: Number as PropType<MasterportalLayer>,
    default: MasterportalLayer.STREETMAP,
  },
  /** the name is necessary for a named download. Without a given name, the download option will default to the name "Belegenheit" */
  name: { type: String, default: undefined },
  portalConfig: {
    type: Object as PropType<MasterportalConfig>,
    default: () => {},
  },
  layerConfig: {
    type: Object as PropType<LayerConfig>,
    default: () => {},
  },
});
```
> ℹ️ **Info:** Not that in a pure HTML (non-Vue) environment you need to change to *kebab-style* and pass non-primitive values using JS as outlined above.

#### Output emits / events
```ts
  const emits = defineEmits<{
    (e: "diplan-karte:background-layer-update", value: MapLayer): void;
    (e: "diplan-karte:data-layer-update", value: MapLayer[]): void;
    (e: "diplan-karte:fullscreen-update", value: boolean): void;
    (e: "diplan-karte:geojson-update", value: GeoJSONMultiPolygon[]): void;
  }>();
```

> ℹ️ **Info:** Not that in a pure HTML (non-Vue) environment you need to listen to these `emits` by using `addEventListener` using JS as outlined above.

### Getting `portalConfig` and `layerConfig` from Backend API

The WC can be passed different map configurations which are provided by a dedicated [Backend service](https://geodienste.develop.diplanung.de/api/karte-backend). Currently, this map configuration needs to be still fetched by the parent app (we are working on moving this code into the WC in the future):

```ts
// Some Vue component like App.vue
<script setup lang="ts">
import "@init/diplan-karten";
</script>

<template>
  <!-- Add some height and width to the surrounding container -->
  <div style="height: 80vh; width: 80vw">
    <diplan-karte
      :baseLayer="0"
      xplanWms="https://init.xplan.develop.diplanung.de/xplan-wms/services/planwerkwms/planname/0711dev?request=GetMap&service=WMS&version=1.3.0&format=image/png&transparent=true&exceptions=application/vnd.ogc.se_inimage&crs=epsg:25832&layers=BP_Planvektor,SO_Planvektor,BP_Planraster,SO_Planraster&bbox=643697.61,5358724.99,644042.674,5359014.65&width=346&height=290"
      @diplan-karte:geojson-update="(payload) => console.log('GeoJSON changed. Payload is: ', payload)"
    />
  </div>
</template>
```

```ts
// Some Vue component like App.vue
<script setup lang="ts">
  import { ref, watch } from "vue";
  import "@init/diplan-karten";

  const api = "https://geodienste.develop.diplanung.de/api/karte-backend";
  const auth ="AuthenticationJWT";
  const portalConfig = ref();
  const layerConfig = ref();
  const rotationKey = ref(Math.random());

  function getConfig(authToken: string) {
    if (!authToken) {
      throw new Error("There was no api set!");
    }
    return {
      headers: {
        Authorization: `Bearer ${authToken}`,
      },
    };
  }


  function getLayerConfig(authToken: string): Promise<LayerConfig> {
    const config = getConfig(authToken);
    return axios
      .get(this.api + "config/layer", config)
      .then((res) => res.data)
      .catch((error) => {
        throw new Error("Was not able to get Layer config", error);
      });
  }

  function getPortalConfig(authToken: string): Promise<MasterportalConfig> {
    const config = getConfig(authToken);
    return axios
      .get(this.api + "config/portal", config)
      .then((res) => res.data)
      .catch((error) => {
      throw new Error("Was not able to get Portal config", error)
      });
  }

  if (!portalConfig.value) {
    getPortalConfig(auth)
    .then((data) => (portalConfig.value = data));
  }
  if (!layerConfig.value) {
    getLayerConfig(auth)
    .then((data) => (layerConfig.value = data));
  }

  watch([portalConfig, layerConfig], () => {
    if (portalConfig.value && layerConfig.value) {
      rotationKey.value = Math.random();
    }
  });
</script>

<template>
  <!-- Add some height and width to the surrounding container -->
  <div style="height: 80vh; width: 80vw">
    <diplan-karte
      :key="rotationKey"
      :portalConfig
      :layerConfig
      :baseLayer="0"
      xplanWms="https://init.xplan.develop.diplanung.de/xplan-wms/services/planwerkwms/planname/0711dev?request=GetMap&service=WMS&version=1.3.0&format=image/png&transparent=true&exceptions=application/vnd.ogc.se_inimage&crs=epsg:25832&layers=BP_Planvektor,SO_Planvektor,BP_Planraster,SO_Planraster&bbox=643697.61,5358724.99,644042.674,5359014.65&width=346&height=290"
      @diplan-karte:geojson-update="(payload) => console.log('GeoJSON changed. Payload is: ', payload)"
    />
  </div>
</template>
```

In the example above, depending on the user authentication (variable `auth`, in which a JSON web token is stored), a corresponding configuration is loaded from the Diplan map backend and then made available to the application. Of course, the surrounding parent app component can provide a static configuration or load it from another location. In these cases, contact should be made with the maintainers of this project to find out the necessary structures.

## Development

### Project Setup

If not done yet, follow the documentation on [Setup und Konfiguration NPM für ADO](https://www.dev.diplanung.de/DefaultCollection/E2E-Plattform/_wiki/wikis/E2E-Plattform.wiki/3962/Setup-und-Konfiguration-NPM-f%C3%BCr-ADO).

Then you can:

```sh
npm install
```

### Type Support for `.vue` Imports in TS

TypeScript cannot handle type information for `.vue` imports by default, so we replace the `tsc` CLI with `vue-tsc` for type checking. In editors, we need [Volar](https://marketplace.visualstudio.com/items?itemName=Vue.volar) to make the TypeScript language service aware of `.vue` types.

### Customize configuration

See [Vite Configuration Reference](https://vite.dev/config/).
