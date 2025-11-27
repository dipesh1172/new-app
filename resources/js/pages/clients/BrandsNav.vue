<template>
  <ul class="nav nav-tabs">
    <li
      v-for="(item, i) in items"
      :key="i"
      class="nav-item"
    >
      <a
        :href="item.href"
        :class="['nav-link', { active: item.active }]"
      >
        {{ item.label }}
      </a>
    </li>
  </ul>
</template>
<script>
import { requestIs } from 'utils/urlHelpers';

export default {
    computed: {
        items() {
            return [
                {
                    active: requestIs('/brands'),
                    href: '/brands',
                    label: 'Brands',
                },
                {
                    active: requestIs('/clients'),
                    href: '/clients',
                    label: 'Clients',
                },
                {
                    active: requestIs('/vendors'),
                    href: '/vendors',
                    label: 'Vendors',
                },
            ];
        },
    },
};
</script>
