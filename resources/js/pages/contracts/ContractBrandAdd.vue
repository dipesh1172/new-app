<template>
  <layout
    :brand="brand"
    :force-selection="'1.Contracts'"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: `${brand.name} Contracts`, url: `/brands/${brand.id}/get_contracts`},
      {name: 'Add Contract', active: true}
    ]"
  >
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <em class="fa fa-align-justify" /> Add Contract
          </div>
          <div class="card-body">
            <div
              v-if="errorOnSubmit.msg.length"
              class="alert alert-danger"
            >
              <ul>
                <li
                  v-for="(msg, index) in errorOnSubmit.msg"
                  :key="index"
                >
                  {{ msg }}
                </li>
              </ul>
            </div>

            <div
              v-if="errors.length"
              class="alert alert-danger"
            >
              <ul>
                <li
                  v-for="(error, index) in errors"
                  :key="index"
                >
                  {{ error }}
                </li>
              </ul>
            </div>

            <form
              method="POST"
              action="/brand_contracts/store"
              class="mt-2"
              enctype="multipart/form-data"
            >
              <input
                type="hidden"
                name="_token"
                :value="csrf_token"
              >
              <input
                type="hidden"
                name="brand_id"
                :value="brand.id"
              >
              <div class="row p-3">
                <div class="col-2">
                  <h5>States</h5>
                  <div
                    v-for="state in states"
                    :key="state.id"
                    class="form-check"
                  >
                    <input
                      v-model="selectedStates"
                      class="form-check-input"
                      type="checkbox"
                      name="state_id[]"
                      :value="state.id"
                      @change="filterUtilities()"
                    >
                    <label
                      class="form-check-label"
                      for="defaultCheck1"
                    >
                      {{ state.name }}
                    </label>
                  </div>
                </div>
                <div class="col-2">
                  <h5>Languages</h5>
                  <div
                    v-for="language in languages"
                    :key="language.id"
                    class="form-check"
                  >
                    <input
                      class="form-check-input"
                      type="checkbox"
                      name="language_id[]"
                      :value="language.id"
                    >
                    <label
                      class="form-check-label"
                      for="defaultCheck1"
                    >
                      {{ language.name }}
                    </label>
                  </div>
                </div>
                <div class="col-2">
                  <h5>Channels</h5>
                  <div
                    v-for="channel in channels"
                    :key="channel.id"
                    class="form-check"
                  >
                    <input
                      class="form-check-input"
                      type="checkbox"
                      name="channel_id[]"
                      :value="channel.id"
                    >
                    <label
                      class="form-check-label"
                    >
                      {{ channel.name }}
                    </label>
                  </div>
                </div>
                <div class="col-2">
                  <h5>Commodities</h5>
                  <div
                    v-for="commodity in commodities"
                    :key="commodity.id"
                    class="form-check"
                  >
                    <input
                      v-model="selectedCommodities"
                      class="form-check-input"
                      type="checkbox"
                      name="commodity[]"
                      :value="commodity.id"
                      @change="filterUtilities()"
                    >
                    <label
                      class="form-check-label"
                    >
                      {{ commodity.name }}
                    </label>
                  </div>
                </div>
                <div class="col-2">
                  <h5>Markets</h5>
                  <div
                    v-for="market in markets"
                    :key="market.id"
                    class="form-check"
                  >
                    <input
                      class="form-check-input"
                      type="checkbox"
                      name="market_id[]"
                      :value="market.id"
                    >
                    <label
                      class="form-check-label"
                    >
                      {{ market.name }}
                    </label>
                  </div>
                </div>
                <div class="col-2">
                  <h5>Rate Types</h5>
                  <div
                    v-for="rt in rateTypes"
                    :key="rt.id"
                    class="form-check"
                  >
                    <input
                      v-model="rateType"
                      class="form-check-input"
                      type="radio"
                      name="rate_type_id"
                      :value="rt.id"
                      @change="checkRateTypeSelected()"
                    >
                    <label
                      class="form-check-label"
                    >
                      {{ rt.name }}
                    </label>
                  </div>
                </div>
              </div>

              <div class="form-row">
                <div
                  v-if="tieredRateType"
                  class="col-md-3"
                >
                  <div class="form-group">
                    <label for="expanded_rate_type">Expanded Rate Type</label>
                    <select
                      id="expanded_rate_type"
                      v-model="expandedRateType"
                      name="expanded_rate_type"
                      class="form-control"
                    >
                      <option value="" />
                      <option value="fixed-tiered">
                        Fixed Tiered
                      </option>
                      <option value="tiered-variable">
                        Tiered Variable
                      </option>
                    </select>
                    <span class="font-italic font-weight-light">If the contract uses tiered rates that are Fixed-Tiered or Tiered-Variable, select the appropriate type above, otherwise leave this blank.</span>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="product_id">Product Type</label>
                    <select
                      id="product_type"
                      name="product_type"
                      class="form-control"
                    >
                      <option value="0">
                        Default
                      </option>
                      <option value="1">
                        Green
                      </option>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="product_id">Product (optional)</label>
                    <select
                      id="product_id"
                      name="product_id"
                      class="form-control"
                    >
                      <option value="" />
                      <option
                        v-for="product in products"
                        :key="product.id"
                        :value="product.id"
                      >
                        {{ product.name }}
                      </option>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="rate_id">Rate (optional)</label>
                    <select
                      id="rate_id"
                      name="rate_id"
                      class="form-control"
                    >
                      <option value="" />
                      <option
                        v-for="rate in rates"
                        :key="rate.id"
                        :value="rate.id"
                      >
                        {{ rate.program_code }}
                      </option>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="utility_id">Utility (optional)</label>
                    <select
                      id="utility_id"
                      v-model="selectedUtility"
                      name="utility_id"
                      class="form-control"
                    >
                      <option value="" />
                      <option
                        v-for="utility in utilitiesClone"
                        :key="utility.utilit_id"
                        :value="utility.utilit_id"
                      >
                        {{ utility.name }}
                      </option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="form-row">
                <div class="col-md-4 pt-3 pl-4">
                  <div class="form-check">
                    <input
                      id="signature_required_customer1"
                      class="form-check-input"
                      type="radio"
                      name="signature_required_customer"
                      value="1"
                      checked
                    >
                    <label
                      class="form-check-label"
                      for="signature_required_customer1"
                    >
                      Yes - Require a customer signature
                    </label>
                  </div>
                  <div class="form-check">
                    <input
                      id="signature_required_customer2"
                      class="form-check-input"
                      type="radio"
                      name="signature_required_customer"
                      value="0"
                    >
                    <label
                      class="form-check-label"
                      for="signature_required_customer2"
                    >
                      No - Do not require a customer signature
                    </label>
                  </div>
                </div>
                <div class="col-md-4 pt-3 pl-4">
                  <div class="form-check">
                    <input
                      id="signature_required_agent1"
                      class="form-check-input"
                      type="radio"
                      name="signature_required_agent"
                      value="1"
                      checked
                    >
                    <label
                      class="form-check-label"
                      for="signature_required_customer1"
                    >
                      Yes - Require a sales agent signature
                    </label>
                  </div>
                  <div class="form-check">
                    <input
                      id="signature_required_agent2"
                      class="form-check-input"
                      type="radio"
                      name="signature_required_agent"
                      value="0"
                    >
                    <label
                      class="form-check-label"
                      for="signature_required_agent2"
                    >
                      No - Do not require a sales agent signature
                    </label>
                  </div>
                </div>
              </div>

              <br><hr><br>

              <div class="form-row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="document_type_id">Contract Type</label>
                    <select
                      id="document_type_id"
                      name="document_type_id"
                      class="form-control"
                    >
                      <option
                        value="1"
                      >
                        Contract Only
                      </option>
                      <option
                        value="3"
                      >
                        Contract + Signature Page
                      </option>
                    </select>
                  </div>
                </div>
                <div class="col-md-4 pl-5">
                  <div class="form-group">
                    <label for="contract_doc">Upload Contract <span style="color:red">*</span></label><br>
                    <input
                      id="contract_doc"
                      type="file"
                      name="contract_doc"
                      accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    >
                  </div>
                </div>
              </div>

              <br><hr><br>

              <button
                class="btn btn-primary pull-right"
                type="submit"
                @click="submit($event)"
              >
                <em class="fa fa-floppy-o" />
                Save
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </layout>
</template>
<script>
import Layout from '../brands/edit/Layout';

export default {
    name: 'ContractBrandAdd',
    components: {
        Layout,
    },
    props: {
        brand: {
            type: Object,
            required: true,
            default: () => ({}),
        },
        channels: {
            type: Array,
            required: true,
            default: () => [],
        },
        states: {
            type: Array,
            required: true,
            default: () => [],
        },
        rateTypes: {
            type: Array,
            required: true,
            default: () => [],
        },
        languages: {
            type: Array,
            required: true,
            default: () => [],
        },
        products: {
            type: Array,
            required: true,
            default: () => [],
        },
        rates: {
            type: Array,
            required: true,
            default: () => [],
        },
        utilities: {
            type: Array,
            required: true,
            default: () => [],
        },
        errors: {
            type: Array,
            default: () => [],
        },
    },
    data() {

        return {
            commodities: [
                {
                    id: 'gas',
                    name: 'Gas',
                },
                {
                    id: 'electric',
                    name: 'Electric',
                },
                {
                    id: 'dual',
                    name: 'Dual',
                },
            ],
            markets: [
                {
                    id: 1,
                    name: 'Residential',
                },
                {
                    id: 2,
                    name: 'Commercial',
                },
            ],
            errorOnSubmit: {
                msg: [],
            },
            csrf_token: window.csrf_token,
            tieredRateType: false,
            rateType: null,
            expandedRateType: null,
            selectedUtility: null,
            selectedStates: [],
            selectedCommodities: [],
            utilitiesClone: [],
        };
    },
    methods: {
        submit(e) {
            if (!this.errorOnSubmit.fileErrorShown && document.getElementById('contract_doc').files.length === 0) {
                e.preventDefault();
                this.errorOnSubmit.msg.push('You need to select a file before submiting the form.');
                this.errorOnSubmit.fileErrorShown = true;
            }
            else if (!this.errorOnSubmit.expandedRateErrorShown && this.rate_type_id === 3 && this.expanded_rate_type === null) {
                this.errorOnSubmit.msg.push('Tiered rates require an Expanded Rate Type.');
                e.preventDefault();
                this.errorOnSubmit.expandedRateErrorShown = true;
            }
            else {
                this.errorOnSubmit.msg.length = 0;
                this.errorOnSubmit.fileErrorShown = false;
                this.errorOnSubmit.expandedRateErrorShown = false;
            }
        },
        checkRateTypeSelected() {
            if (this.rateType === 3) {
                this.tieredRateType = true;
            }
            else {
                this.tieredRateType = false;
                this.expandedRateType = null;
            }
        },
        filterUtilities() {
            let filteredUtilities = [];
            this.utilitiesClone = [];

            if (this.selectedCommodities.length > 0 && this.selectedStates.length === 0) {
                filteredUtilities = this.utilities.filter((el) => this.selectedCommodities.includes(el.commodity));
            }
            else if (this.selectedStates.length > 0 && this.selectedCommodities.length === 0) {
                filteredUtilities = this.utilities.filter((el) => this.selectedStates.includes(el.state_id));
            }
            else {
                filteredUtilities = this.utilities.filter((el) => this.selectedStates.includes(el.state_id)
                  && this.selectedCommodities.includes(el.commodity));
            }

            this.utilitiesClone = filteredUtilities;
        },
    },
};
</script>
