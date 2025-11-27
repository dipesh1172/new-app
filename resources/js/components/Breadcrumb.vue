<template>
  <ol
    class="breadcrumb"
    style="margin-bottom: 0;"
  >
    <li
      v-for="(item, index) in items"
      :key="index"
      :class="[{ active: item.active },'breadcrumb-item']"
    >
      <span
        v-if="item.active"
        v-html="item.name"
      />
      <a
        v-else
        :href="item.url"
        v-html="item.name"
      />
    </li>
  </ol>
</template>

<script>
export default {
    name: 'Breadcrumb',
    props: {
        items: {
            type: Array,
            default: () => [],
        },
    },
};
</script>
