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
      > <i 
          v-if="item.icon"
          :class="`fa ${item.icon}`"
          aria-hidden="true"
        /> 
        {{ item.label }}
      </a>
    </li>
  </ul>
</template>
<script>
import { requestIs } from 'utils/urlHelpers';

export default {
    name: 'NavAgents',
    computed: {
        items() {
            return [
                {
                    active: requestIs('/agents'),
                    href: '/agents',
                    label: 'Agents',
                    icon: 'fa-user',
                },
                {
                    active: requestIs('/agent/groups'),
                    href: '/agent/groups',
                    label: 'Agent Groups',
                    icon: 'fa-users',
                },
            ];
        },
    },
};
</script>