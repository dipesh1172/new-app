<template>
  <div>
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        Home
      </li>
      <li class="breadcrumb-item active">
        <a href="/contracts">Contracts</a>
      </li>
      <li class="breadcrumb-item active">
        Show :: {{ contractConfig.contract_name }}
      </li>
    </ol>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <div class="row">
              <div class="col-md-9">
                <i class="fa fa-th-large" /> {{ contractConfig.contract_name }}
              </div>
              <div class="col-md-3 text-right" />
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-8">
                <h2>Contract Versions</h2>

                <hr>

                <div class="row p-3">
                  <div
                    v-for="version in contractConfigVersion"
                    :key="version.id"
                    class="col-md-3 text-center border m-2"
                  >
                    <h2 class="p-3">
                      v{{ version.version }}
                    </h2>

                    <hr>

                    <div class="row">
                      <div class="col-md-12">
                        <a
                          class="btn btn-sm btn-info"
                          :href="`/contracts/` + contractConfig.id + `/page/` + version.id + `/edit`"
                        >
                          Edit
                        </a>

                        <span v-if="version.active">
                          <span
                            class="btn btn-sm btn-danger"
                          >
                            Active
                          </span>
                        </span>
                        <span v-else>
                          <a
                            onClick="return confirm('are you sure you want to make this active?');"
                            :href="`/contracts/` + contractConfig.id + `/page/` + version.id + `/activate`"
                            class="btn btn-sm btn-success"
                          >
                            Make Active
                          </a>
                        </span>
                      </div>
                    </div>

                    <br>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card">
                  <div class="card-header">
                    <div class="row">
                      <div class="col-md-9">
                        Information
                      </div>
                      <div class="col-md-3 text-right">
                        <a
                          :href="`/contracts/` + contractConfig.id + `/editContract`"
                          class="btn btn-sm btn-primary"
                        >
                          Edit Contract
                        </a>
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                    <b>Contract Name</b>: {{ contractConfig.contract_name }}<br>
                    <b>Brand:</b> {{ contractConfig.name }}<br>
                    <b>State:</b> {{ contractConfig.state_abbrev }}<br>
                    <b>Channel:</b> {{ contractConfig.channel }}<br>
                    <b>Market:</b> {{ contractConfig.market }}<br>
                    <b>Language:</b> {{ contractConfig.language }}<br>
                    <b>Commodities:</b> {{ contractConfig.commodities }}<br>
                    <b>Customer Signature Required:</b>
                    <span v-if="contractConfig.customer_signature === 1">
                      Yes
                    </span>
                    <span v-else>
                      No
                    </span>
                    <br>
                    <b>Sales Agent Signature Required:</b>
                    <span v-if="contractConfig.sales_agent_signature === 1">
                      Yes
                    </span>
                    <span v-else>
                      No
                    </span>
                    <br>
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
export default {
    name: 'ContractEditor',
    components: {

    },
    props: {
        contractConfig: {
            type: Object,
        },
        contractConfigVersion: {
            type: Array,
        },
    },
    data() {
        return {

        };
    },
    computed: {

    },
    mounted() {

    },
    methods: {

    },
};
</script>

<style lang="scss" scoped>

</style>
