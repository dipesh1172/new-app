<template>
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
          <a href="/">Home</a>
        </li>
        <li class="breadcrumb-item">
          <a href="/tpv_staff">TPV Staff</a>
        </li>
        <li :class="{'breadcrumb-item': true, active: route_name == 'Home'}">
          Agent Groups
        </li>
        <li
          v-if="route_name != 'Home'"
          class="breadcrumb-item active"
          aria-current="page"
        >
          {{ route_name }}
        </li>
      </ol>
    </nav>
    <router-view />
  </div>
</template>
<script>
export default {
    name: 'MainApp',
    computed: {
        route_name() {
            return this.$router.currentRoute.name;
        },
    },
    created() {
        this.$store.dispatch('loadProfiles');
        this.$store.dispatch('loadSkills');
    },

};
</script>
