<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Clients', url: '/clients'},
        {name: 'Add Client', active: true}
      ]"
    />
    
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Add a Client
          </div>
          <div class="card-body">
            <form
              method="POST"
              action="/clients"
              autocomplete="off"
              enctype="multipart/form-data"
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
                    class="img-avatar"
                    alt="No logo"
                    style="max-width: 100%; height: auto;"
                  >

                  <br><br>
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

                  <div class="form-group">
                    <label for="name">Client Name</label>
                    <input
                      v-model="values.name"
                      type="text"
                      name="name"
                      class="form-control form-control-lg"
                      placeholder="Enter a Client Name"
                    >
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
                        <label for="address">Address</label>
                        <input
                          v-model="values.address"
                          type="text"
                          name="address"
                          class="form-control form-control-lg"
                          placeholder="Enter the Address"
                        >
                      </div>
                    </div>
                  </div>

                  <div class="row">
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
                      <div class="form-group">
                        <label for="state">{{ stateLabel }}</label>
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
                        <label for="zip">{{ zipLabel }}</label>
                        <input
                          v-model="values.zip"
                          type="text"
                          name="zip"
                          class="form-control form-control-lg"
                          :placeholder="`Enter a ${zipLabel}`"
                        >
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="phone">Phone</label>
                        <the-mask
                          v-model="values.phone"
                          type="text"
                          name="phone"
                          mask="(###) ###-####"
                          class="form-control form-control-lg"
                          placeholder="Enter a Phone"
                        />
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="email">Email</label>
                        <input
                          v-model="values.email"
                          type="text"
                          name="email"
                          class="form-control form-control-lg"
                          placeholder="Enter a Email"
                        >
                      </div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea
                      v-model="values.notes"
                      name="notes"
                      class="form-control form-control-lg"
                      placeholder="Enter notes"
                    />
                  </div>

                  <button
                    type="submit"
                    class="btn btn-primary"
                  >
                    Submit
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { TheMask } from 'vue-the-mask';
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'CreateClient',
    components: {
        TheMask,
        Breadcrumb,
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
        const values = {};

        const defaultValues = {
            name: '',
            country_id: this.countries[0].id,
            address: '',
            city: '',
            state: '',
            zip: '',
            phone: '',
            email: '',
            notes: '',
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
        handleLogoChange(e) {
            const reader = new FileReader();
            reader.onload = ({target: { result }}) => this.logoPreview = result;
            reader.readAsDataURL(e.target.files[0]);
        },
    },
};
</script>
