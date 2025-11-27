<template>
  <div>
    <div
      class="col-12"
      :style="{paddingRight: 0}"
    >
      <breadcrumb
        :items="[
          {name: 'Home', url: '/'},
          {name: 'Brands', url: '/brands'},
          {name: 'Vendors', url: `/brands/${brand.id}/vendors`},
          {name: 'Add Vendor', active: true}
        ]"
      />
    </div>

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Add a Vendor to {{ brand.name }}
          </div>
          <div class="card-body">
            <br><br>
            <form
              method="POST"
              :action="`/brands/${brand.id}/vendors/storeVendor`"
              accept-charset="UTF-8"
              autocomplete="off"
            >
              <input
                name="_token"
                type="hidden"
                :value="csrfToken"
              >
              <div class="row">
                <div class="col-6">
                  <div class="form-group">
                    <select
                      id="vendor"
                      name="vendor"
                      class="form-control form-control-lg"
                    >
                      <option value="">
                        Select a Vendor
                      </option>
                      <option
                        v-for="vendor in vendors"
                        :key="vendor.id"
                        :value="vendor.id"
                      >
                        {{ vendor.name }}
                        <template v-if="vendor.address">
                          -- {{ vendor.address }} {{ vendor.city }}, {{ vendor.state_abbrev }} {{ vendor.zip }}
                        </template>
                      </option>
                    </select>
                  </div>
                </div>
                <div class="col-3">
                  <div class="form-group">
                    <input
                      type="text"
                      name="vendor_label_add"
                      class="form-control form-control-lg"
                      placeholder="Vendor Label"
                    >
                  </div>
                </div>
                <div class="col-3">
                  <div class="form-group">
                    <input
                      type="text"
                      name="vendor_id_add"
                      class="form-control form-control-lg"
                      placeholder="Vendor ID (optional)"
                    >
                  </div>
                </div>
              </div>

              <button
                type="submit"
                class="btn btn-primary btn-lg pull-right"
              >
                <i
                  class="fa fa-floppy-o"
                  aria-hidden="true"
                /> 
                Submit
              </button>

              <br class="clearfix">
              <br class="clearfix">
              <br class="clearfix">
              <br>

              <div class="row">
                <div class="col-md-5">
                  <hr>
                </div>
                <div class="col-2 text-center">
                  <strong>OR</strong>
                </div>
                <div class="col-md-5">
                  <hr>
                </div>
              </div>

              <br>
              <br>
                        
              <div class="col-md-12">
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

                <div class="form-group">
                  <label for="name">Vendor Name</label>
                  <input
                    id="name"
                    class="form-control form-control-lg"
                    placeholder="Enter a Vendor Name"
                    name="name"
                    type="text"
                  >
                </div>

                <div class="row">
                  <div class="col-4">
                    <div class="form-group">
                      <label for="vendor_label">Vendor Label</label>
                      <i
                        class="fa fa-question-circle"
                        aria-hidden="true"
                        data-toggle="tooltip"
                        data-placement="top"
                        title="A vendor label typically represents the external ID of the vendor for the defined brand.  This also may be the name or abbreviation of what the brand calls the vendor internally."
                      />
                      <input
                        id="vendor_label"
                        class="form-control form-control-lg"
                        placeholder="Enter a Vendor Label"
                        name="vendor_label"
                        type="text"
                      >
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="form-group">
                      <label for="vendor_code">Vendor Code</label>
                      <i
                        class="fa fa-question-circle"
                        aria-hidden="true"
                        data-toggle="tooltip"
                        data-placement="top"
                        title="A vendor code is an additional field for vendor labeling."
                      />
                      <input
                        id="vendor_code"
                        class="form-control form-control-lg"
                        placeholder="Enter a Vendor Code"
                        name="vendor_code"
                        type="text"
                      >
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="form-group">
                      <label for="vendor_id">Vendor ID (optional)</label>
                      <input
                        id="vendor_id"
                        class="form-control form-control-lg"
                        placeholder="Enter a Vendor ID (optional)"
                        name="vendor_id"
                        type="text"
                      >
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label for="address">Address</label>
                  <input
                    id="address"
                    class="form-control form-control-lg"
                    placeholder="Enter the Address"
                    name="address"
                    type="text"
                  >
                </div>

                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="city">City</label>
                      <input
                        id="city"
                        class="form-control form-control-lg"
                        placeholder="Enter a City"
                        name="city"
                        type="text"
                      >
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="state">State</label>
                      <select
                        id="state"
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
                      <label for="zip">Zip Code</label>
                      <input
                        id="zip"
                        class="form-control form-control-lg"
                        placeholder="Enter a Zip Code"
                        name="zip"
                        type="text"
                      >
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label for="service_number">Phone</label>
                  <the-mask
                    :mask="['(###) ###-####']"
                    class="form-control form-control-lg"
                    placeholder="Enter a Phone"
                    name="service_number"
                  />
                </div>

                <br>

                <hr>

                <br>

                <button
                  type="submit"
                  class="btn btn-primary btn-lg pull-right"
                >
                  <i
                    class="fa fa-floppy-o"
                    aria-hidden="true"
                  /> 
                  Submit
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div> 
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';
import {TheMask} from 'vue-the-mask';

export default {
    name: 'AddBrandVendor',
    components: {
        Breadcrumb,
        TheMask,
    },
    props: {
        states: {
            type: Array,
            default: () => [],
        },
        vendors: {
            type: Array,
            default: () => [],
        },
        brand: {
            type: Object,
            required: true,
            default: () => {},
        },
        errors: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            csrfToken: window.csrf_token,
        };
    },
    created() {
        $('[data-toggle="tooltip"]').tooltip();
    },
};
</script>
