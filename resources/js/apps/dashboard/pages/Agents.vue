<template>
    <div class="tab-content">
        <br />
        
        <div class="row">
            <div class="col-md-12">
                <div class="container-fluid">
                    <div class="animated fadeIn">
                        <tpv-agentstate />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import AgentState from '../components/AgentState.vue';

export default {
    name: 'Agents',
    components: {
        'tpv-agentstate': AgentState
    }
}
</script>

<style scoped>
h5 {
    margin-bottom: 0;
}
</style>
