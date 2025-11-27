<template>
  <div class="container-fluid">
    <h2 class="mt-4 mb-4">
      Dashboard
    </h2>
    <tab-bar />
    <router-view />
  </div>
</template>

<script>
import TabBar from '../components/TabBar.vue';

export default {
    name: 'Main',
    components: {
        TabBar,
    },
    data() {
        return {
            updateDataTimer: null,
        };
    },
    created() {
        this.recheck();
    },
    destroyed() {
        clearTimeout(this.updateDataTimer);
    },
    methods: {
        recheck() {
            this.$store.dispatch('gatherStats').then(() => {
                this.updateDataTimer = setTimeout(this.recheck, 2500);
            })
                .catch(() => {
                    this.$log('==== ERROR ====', arguments);
                    this.updateDataTimer = setTimeout(this.recheck, 2500);
                });
        },
    },
};
</script>
