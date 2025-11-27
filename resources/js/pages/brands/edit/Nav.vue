<template>
  <div>
    <ul class="nav nav-tabs">
      <li
        v-for="(item, i) in items"
        :key="i"
        class="nav-item"
      >
        <a
          :href="item.href"
          :class="['nav-link', { active: item.isActive }]"
        >
          {{ item.label }}
        </a>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
    name: 'BrandNav',
    props: {
        items: {
            type: Array,
            default: () => [],
        },
    },
};
</script>
<style scoped>
.nav-tabs{
  background-color: white !important;
}
</style>
