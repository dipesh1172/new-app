<template>
  <ul class="nav nav-tabs justify-content-end">
    <li
      v-for="(link, index) in links"
      :key="index"
      class="nav-item"
      :class="{active: activeItem === index}"
    >
      <a
        class="nav-link"
        :href="link.href"
      >{{ link.text }}</a>
    </li>
  </ul>
</template>
<script>
export default {
    name: 'TabBar',
    props: {
        activeItem: {
            type: Number,
            default: 0,
        },
    },
    data() {
        return {
            links: [
                { href: '/dashboard', text: 'Call Center' },
                { href: '/live-agent', text: 'Live Agent' },
                { href: '/queues', text: 'Queues' },
                { href: '/alerts', text: 'Alerts' },
                { href: '/surveys', text: 'Surveys'},
            ],
        };
    },

};
</script>
<style scoped>
.active {
  background-color: rgba(255, 255, 255, 1.0);
}
</style>
