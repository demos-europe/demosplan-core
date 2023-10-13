<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <section v-if="title">
      <h4 class="font-semibold">
        {{ title }}
      </h4>
    </section>
    <section v-else>
        <svg
            class="inline-block"
            height="16px"
            width="16px"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 28.314 28.323"
            style="enable-background:new 0 0 28.314 28.323"
            xml:space="preserve">
        <path d="m27.728 20.384-4.242-4.242a1.982 1.982 0 0 0-1.413-.586h-.002c-.534 0-1.036.209-1.413.586L17.83 18.97l-8.485-8.485 2.828-2.828c.78-.78.78-2.05-.001-2.83L7.929.585A1.986 1.986 0 0 0 6.516 0h-.001C5.98 0 5.478.209 5.101.587L.858 4.83C.729 4.958-.389 6.168.142 8.827c.626 3.129 3.246 7.019 7.787 11.56 6.499 6.499 10.598 7.937 12.953 7.937 1.63 0 2.426-.689 2.604-.867l4.242-4.242c.378-.378.587-.881.586-1.416 0-.534-.208-1.037-.586-1.415zm-5.656 5.658c-.028.028-3.409 2.249-12.729-7.07C-.178 9.452 2.276 6.243 2.272 6.244L6.515 2l4.243 4.244-3.535 3.535a.999.999 0 0 0 0 1.414l9.899 9.899a.999.999 0 0 0 1.414 0l3.535-3.536 4.243 4.244-4.242 4.242z" />
      </svg>
        <p class="mt-8 mb-0 inline-block font-semibold">
          (+49) 40 428 46 2694
        </p>
    </section>
    <section v-if="phoneNumber">
      <a
        :href="`tel:${phoneNumber}`"
        class="mt-3 mb-0 inline-block font-semibold">
        <svg
            class="inline-block"
            height="16px"
            width="16px"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 28.314 28.323"
            style="enable-background:new 0 0 28.314 28.323"
            xml:space="preserve">
        <path d="m27.728 20.384-4.242-4.242a1.982 1.982 0 0 0-1.413-.586h-.002c-.534 0-1.036.209-1.413.586L17.83 18.97l-8.485-8.485 2.828-2.828c.78-.78.78-2.05-.001-2.83L7.929.585A1.986 1.986 0 0 0 6.516 0h-.001C5.98 0 5.478.209 5.101.587L.858 4.83C.729 4.958-.389 6.168.142 8.827c.626 3.129 3.246 7.019 7.787 11.56 6.499 6.499 10.598 7.937 12.953 7.937 1.63 0 2.426-.689 2.604-.867l4.242-4.242c.378-.378.587-.881.586-1.416 0-.534-.208-1.037-.586-1.415zm-5.656 5.658c-.028.028-3.409 2.249-12.729-7.07C-.178 9.452 2.276 6.243 2.272 6.244L6.515 2l4.243 4.244-3.535 3.535a.999.999 0 0 0 0 1.414l9.899 9.899a.999.999 0 0 0 1.414 0l3.535-3.536 4.243 4.244-4.242 4.242z" />
      </svg>
        {{ phoneNumber }}
      </a>
    </section>

    <section v-if="email">
      <p class="mb-0 font-normal">
        {{ email }}
      </p>
    </section>

    <section v-if="reachability">
      <p class="mt-4 lg:mt-2 font-normal">
        {{ (reachability).replace(/<\/?p[^>]*>/g, "") }}
      </p>
    </section>
    <section v-else>
      <h4 class="mb-0 mt-3 font-semibold">
        {{ Translator.trans('support.contact.service') }}
      </h4>
      <p class="font-normal">
        Montag bis Freitag: 6.30 - 18 Uhr<br>
        Freitag: 6.30 - 17Uhr
      </p>
      <span>
        {{ Translator.trans('support.contact.exception') }}
      </span>
    </section>
  </div>
</template>
<script>

export default {
  name: 'DpFaqSupportCard',

  props: {
    title: {
      required: false,
      type: String
    },

    phoneNumber: {
      required: false,
      type: String,
      default: ''
    },

    email: {
      required: false,
      type: String,
      default: ''
    },

    reachability: {
      required: false,
      type: String,
      default: ''
    }
  }
}
</script>
