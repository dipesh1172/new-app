<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Brands', url: '/brands'},
        {name: 'Add Brand', active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Add a Brand
          </div>
          <div class="card-body">
            <ValidationObserver ref="formHandler">
              <form 
                ref="formObject"
                action="/brands" 
                method="POST"
                autocomplete="off"
              >
                <input
                  type="hidden"
                  name="_token"
                  :value="csrf_token"
                >
                <div class="row">
                  <div class="col-md-3 text-center">
                    <img
                      :src="logoPreview"
                      alt="No logo"
                      style="width: 100%; height: auto;"
                    >
                    
                    <div class="form-group">
                      <input
                        name="logo_upload"
                        type="file"
                        :style="{
                          border: '1px solid #c2cfd6',
                          padding: '.5rem .75rem',
                          width: '100%'
                        }"
                        @change="handleLogoChange"
                      >
                    </div>
                    <hr>
                    <div class="form-group">
                      <label for="allow_bg_checks">Allow Background Checks</label>
                      <br>
                      <label class="switch">
                        <input
                          id="allow_bg_checks"
                          v-model="values.allow_bg_checks"
                          type="checkbox"
                          name="allow_bg_checks"
                        > 
                        <span class="slider round" />
                      </label>
                    </div>
                    <div class="form-group">
                      <label for="billing_enabled">Enable Billing</label>
                      <br>
                      <label class="switch">
                        <input
                          id="billing_enabled"
                          v-model="values.billing_enabled"
                          type="checkbox"
                          name="billing_enabled"
                        > 
                        <span class="slider slider-success round" />
                      </label>
                    </div>
                    <div class="form-group">
                      <label for="billing_frequency">Billing Frequency</label>
                      <select
                        id="billing_frequency"
                        class="form-control form-control-lg"
                        name="billing_frequency"
                      >
                        <option
                          value="monthly"
                          :selected="values.billing_frequency === 'monthly'"
                        >
                          Monthly
                        </option>
                        <option
                          value="bi-weekly"
                          :selected="values.billing_frequency === 'bi-weekly'"
                        >
                          Bi-Weekly
                        </option>
                        <option
                          value="weekly"
                          :selected="values.billing_frequency === 'weekly'"
                        >
                          Weekly
                        </option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-9">
                    <div
                      v-show="flashMessage"
                      class="alert alert-success"
                    >
                      <span class="fa fa-check-circle" /><em>{{ flashMessage }}</em>
                    </div>

                    <div
                      v-show="errors.length"
                      class="alert alert-danger"
                    >
                      <li
                        v-for="(error, index) in errors"
                        :key="index"
                      >
                        {{ error }}
                      </li>
                    </div>

                    <div class="row">
                      <div class="col-md-12">
                        <div class="form-group">
                          <label for="client_id">Select a Client</label>
                          <ValidationProvider
                            v-slot="{ errors }"
                            name="client_id"
                            rules="required|min:1"
                          >
                            <select
                              v-model="values.client_id"
                              name="client_id"
                              class="form-control form-control-lg"
                            >
                              <option value="">
                                Select a Client
                              </option>
                              <option
                                v-for="client in clients"
                                :key="client.id"
                                :value="client.id"
                              >
                                {{ client.name }}
                              </option>
                            </select>
                            <span class="text-danger">{{ errors[0] }}</span>
                          </ValidationProvider>
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <ValidationProvider
                            v-slot="{ errors }"
                            name="legal_name"
                            rules="min:1|max:64"
                          >
                            <label for="leagal">Brand Legal Name</label>
                            <input
                              v-model="values.legal_name"
                              type="text"
                              name="legal_name"
                              class="form-control form-control-lg"
                              placeholder="Enter the Brand's Legal Name"
                            >
                            <span class="text-danger">{{ errors[0] }}</span>
                          </ValidationProvider>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <ValidationProvider
                            v-slot="{ errors }"
                            name="name"
                            rules="required|min:1|max:64"
                          >
                            <label for="name">Brand Short Name</label>
                            <input
                              v-model="values.name"
                              type="text"
                              name="name"
                              class="form-control form-control-lg"
                              placeholder="Enter a Brand Name"
                            >
                            <span class="text-danger">{{ errors[0] }}</span>
                          </ValidationProvider>
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="country_id">Select a Country</label>
                          <select
                            v-model="values.country_id"
                            name="country_id"
                            class="form-control form-control-lg"
                          >
                            <option
                              v-for="country in countries"
                              :key="country.id"
                              :value="country.id"
                            >
                              {{ country.name }}
                            </option>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-8">
                        <div class="form-group">
                          <label for="address">Billing Address</label>
                          <input
                            v-model="values.address"
                            type="text"
                            name="address"
                            class="form-control form-control-lg"
                            placeholder="Enter the Billing Address"
                          >
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="city">Billing City</label>
                          <input
                            v-model="values.city"
                            type="text"
                            name="city"
                            class="form-control form-control-lg"
                            placeholder="Enter a Billing City"
                          >
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="state">Billing {{ stateLabel }}</label>
                          <select
                            v-model="values.state"
                            name="state"
                            class="form-control form-control-lg"
                          >
                            <option value="">
                              Select a {{ stateLabel }}
                            </option>
                            <option
                              v-for="state in countryStates"
                              :key="state.id"
                              :value="state.id"
                            >
                              {{ state.name }}
                            </option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="zip">Billing {{ zipLabel }}</label>
                          <input
                            v-model="values.zip"
                            type="text"
                            name="zip"
                            class="form-control form-control-lg"
                            :placeholder="`Enter a Billing ${zipLabel}`"
                          >
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="service_number">Billing Phone</label>
                          <the-mask
                            v-model="values.service_number"
                            type="text"
                            name="service_number"
                            mask="(###) ###-####"
                            class="form-control form-control-lg"
                            placeholder="Enter a Billing Phone"
                          />
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="purchase_order_no">Purchase Order Number</label>
                          <input
                            v-model="values.purchase_order_no"
                            name="purchase_order_no"
                            class="form-control form-control-lg"
                            placeholder="Enter a Purchase Order Number"
                          >
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="po_valid_until">Purchase Order Number Valid Until</label>
                          <input
                            v-model="values.po_valid_until"
                            name="po_valid_until"
                            class="form-control form-control-lg"
                            placeholder="Enter a Purchase Order Number Valid Until"
                          >
                        </div>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="billing_distribution">Billing Distribution <span class="font-italic">(Receives links)</span></label>
                          <textarea
                            v-model="values.billing_distribution"
                            name="billing_distribution"
                            class="form-control form-control-lg"
                            placeholder="Enter email addresses (comma separated)"
                            style="height: 100px;"
                          />
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="accounts_payable_distribution">
                            Accounts Payable Distribution <span class="font-italic">(Receives attachments)</span>
                          </label>
                          <textarea
                            v-model="values.accounts_payable_distribution"
                            name="accounts_payable_distribution"
                            class="form-control form-control-lg"
                            placeholder="Enter email addresses (comma separated)"
                            style="height: 100px;"
                          />
                        </div>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-md-12">
                        <div class="form-group">
                          <label for="notes">Notes</label>
                          <textarea
                            v-model="values.notes"
                            name="notes"
                            class="form-control form-control-lg"
                            placeholder="Enter notes"
                          />
                        </div>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-md-8">
&nbsp;
                      </div>
                      <div class="col-md-2">
                        <div class="form-group">
                          <label for="setup_default">Setup Defaults</label>
                          <br>
                          <label class="switch">
                            <input
                              id="setup_default"
                              v-model="values.setup_default"
                              type="checkbox"
                              name="setup_default"
                              @click="warnNoDefaults"
                            > 
                            <span class="slider slider-danger round" />
                          </label>
                        </div>
                      </div>
                      <div class="col-md-2">
                        &nbsp;
                        <br>
                        <button
                          type="button"
                          class="btn btn-primary pull-right"
                          @click="onFormSubmit"
                        >
                          Submit
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </form>
            </ValidationObserver>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { TheMask } from 'vue-the-mask';
import Breadcrumb from 'components/Breadcrumb';

import {
    ValidationProvider,
    ValidationObserver,
} from 'vee-validate/dist/vee-validate.full';

export default {
    name: 'CreateBrandForm',
    components: {
        TheMask,
        Breadcrumb,
        ValidationProvider,
        ValidationObserver,
    },
    props: {
        clients: {
            type: Array,
            default: () => [],
        },
        countries: {
            type: Array,
            default: () => [],
        },
        states: {
            type: Array,
            default: () => [],
        },
        initialValues: {
            type: Object,
            default: () => ({}),
        },
        flashMessage: {
            type: String,
            default: '',
        },
        errors: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        console.log('initialValues', this.initialValues);
        const values = {};

        const defaultValues = {
            client_id: '',
            name: '',
            country_id: this.countries[0].id,
            address: '',
            city: '',
            state: '',
            zip: '',
            service_number: '',
            billing_distribution: '',
            notes: '',
            purchase_order_no: '',
            po_valid_until: '',
            legal_name: '',
            billing_enabled: false,
            billing_frequency: 'bi-weekly',
            allow_bg_check: false,
            accounts_payable_distribution: '',
            setup_default: true,
        };

        Object.keys(defaultValues).forEach((key) => {
            if (key in this.initialValues) {
                if (['billing_enabled', 'allow_bg_check', 'setup_default'].includes(key)) {
                    values[key] = this.initialValues[key] == 'on';
                }
                else {
                    values[key] = this.initialValues[key] == null ? '' : this.initialValues[key];
                }
            }
            else {
                values[key] = defaultValues[key];
            }
        });

        return {
            values,
            logoPreview: 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mm&s=300',
        };
    },
    computed: {
        csrf_token() {
            return csrf_token;
        },
        selectedCountry() {
            return this.countries.find(({id}) => id == this.values.country_id);
        },
        zipLabel() {
            if (this.selectedCountry.name === 'Canada') {
                return 'Postal Code';
            }
            return 'Zip Code';
            
        },
        stateLabel() {
            if (this.selectedCountry.name === 'Canada') {
                return 'Province';
            }
            return 'State';
            
        },
        countryStates() {
            return this.states
                .filter(({country_id}) => country_id == this.values.country_id)
                .sort(({name: a}, {name: b}) => a.localeCompare(b));
        },
    },
    methods: {
        warnNoDefaults(e) {
            if (this.values.setup_default) {
                if (!window.confirm('If this is unchecked this brand will NOT have dispositions and other default items necessary for most brands created alongside it.')) {
                    console.log('setting setup_default back to true');
                    e.preventDefault();
                    this.values.setup_default = true;
                }
            }
        },
        handleLogoChange(e) {
            const reader = new FileReader();
            reader.onload = ({target: { result }}) => this.logoPreview = result;
            reader.readAsDataURL(e.target.files[0]);
        },
        onFormSubmit() {
            this.$refs.formHandler.validate().then((success) => {
                if (!success) {
                    return false;
                }

                this.$refs.formObject.submit();
            });
        },
    },
};
</script>
