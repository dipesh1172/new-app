<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Contracts', url: '/contracts'},
        {name: edit ? 'Edit' : 'Add', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div
          v-if="hasFlashMessage"
          class="alert alert-success"
        >
          <span class="fa fa-check-circle" />
          <em>{{ flashMessage }}</em>
        </div>

        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" />
            <span v-if="edit">
              Edit
            </span>
            <span v-else>
              Add
            </span> Contract
          </div>
          <div class="card-body">
            <form
              method="POST"
              :action="contractUrl"
              accept-charset="UTF-8"
              autocomplete="off"
              enctype="multipart/form-data"
            >
              <input
                type="hidden"
                name="_token"
                :value="csrf_token"
              >
              <!-- <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contract_name">Contract Name</label>
                                        <input v-model="contract.contract_name" class="form-control form-control-lg" placeholder="Enter a name for this Contract" name="contract_name" type="text" id="contract_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">

                                </div>
                            </div> -->

              <span v-if="!edit">
                <br>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="brand">Brand</label>
                      <select
                        id="brand"
                        v-model="contract.brand_id"
                        name="brand"
                        class="form-control form-control-lg"
                        required
                        @change="lookupTCS"
                      >
                        <option value="">Select a Brand</option>
                        <option
                          v-for="brand in brands"
                          :key="brand.id"
                          :value="brand.id"
                        >
                          {{ brand.name }}
                        </option>
                      </select>
                    </div>
                  </div>
                </div>
              </span>
              <span v-else>
                <br>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="brand">Brand</label><br>
                      <strong>{{ contract.brand_name }}</strong>
                    </div>
                  </div>
                </div>
              </span>

              <br>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="commodity">Commodity</label>
                    <select
                      id="commodity"
                      v-model="contract.commodities"
                      name="commodity"
                      class="form-control form-control-lg"
                    >
                      <option value="any">
                        Any
                      </option>
                      <option value="electric">
                        Electric
                      </option>
                      <option value="gas">
                        Gas
                      </option>
                      <!-- <option value="dual">Dual</option> -->
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="state">State</label>
                    <select
                      id="state"
                      v-model="contract.state_id"
                      name="state"
                      class="form-control form-control-lg"
                      required
                    >
                      <option value="">
                        Select a State
                      </option>
                      <option
                        v-for="state in states"
                        :key="state.id"
                        :value="state.id"
                      >
                        {{ state.name }}
                      </option>
                    </select>
                  </div>
                </div>
              </div>

              <br>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="rate_type">Rate Type</label>
                    <select
                      id="rate_type"
                      v-model="contract.rate_type"
                      name="rate_type"
                      class="form-control form-control-lg"
                    >
                      <option value="">
                        Any Rate Type
                      </option>
                      <option value="1">
                        Fixed
                      </option>
                      <option value="2">
                        Variable
                      </option>
                      <option value="4">
                        Tiered Fixed
                      </option>
                      <option value="5">
                        Tiered Variable
                      </option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="terms_conditions">Terms & Conditions</label><br>
                    <span v-if="edit">
                      English
                      <select
                        id="terms_and_conditions"
                        v-model="contract.terms_and_conditions"
                        name="terms_and_conditions"
                        class="form-control form-control-lg"
                      >
                        <option value="" />
                        <option
                          v-for="tc in tcs"
                          :key="tc.terms_and_conditions"
                          :value="tc.terms_and_conditions"
                        >
                          {{ tc.terms_and_conditions_name }}
                        </option>
                      </select>

                      <br>

                      Spanish
                      <select
                        id="terms_and_conditions_spanish"
                        v-model="contract.terms_and_conditions_spanish"
                        name="terms_and_conditions_spanish"
                        class="form-control form-control-lg"
                      >
                        <option value="" />
                        <option
                          v-for="tc in tcs"
                          :key="tc.terms_and_conditions"
                          :value="tc.terms_and_conditions"
                        >
                          {{ tc.terms_and_conditions_name }}
                        </option>
                      </select>
                    </span>
                    <span v-else>
                      <span v-if="loadTCS && loadTCS.length > 0">
                        <select
                          id="terms_and_conditions"
                          name="terms_and_conditions"
                          class="form-control form-control-lg"
                        >
                          <option value="" />
                          <option
                            v-for="tc in loadTCS"
                            :key="tc.terms_and_conditions"
                            :value="tc.terms_and_conditions"
                          >
                            {{ tc.terms_and_conditions_name }}
                          </option>
                        </select>

                        <br>

                        Spanish
                        <select
                          id="terms_and_conditions_spanish"
                          name="terms_and_conditions_spanish"
                          class="form-control form-control-lg"
                        >
                          <option value="" />
                          <option
                            v-for="tc in loadTCS"
                            :key="tc.terms_and_conditions"
                            :value="tc.terms_and_conditions"
                          >
                            {{ tc.terms_and_conditions_name }}
                          </option>
                        </select>
                      </span>
                      <span v-else>
                        <i>Select a brand.</i>
                      </span>
                    </span>
                  </div>
                </div>
              </div>

              <br>

              <div class="row">
                <div class="col-md-6">
                  <label for="channel">Channel(s)</label>
                  <div class="pl-5">
                    <div class="form-check">
                      <input
                        :checked="contract && contract.channel && contract.channel.includes('DTD')"
                        class="form-check-input"
                        name="channel[]"
                        type="checkbox"
                        value="DTD"
                      >
                      <label
                        class="form-check-label"
                        for="dtd"
                      >
                        DTD
                      </label>
                    </div>
                    <div class="form-check">
                      <input
                        :checked="contract && contract.channel && contract.channel.includes('RETAIL')"
                        class="form-check-input"
                        name="channel[]"
                        type="checkbox"
                        value="RETAIL"
                      >
                      <label
                        class="form-check-label"
                        for="retail"
                      >
                        Retail
                      </label>
                    </div>
                    <div class="form-check">
                      <input
                        :checked="contract && contract.channel && contract.channel.includes('TM')"
                        class="form-check-input"
                        name="channel[]"
                        type="checkbox"
                        value="TM"
                      >
                      <label
                        class="form-check-label"
                        for="tm"
                      >
                        TM
                      </label>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <label for="market">Market(s)</label>
                  <div class="pl-5">
                    <div class="form-check">
                      <input
                        :checked="contract && contract.market && contract.market.includes('RESIDENTIAL')"
                        class="form-check-input"
                        name="market[]"
                        type="checkbox"
                        value="RESIDENTIAL"
                      >
                      <label
                        class="form-check-label"
                        for="res"
                      >
                        Residential
                      </label>
                    </div>
                    <div class="form-check">
                      <input
                        :checked="contract && contract.market && contract.market.includes('COMMERCIAL')"
                        class="form-check-input"
                        name="market[]"
                        type="checkbox"
                        value="COMMERCIAL"
                      >
                      <label
                        class="form-check-label"
                        for="com"
                      >
                        Commercial
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- <br />

                            <div class="row">
                                <div class="col-md-6">
                                    <label for="customer_signature">Should the Customer Signature be required?</label>
                                    <div class="pl-5">
                                        <div class="form-check">
                                            <input v-model="contract.customer_signature" class="form-check-input" name="customer_signature" type="checkbox" value="1">
                                            <label class="form-check-label" for="customer_signature">
                                                Yes
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="sales_agent_signature">Should the Sales Agent Signature be required?</label>
                                    <div class="pl-5">
                                        <div class="form-check">
                                            <input v-model="contract.sales_agent_signature" class="form-check-input" name="sales_agent_signature" type="checkbox" value="1">
                                            <label class="form-check-label" for="sales_agent_signature">
                                                Yes
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div> -->

              <!-- <br />

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email_verbiage_english">English Email Verbiage (optional)</label>
                                        <textarea v-model="contract.email_verbiage.english" class="form-control form-control-lg" name="email_verbiage_english" style="min-height: 150px;" placeholder="English Email Verbiage"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email_verbiage_spanish">Spanish Email Verbiage (optional)</label>
                                        <textarea v-model="contract.email_verbiage.spanish" class="form-control form-control-lg" name="email_verbiage_spanish" style="min-height: 150px;" placeholder="Spanish Email Verbiage"></textarea>
                                    </div>
                                </div>
                            </div> -->

              <br><br>

              <button
                type="submit"
                class="btn btn-primary btn-lg"
              >
                <i
                  class="fa fa-floppy-o"
                  aria-hidden="true"
                /> 
                Submit
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'ContractAddEdit',
    components: {
        Breadcrumb,
    },
    props: {
        hasFlashMessage: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
        brands: {
            type: Array,
        },
        states: {
            type: Array,
        },
        tcs: {
            type: Array,
        },
        contract: {
            type: Object,
            default: () => (
                {
                    contract_name: null,
                    language_id: '',
                    brand_id: '',
                    state_id: '',
                    commodity: '',
                    rate_type: null,
                    market: null,
                    channel: null,
                    sales_agent_signature: null,
                    customer_signature: null,
                    email_verbiage: {
                        english: null,
                        spanish: null,
                    },
                }
            ),
        },
        edit: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            contractUrl: '/contracts/store',
            loadTCS: null,
        };
    },
    computed: {
        csrf_token() {
            return csrf_token;
        },
    },
    mounted() {
        console.log('contract is ', this.contract);

        if (this.edit) {
            this.contractUrl = `/contracts/${this.contract.id}/update`;
        }
    },
    methods: {
        lookupTCS() {
            axios.get(`/contracts/tclist/${this.contract.brand_id}`)
                .then((response) => {
                    // console.log(response.data);
                    this.loadTCS = response.data;
                })
                .catch((error) => {
                    console.log(error);
                });
        },
    },
};
</script>

<style lang="scss" scoped>

</style>
