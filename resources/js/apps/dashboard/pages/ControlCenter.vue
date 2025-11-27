<template>
  <div class="tab-content">
    <div class="row">
      <div class="col-md-12">
        <div class="container-fluid">
          <div class="animated fadeIn">
            <div class="card mt-3 mb-3">
              <div class="card-header">
                <h5 class="card-title pull-left">
                  Call Stats
                </h5>
                <span class="pull-right">
                  Last Updated:
                  <span v-if="updatedTime">{{ updatedTime | moment("MM/DD/YYYY, h:mm:ss a") }}</span>
                  <span v-else><i class="fa fa-spinner fa-spin" /> Loading ...</span>
                </span>
              </div>
              <div class="card-body">
                <div class="row no-gutters mb-4">
                  <div class="col-md-12">
                    <tpv-callstats title="English" />
                  </div>
                </div>
                <div class="row no-gutters">
                  <div class="col-md-12">
                    <tpv-callstats
                      title="Spanish"
                      spanish
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <div class="container-fluid">
          <div class="animated fadeIn">
            <div class="card mb-3">
              <div class="card-header">
                <h5 class="card-title">
                  Status Codes
                </h5>
              </div>
              <div class="card-body">
                <tpv-statuscodes />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import CallStats from '../components/CallStats.vue';
import StatusCodes from '../components/StatusCodes.vue';

export default {
    name: 'ControlCenter',
    components: {
        'tpv-callstats': CallStats,
        'tpv-statuscodes': StatusCodes,
    },
    computed: {
        updatedTime() {
            return this.$store.state.AsOf;
        },
    },
};
</script>

<style scoped>
h5 {
    margin-bottom: 0;
}
</style>
