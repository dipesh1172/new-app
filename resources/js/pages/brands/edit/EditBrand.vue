<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: `${brand.name} Configuration`, active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <ValidationObserver ref="formHandler">
        <form
          ref="formObject"
          method="POST"
          :action="`/brands/${brand.id}`"
          autocomplete="off"
          enctype="multipart/form-data"
        >
          <input
            name="_method"
            type="hidden"
            value="PUT"
          >
          <input
            name="_token"
            type="hidden"
            :value="csrf_token"
          >
          <div class="row">
            <div class="col-md-3 text-center">
              <img
                v-if="logo"
                :src="logo"
                alt="Logo"
                width="100%"
              >
              <img
                v-else
                src="https://s3.amazonaws.com/tpv-assets/no-logo.png"
                alt="No logo"
                style="max-width: 100%; height: auto;"
              >

              <br><br>

              <div class="form-group">
                <input
                  name="logo_upload"
                  type="file"
                  style="border: 1px solid #c2cfd6;padding: .5rem .75rem;width: 100%;"
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
                    v-model="brand.allow_bg_checks"
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
                    v-model="brand.billing_enabled"
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
                    :selected="brand.billing_frequency === 'monthly'"
                  >
                    Monthly
                  </option>
                  <option
                    value="bi-weekly"
                    :selected="brand.billing_frequency === 'bi-weekly'"
                  >
                    Bi-Weekly
                  </option>
                  <option
                    value="weekly"
                    :selected="brand.billing_frequency === 'weekly'"
                  >
                    Weekly
                  </option>
                </select>
              </div>
            </div>

            <div class="col-md-9">
              <div
                v-if="flashMessage"
                class="alert alert-success"
              >
                <span class="fa fa-check-circle" />
                <em> {{ flashMessage }}</em>
              </div>

              <div
                v-if="errors.length"
                class="alert alert-danger"
              >
                <strong>Errors</strong><br>
                <ul>
                  <li
                    v-for="(error, i) in errors"
                    :key="i"
                  >
                    {{ error }}
                  </li>
                </ul>
              </div>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="client_id">Client</label>
                    <ValidationProvider
                      v-slot="{ errors }"
                      name="client_id"
                      rules="required|min:1"
                    >
                      <select
                        v-model="brand.client_id"
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
                    <label for="legal_name">Brand Legal Name</label>
                    <ValidationProvider
                      v-slot="{ errors }"
                      name="legal_name"
                      rules="required|min:1|max:64"
                    >
                      <input
                        v-model="brand.legal_name"
                        type="text"
                        name="legal_name"
                        class="form-control form-control-lg"
                        placeholder="Enter a Brand's LEGAL Name"
                      >
                      <span class="text-danger">{{ errors[0] }}</span>
                    </ValidationProvider>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="name">Brand Short Name</label>
                    <input
                      v-model="brand.name"
                      type="text"
                      name="name"
                      class="form-control form-control-lg"
                      placeholder="Enter a Brand Short Name"
                    >                                
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="address">Billing Address</label>
                    <input
                      v-model="brand.address"
                      type="text"
                      name="address"
                      class="form-control form-control-lg"
                      placeholder="Enter a Billing Address"
                    >
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="city">Billing City</label>
                    <input
                      v-model="brand.city"
                      type="text"
                      name="city"
                      class="form-control form-control-lg"
                      placeholder="Enter a Billing City"
                    >
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="state">Billing State</label>
                    <select
                      v-model="brand.state"
                      name="state"
                      class="form-control form-control-lg"
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
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="zip">Billing Zip Code</label>
                    <input
                      v-model="brand.zip"
                      type="text"
                      name="zip"
                      class="form-control form-control-lg"
                      placeholder="Enter a Billing Zip Code"
                    >
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="service_number">Billing Phone</label>
                    <the-mask
                      v-model="brand.service_number"
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
                      v-model="brand.purchase_order_no"
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
                      v-model="brand.po_valid_until"
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
                    <label for="billing_distribution">
                      Billing Distribution <span class="font-italic">(Receives links)</span>
                    </label>
                    <textarea
                      v-model="brand.billing_distribution"
                      name="billing_distribution"
                      class="form-control form-control-lg"
                      placeholder="Enter a Billing Distribution"
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
                      v-model="brand.accounts_payable_distribution"
                      name="accounts_payable_distribution"
                      class="form-control form-control-lg"
                      placeholder="Enter a Accounts Payable Distribution"
                      style="height: 100px;"
                    />
                  </div>
                </div>
              </div><div class="row">
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea
                      v-model="brand.notes"
                      name="notes"
                      class="form-control form-control-lg"
                      placeholder="Enter Notes"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-12">
              <button
                type="button"
                class="btn btn-danger btn-sm pull-left"
                @click="setupDefaults"
              >
                <i class="fa fa-exclamation-circle" /> Setup Defaults
              </button>
              <button
                type="button"
                class="btn btn-primary pull-right"
                @click="onSubmit"
              >
                <i
                  class="fa fa-floppy-o"
                  aria-hidden="true"
                /> 
                Submit
              </button>
            </div>
          </div>
        </form>
      </ValidationObserver>
    </div>
  </layout>
</template>

<script>
import { mapState } from 'vuex';
import { TheMask } from 'vue-the-mask';
import { ValidationProvider, ValidationObserver } from 'vee-validate/dist/vee-validate.full.esm';
import Layout from './Layout';

export default {
    name: 'EditBrand',
    components: {
        Layout,
        TheMask,
        ValidationObserver,
        ValidationProvider,
    },
    props: {
        brand: {
            type: Object,
            default: () => ({}),
        },
        clients: {
            type: Array,
            default: () => [],
        },
        states: {
            type: Array,
            default: () => [],
        },
        errors: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            logo: this.brand.filename ? `${this.$store.state.AWS_CLOUDFRONT}/${this.brand.filename}` : '',
        };
    },
    computed: {
        ...mapState(['AWS_CLOUDFRONT']),
        csrf_token() {
            return csrf_token;
        },
    },
    methods: {
        setupDefaults() {
            if (confirm('This will replace all dispositions, enrollment file, business rules and rate card with default values.\nThis process is not reversible.\n\nAre you sure you want to do this?')) {
                axios.post(`/brands/${this.brand.id}/setup-default`, {
                    _token: this.csrf_token,
                }).then((res) => {
                    if (res.data.error) {
                        throw new Error(res.data.message);
                    }
                    alert('The brand has had the defaults setup');
                }).catch((e) => {
                    console.log('setupdefaults returned error', e);
                    alert('There was an error while setting the defaults, no changes were made.');
                });
            }
        },
        handleLogoChange({ target: { files } }) {
            const reader = new FileReader();
            const self = this;
            reader.onload = function(e) {
                self.logo = e.target.result;
            };
            reader.onerror = function(e) {
                self.logo = null;
            };
            reader.readAsDataURL(files[0]);
        },
        onSubmit() {
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
