<template>
    <div>
        <div class="card">
            <div class="card-body text-center">
                <button type="button" class="btn btn-primary btn-lg" @click="showWarning">Launch Monitor</button>
            </div>
        </div>


        <div class="modal" id="tv-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" style="height:720px;width:1280px"  role="document">
                <div class="modal-content" style="height:720px;width:1280px">
                    <div class="modal-header bg-dark text-light">
                        <h5 class="modal-title">TPV.com Monitor</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body bg-dark p-0">
                        <div class="row mb-1">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header ">
                                        <h5 class="card-title pull-left">Call Stats</h5>
                                        <span class="pull-right">Last Updated: {{ updatedTime | moment("MM/DD/YYYY, h:mm:ss a") }}</span>
                                    </div>
                                    <div class="card-body p-0">
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
                        <div class="row mb-1">
                            <div class="col-md-12">
                                <div class="card mb-0">
                                    <div class="card-header">
                                        <h5 class="card-title">Status Codes</h5>
                                    </div>
                                    <div class="card-body">
                                        <tpv-statuscodes></tpv-statuscodes>
                                        <div class="row pt-3">
                                            <div class="col-6">
                                                <div class="row no-gutters">
                                                    <div class="col-4">
                                                        <div class="c-9">
                                                            <span class="number">000</span>
                                                            <span class="c9-title">Calls Last Interval</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="c-9">
                                                            <span class="number">000</span>
                                                            <span class="c9-title">Calls This Interval</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="c-9">
                                                            <span class="number">000</span>
                                                            <span class="c9-title">Calls Next Interval</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="row no-gutters">
                                                    <div class="col-6 text-center">
                                                        <div class="c-9">
                                                            <span class="number">000</span>
                                                            <span class="c9-title">Pending Breaks</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 text-center">
                                                        <div class="c-9">
                                                            <span class="number">000</span>
                                                            <span class="c9-title">Pending Meals</span>
                                                        </div>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
    name: 'Television',
    components: {
        'tpv-callstats': CallStats,
        'tpv-statuscodes': StatusCodes
    },
    computed: {
        updatedTime() {
            return this.$store.state.AsOf;
        }
    },
    mounted() {
        $('#tv-modal').on('hide.bs.modal', function() {
            axios.post('/logout');
            window.location = '/logout';
        });
    },
    methods: {
        showWarning() {
            if(confirm('Launching the TV Popout will log you out, proceed?')) {
                $('#tv-modal').modal('toggle');
            }
        }
    }
}
</script>

<style scoped>
    .modal-dialog {
        max-width: 100%;
    }
    h5 {
        margin-bottom: 0;
    }
    .c-9 {
        width: 119px;
        display: inline-block;
        height: 120px;
        border: 1px solid #ccc;
        position: relative;
        text-align: center;
    }
    .c9-title {
        position: absolute;
        bottom: 0;
        left: 0;
        font-size: 20px;
        line-height: 22px;
        text-align: center;
        width: 100%;
        font-weight: bolder;
        font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .number {
        font-size: 56px;
        font-family:'Courier New', Courier, monospace;
    }
    .modal {
        background-color: rgba(0,0,0,0.95);
    }
</style>
