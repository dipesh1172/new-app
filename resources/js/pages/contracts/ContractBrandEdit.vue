<template>
  <layout
    :brand="{id: contract.brand_id, name: contract.brand_name}"
    :force-selection="'1.Contracts'"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: `${contract.brand_name} Contracts`, url: `/brands/${contract.brand_id}/get_contracts`},
      {name: 'Edit Contract', active: true}
    ]"
  >
    <div class="container p-0">
      <div class="card mb-0">
        <div class="card-header">
          <em class="fa fa-align-justify" /> Edit Contract
        </div>
        <div class="card-body">
          <div
            v-if="errorMsg.active"
            class="alert alert-danger"
          >
            {{ errorMsg.text }}
            <table class="table table-borderless mt-2">
              <thead>
                <tr>
                  <th scope="col">
                    Fields
                  </th>
                  <th scope="col">
                    Current
                  </th>
                  <th scope="col">
                    Conflict
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(key, index) in Object.keys(errorMsg.conflict.obj1)"
                  :key="index"
                >
                  <th>
                    {{ key }}
                  </th>
                  <td>{{ errorMsg.conflict.obj1[key] }}</td>
                  <td>{{ errorMsg.conflict.obj2[key] }}</td>
                </tr>
              </tbody>
            </table>
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
            ref="contractForm"
            method="POST"
            :action="`/brand_contracts/${contract.id}/update`"
            class="mt-2"
            enctype="multipart/form-data"
          >
            <input
              type="hidden"
              name="_token"
              :value="csrf_token"
            >
            <div class="form-row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="state_id">States</label>
                  <select
                    id="state_id"
                    v-model="selectedState"
                    name="state_id"
                    type="text"
                    class="form-control"
                    @change="filterUtilities()"
                  >
                    <option
                      v-for="state in states"
                      :key="state.id"
                      :value="state.id"
                      :selected="contract.state_id === state.id"
                    >
                      {{ state.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="language_id">Languages</label>
                  <select
                    id="language_id"
                    name="language_id"
                    type="text"
                    class="form-control"
                  >
                    <option
                      v-for="language in languages"
                      :key="language.id"
                      :value="language.id"
                      :selected="contract.language_id === language.id"
                    >
                      {{ language.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="channel_id">Channels</label>
                  <select
                    id="channel_id"
                    name="channel_id"
                    type="text"
                    class="form-control"
                  >
                    <option
                      v-for="channel in channels"
                      :key="channel.id"
                      :value="channel.id"
                      :selected="contract.channel_id === channel.id"
                    >
                      {{ channel.name }}
                    </option>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-row">
              <div class="col-md-4">
                <div class="form-group">
                  <label for="commodity">Commodities</label>
                  <select
                    id="commodity"
                    v-model="selectedCommodity"
                    name="commodity"
                    class="form-control"
                    @change="filterUtilities()"
                  >
                    <option
                      v-for="commodity in commodities"
                      :key="commodity.id"
                      :value="commodity.id"
                      :selected="contract.commodity === commodity.id"
                    >
                      {{ commodity.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="market_id">Markets</label>
                  <select
                    id="market_id"
                    name="market_id"
                    class="form-control"
                  >
                    <option
                      v-for="market in markets"
                      :key="market.id"
                      :value="market.id"
                      :selected="contract.market_id === market.id"
                    >
                      {{ market.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label for="rate_type_id">Rate Types</label>
                  <select
                    id="rate_type_id"
                    v-model="contract.rate_type_id"
                    name="rate_type_id"
                    class="form-control"
                    @change="checkRateTypeSelected()"
                  >
                    <option value="" />
                    <option
                      v-for="rate in rateTypes"
                      :key="rate.id"
                      :value="rate.id"
                    >
                      {{ rate.name }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <div class="form-row">
              <div
                v-if="showExpandedRateTypeField"
                class="col-md-3"
              >
                <div class="form-group">
                  <label for="expanded_rate_type">Expanded Rate Type</label>
                  <select
                    id="expanded_rate_type"
                    v-model="contract.expanded_rate_type"
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
                  <label for="product_type">Product Type</label>
                  <select
                    id="product_type"
                    name="product_type"
                    class="form-control"
                  >
                    <option
                      value="0"
                      :selected="contract.product_type === 0"
                    >
                      Default
                    </option>
                    <option
                      value="1"
                      :selected="contract.product_type === 1"
                    >
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
                      :selected="contract.product_id === product.id"
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
                      :selected="contract.rate_id === rate.id"
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
                    name="utility_id"
                    type="text"
                    class="form-control"
                  >
                    <option value="" />
                    <option
                      v-for="utility in utilitiesClone"
                      :key="utility.utilit_id"
                      :value="utility.utilit_id"
                      :selected="contract.utility_id === utility.utilit_id"
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
                    :checked="contract.signature_required_customer === 1"
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
                    :checked="contract.signature_required_customer === 0"
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
                    :checked="contract.signature_required_agent === 1"
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
                    :checked="contract.signature_required_agent === 0"
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
                      :selected="contract.document_type_id === 1"
                    >
                      Contract Only
                    </option>
                    <option
                      value="3"
                      :selected="contract.document_type_id === 3"
                    >
                      Contract + Signature Page
                    </option>
                  </select>
                </div>
              </div>
              <div class="col-md-4 pl-5">
                <div class="form-group">
                  <label for="contract_doc">Upload Contract (optional)</label><br>
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
              tabindex="-1"
              class="btn btn-danger pull-left"
              type="button"
              @click="deleteContract"
            >
              <span class="fa fa-trash-o" /> Delete Permanently
            </button>

            <button
              class="btn btn-primary pull-right"
              type="submit"
              @click="submit($event)"
            >
              <em class="fa fa-floppy-o" />
              Save
            </button>
          </form>

          <br><br>

          <div
            v-if="mapPVersions().length"
            id="previousVersions"
            class="row mt-4"
          >
            <div class="col-12">
              <h4>
                Previous Versions
              </h4>
              <custom-table
                :headers="headers"
                :data-grid="mapPVersions()"
                :data-is-loaded="true"
                :show-action-buttons="true"
                :has-action-buttons="true"
                empty-table-message="No previous versions were found."
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </layout>
</template>
<script>
import CustomTable from 'components/CustomTable';
import Layout from '../brands/edit/Layout';

export default {
    name: 'ContractBrandEdit',
    components: {
        Layout,
        CustomTable,
    },
    props: {
        contract: {
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
        languages: {
            type: Array,
            required: true,
            default: () => [],
        },
        configurations: {
            type: Array,
            required: true,
            default: () => [],
        },
        utilities: {
            type: Array,
            required: true,
            default: () => [],
        },
        previousVersions: {
            type: Array,
            default: () => [],
        },
        errors: {
            type: Array,
            default: () => [],
        },
        awsCloudFront: {
            type: String,
            default: '',
        },
    },
    data() {
        console.log(this.mapPVersions());
        console.log(this.contract);

        return {
            headers: [{
                label: 'Date',
                key: 'created_at',
                serviceKey: 'created_at',
            }, {
                label: 'Source file',
                key: 'source_file',
                serviceKey: 'source_file',
            }, {
                label: 'Uploaded By',
                key: 'uploaded_by_name',
                serviceKey: 'uploaded_by_name',
            }],
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
            errorMsg: {
                text: 'This configuration already exists.',
                active: false,
                conflict: {
                    obj1: {},
                    obj2: {},
                },
            },
            csrf_token: window.csrf_token,
            showExpandedRateTypeField: false,
            selectedState: this.contract.state_id,
            selectedCommodity: this.contract.commodity,
            utilitiesClone: this.utilities,
        };
    },
    mounted() {
        if (
            this.contract.expanded_rate_type
                || this.contract.rate_type_id === 3
        ) {
            this.showExpandedRateTypeField = true;
        }
        else {
            this.showExpandedRateTypeField = false;
        }

        this.filterUtilities();
    },
    methods: {
        filterUtilities() {
            let filteredUtilities = [];
            this.utilitiesClone = [];

            if (this.selectedCommodity.length > 0 && this.selectedState.length === 0) {
                filteredUtilities = this.utilities.filter((el) => this.selectedCommodity == el.commodity);
            }
            else if (this.selectedState.length > 0 && this.selectedCommodity.length === 0) {
                filteredUtilities = this.utilities.filter((el) => this.selectedState == el.state_id);
            }
            else {
                filteredUtilities = this.utilities.filter((el) => this.selectedState == el.state_id
                && this.selectedCommodity == el.commodity);
            }
  
            this.utilitiesClone = filteredUtilities;
        },
        deleteContract() {
            if (confirm('Are you sure you want to PERMANENTLY delete this contract?')) {
                axios.post(`/brand_contracts/${this.contract.id}/delete`)
                    .then(() => {
                        window.location.href = `/brands/${this.contract.brand_id}/get_contracts`;
                    })
                    .catch((e) => {
                        console.log(e);
                        alert('Could not delete contract');
                    });
            }
        },
        submit(e) {
            const formData = new FormData(this.$refs.contractForm);
            const currentObj = {};
            for (const pair of formData.entries()) {
                if (!['_token', 'contract_doc'].includes(pair[0])) {
                    currentObj[pair[0]] = pair[1];
                }
            }
            currentObj.expanded_rate_type = this.contract.expanded_rate_type;
            currentObj.product_type = this.contract.product_type;
            console.log('currentObj:');
            console.log(currentObj);
            console.log('configurations:');
            for (let i = 0; i < this.configurations.length; i++) {
                console.log(this.configurations[i]);
                if (this.areEqualObjects(currentObj, this.configurations[i])) {
                    e.preventDefault();
                    this.errorMsg.conflict.obj1 = this.mapObjValues(currentObj);
                    this.errorMsg.conflict.obj2 = this.mapObjValues(this.configurations[i]);
                    this.errorMsg.active = true;
                    break;
                }
            }
            if (this.contract.rate_type_id === 3 && this.contract.expanded_rate_type === null) {
                alert('Tiered rates require an Expanded Rate Type.');
                e.preventDefault();
            }
        },
        areEqualObjects(obj1, obj2) {
            /* if (Object.keys(obj1).length !== Object.keys(obj2).length) {
                // This should be false since they are not equal but im trying to capture the conflict so
                return true;
            }*/
            // for (let i = 0; i < Object.keys(obj1).length; i++) {
            //     const key = Object.keys(obj1)[i];
            //     if (!obj2[key] || obj1[key] == obj2[key]) {
            //         return false;
            //     }
            // }
            if (JSON.stringify(this.orderObjectByKeys(obj1)) === JSON.stringify(this.orderObjectByKeys(obj2))) {
                return true;
            }
            return false;
        },
        orderObjectByKeys(obj) {
            const ordered = {};
            Object.keys(obj).sort().forEach((key) => {
                ordered[key] = obj[key];
            });
            return ordered;
        },
        mapObjValues(obj) {
            const state_id = this.objValueOrId(obj, this.states, 'state_id');
            const market_id = this.objValueOrId(obj, this.markets, 'market_id');
            const commodity = this.objValueOrId(obj, this.commodities, 'commodity');
            const rate_type_id = this.objValueOrId(obj, this.rateTypes, 'rate_type_id');
            const language_id = this.objValueOrId(obj, this.languages, 'language_id');
            const channel_id = this.objValueOrId(obj, this.channels, 'channel_id');
            let expanded_rate_type = null;
            if (this.contract.expanded_rate_type) {
                expanded_rate_type = this.objValueOrId(obj, this.expanded_rate_type, 'expanded_rate_type');
            }

            return {
                state_id,
                market_id,
                commodity,
                rate_type_id,
                language_id,
                channel_id,
                expanded_rate_type,
            };
        },
        objValueOrId(obj, arr, key) {
            return arr.find((s) => s.id == obj[key]) ? arr.find((s) => s.id == obj[key]).name : obj[key];
        },
        fileURLGenerator(obj) {
            const url = `${this.awsCloudFront}/contracts/`;

            return url + obj.file_name;
        },
        mapPVersions() {
            if (this.previousVersions) {
                const mutatedPV = [this.contract, ...this.previousVersions];
                mutatedPV.forEach((pv) => {
                    pv.uploaded_by_name = (pv.uploaded_by_name) || '--';
                    pv.source_file = `<a href="${this.fileURLGenerator(pv)}">View file</a>`;
                    // if (pv.id !== this.contract.id) {
                    //     pv.buttons = [{
                    //         type: 'monitor',
                    //         url: `/brand_contracts/${pv.id}/restore`,
                    //         buttonSize: 'medium',
                    //         label: 'Restore',
                    //         messageAlert: 'Are you sure you want to restore this contract?',
                    //         icon: 'undo',
                    //     }];
                    // }

                });
                return mutatedPV;
            }
            return [this.contract];
        },
        checkRateTypeSelected() {
            if (this.contract.rate_type_id === 3) {
                this.showExpandedRateTypeField = true;
            }
            else {
                this.showExpandedRateTypeField = false;
                this.contract.expanded_rate_type = null;
            }
        },
    },
};
</script>
