<template>
  <!--The attribute data-responsive-menu-helper is used as a hook to append an EventListener in GlobalEventlisteners.js-->
  <button
    type="button"
    class="btn btn--blank font-size-medium"
    aria-haspopup="true"
    aria-expanded="false"
    data-responsive-menu-helper>
    <a class="nav-toggle">
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="bar"></span>
    </a>
  </button>
</template>

<script>

export default {
  name: 'NavButton'
}

</script>
