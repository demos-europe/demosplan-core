<template>
  <!--The attribute data-responsive-menu-helper is used as a hook to append an EventListener in GlobalEventlisteners.js-->
  <button
    type="button"
    class="btn btn--blank font-size-medium"
    aria-haspopup="true"
    aria-expanded="false"
    :aria-label="Translator.trans('menu.nav.toggle')"
    data-responsive-menu-helper>
    <span class="nav-toggle">
      <span class="bar" />
      <span class="bar" />
      <span class="bar" />
    </span>
  </button>
</template>

<script>

export default {
  name: 'HamburgerMenuButton'
}

</script>
