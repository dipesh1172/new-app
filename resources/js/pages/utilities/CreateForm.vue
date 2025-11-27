<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Utilities', url: '/utilities'},
        {name: 'Add Utility', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <em class="fa fa-th-large" /> Add a Utility Provider
          </div>
          <div class="card-body">
            <ValidationObserver ref="validationObserver">
              <form
                ref="formObject"
                method="POST"
                action="/utilities/storeUtility"
                autocomplete="off"
              >
                <input
                  type="hidden"
                  name="_token"
                  :value="csrf_token"
                >

                <div class="row">
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
                </div>

                <div class="row">
                  <div class="col-md-4">
                    <ValidationProvider
                      v-slot="{ errors }"
                      name="name"
                      rules="required|max:64"
                    >
                      <div class="form-group">
                        <label for="name">Utility Name</label>
                        <input
                          v-model="values.name"
                          type="text"
                          name="name"
                          class="form-control form-control-lg"
                          placeholder="Enter a Utility Name"
                        >
                        <span class="text-danger">{{ errors[0] }}</span>
                      </div>
                    </ValidationProvider>
                  </div>

                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="ldc_code">LDC Code</label>
                      <input
                        v-model="values.ldc_code"
                        type="text"
                        name="ldc_code"
                        class="form-control form-control-lg"
                        placeholder="Enter a LDC Code"
                      >
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="name_ivr">IVR Name</label>
                      <input
                        v-model="values.name_ivr"
                        type="text"
                        name="name_ivr"
                        class="form-control form-control-lg"
                        placeholder="Enter a IVR name"
                      >
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="address">Address</label>
                      <input
                        v-model="values.address"
                        type="text"
                        name="address"
                        class="form-control form-control-lg"
                        placeholder="Enter an Address"
                      >
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="city">City</label>
                      <input
                        v-model="values.city"
                        type="text"
                        name="city"
                        class="form-control form-control-lg"
                        placeholder="Enter a City"
                      >
                    </div>
                  </div>
                  <div class="col-md-4">
                    <ValidationProvider
                      v-slot="{ errors }"
                      name="state"
                      rules="required|min:1"
                    >
                      <div class="form-group">
                        <label for="state">{{ stateLabel }}</label>
                        <select
                          v-model="values.state"
                          name="state"
                          class="form-control form-control-lg"
                        >
                          <option value>
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
                        <span class="text-danger">{{ errors[0] }}</span>
                      </div>
                    </ValidationProvider>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="zip">Zip</label>
                      <input
                        v-model="values.zip"
                        type="text"
                        name="zip"
                        class="form-control form-control-lg"
                        placeholder="Enter a Zip"
                      >
                    </div>
                  </div>
                  <div class="col-md-6">
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
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="service_number">Phone</label>
                      <the-mask
                        id="service_number"
                        v-model="values.service_number"
                        :mask="['(###) ###-####']"
                        class="form-control form-control-lg"
                        placeholder="Enter a Customer Service Phone"
                        name="service_number"
                      />
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="duns">DUNS</label>
                      <input
                        v-model="values.duns"
                        type="text"
                        name="duns"
                        class="form-control form-control-lg"
                        placeholder="Enter the DUNS"
                      >
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="disclosure_document">Disclosure Document</label>
                      <input
                        v-model="values.disclosure_document"
                        type="text"
                        name="disclosure_document"
                        class="form-control form-control-lg"
                        placeholder="Enter a Disclosure Document Name"
                      >
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="discount_program">Discount Program</label>
                      <input
                        v-model="values.discount_program"
                        type="text"
                        name="discount_program"
                        class="form-control form-control-lg"
                        placeholder="Enter a Discount Program Name"
                      >
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="website">Website</label>
                      <input
                        v-model="values.website"
                        type="text"
                        name="website"
                        class="form-control form-control-lg"
                        placeholder="Enter a Web Address"
                      >
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="service_zips">Service Zips (Comma Separated)</label>
                      <textarea
                        v-model="values.service_zips"
                        name="service_zips"
                        class="form-control form-control-lg"
                        style="width: 100%; height: 200px;"
                        placeholder="Enter Service Zips (Comma Separated)"
                      />
                    </div>
                  </div>
                </div>

                <br>

                <h3>Supported Types</h3>
                <div class="row p-4">
                  <div
                    v-for="utilityType in utilityTypes"
                    :key="utilityType.id"
                    class="col-md-2"
                  >
                    <div class="form-check form-check-inline">
                      <input
                        v-model="values.supported[utilityType.id]"
                        type="checkbox"
                        :name="`supported[${utilityType.id}]`"
                        class="form-check-input"
                        value="on"
                      >
                      <label class="form-check-label">{{ utilityType.utility_type }}</label>
                    </div>
                  </div>
                </div>

                <br>
                <hr>
                <br>

                <div class="row">
                  <div class="col-md-12">
                    <button
                      type="button"
                      class="btn btn-primary btn-lg pull-right"
                      @click="onClick"
                    >
                      <em
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
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import {TheMask} from 'vue-the-mask';
import Breadcrumb from 'components/Breadcrumb';
import { ValidationProvider, ValidationObserver } from 'vee-validate/dist/vee-validate.full.esm';

export default {
    name: 'CreateUtility',
    components: {
        TheMask,
        Breadcrumb,
        ValidationObserver,
        ValidationProvider,
    },
    props: {
        countries: {
            type: Array,
            default: () => [],
        },
        states: {
            type: Array,
            default: () => [],
        },
        utilityTypes: {
            type: Array,
            default: () => [],
        },
        initialValues: {
            type: Object,
            default: () => ({}),
        },
        errors: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        const values = {};
        const supported = {};
        this.utilityTypes.forEach((ut) => (supported[ut.id] = false));
        const defaultValues = {
            name: '',
            country_id: this.countries[0].id,
            address: '',
            city: '',
            state: '',
            zip: '',
            service_number: '',
            discount_program: '',
            disclosure_document: '',
            service_zips: '',
            duns: '',
            ldc_code: '',
            supported: {},
            name_ivr: '',
        };
        Object.keys(defaultValues).forEach((key) => {
            if (key in this.initialValues) {
                values[key] = this.initialValues[key];
            }
            else {
                values[key] = defaultValues[key];
            }
        });
        return {
            values,
        };
    },
    computed: {
        csrf_token() {
            return csrf_token;
        },
        selectedCountry() {
            return this.countries.find(({ id }) => id == this.values.country_id);
        },
        stateLabel() {
            if (this.selectedCountry.name === 'Canada') {
                return 'Province';
            }

            return 'State';
        },
        countryStates() {
            return this.states
                .filter(({ country_id }) => country_id == this.values.country_id)
                .sort(({ name: a }, { name: b }) => a.localeCompare(b));
        },
    },
    mounted() {
        document.title += ' Add Utility';
    },
    methods: {
        onClick() {
            this.$refs.validationObserver.validate().then((success) => {
                if (!success) {
                    return;
                }

                this.$refs.formObject.submit();
            });
        },
    },
};
</script>
