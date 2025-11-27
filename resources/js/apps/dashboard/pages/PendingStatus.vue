<template>
<div>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-0">
                <div class="card-header ">
                    <h5 class="card-title pull-left">Call Stats</h5>
                    <span class="pull-right">Last Updated: {{ updatedTime | moment("MM/DD/YYYY, h:mm:ss a") }}</span>
                </div>
                <div class="card-body p-0 statuscodes">
                    <div class="row no-gutters">
                        <div class="col-md-6">
                            <tpv-callstats title="English"></tpv-callstats>
                        </div>
                        <div class="col-md-6">
                            <tpv-callstats title="Spanish" spanish></tpv-callstats>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-0">
                <div class="card-header">
                    <h5 class="card-title">Status Codes</h5>
                </div>
                <div class="card-body statuscodes">
                    <tpv-statuscodes></tpv-statuscodes>
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
    name: 'PendingStatus',
    components: {
        'tpv-callstats': CallStats,
        'tpv-statuscodes': StatusCodes
    },
    computed: {
        updatedTime() {
            return this.$store.state.AsOf;
        }
    }
}
</script>

<style scoped>
    h5 {
        margin-bottom: 0;
        font-size: 1rem;
        line-height: 1rem;
    }
    .statuscodes {
        zoom: 80%;
    }
    .card-header {
        padding-top: .5rem;
        padding-bottom: .25rem;
    }
</style>
