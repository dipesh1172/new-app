<template>
  <brand-vendor-nav
    :brand="brand"
    :vendor="vendor"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: `Edit Vendor <strong>${vendor.name}</strong> for <strong>${brand.name}</strong>`, active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <div class="card">
        <div class="card-header">
          <i class="fa fa-th-large" /> Edit Vendor {{ vendor.name }}
        </div>
        <div class="card-body">
          <form
            method="POST"
            :action="`/brands/${brand.id}/vendor/${vendor.id}/updateVendor`"
            accept-charset="UTF-8"
            autocomplete="off"
          >
            <input
              name="_token"
              type="hidden"
              :value="csrfToken"
            >												
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

              <div class="row">
                <div class="col-md-8">
                  <div class="form-group">
                    <label for="name">Vendor Name</label>
                    <input
                      id="name"
                      class="form-control form-control-lg"
                      placeholder="Enter a Vendor Name"
                      name="name"
                      type="text"
                      :value="vendor.name"
                    >
                  </div>

                  <div class="row">
                    <div class="col-4">
                      <div class="form-group">
                        <label for="vendor_label">Vendor Label</label>
                        <input
                          id="vendor_label"
                          class="form-control form-control-lg"
                          placeholder="Enter a Vendor Label"
                          name="vendor_label"
                          type="text"
                          :value="vendor.vendor_label"
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
                          :value="vendor.vendor_code"
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
                          :value="vendor.grp_id"
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
                      :value="vendor.address"
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
                          :value="vendor.city"
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
                            :selected="state.id == vendor.state"
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
                          :value="vendor.zip"
                        >
                      </div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="service_number">Phone</label>
                    <the-mask
                      id="service_number"
                      :mask="['(###) ###-####']"
                      class="form-control form-control-lg"
                      placeholder="Enter a Phone"
                      name="service_number"
                      :value="vendor.service_number"
                    />
                  </div>
                </div>
                <div
                  class="col-md-4"
                  style="border-left: 1px solid #DDDDDD;"
                >
                  <strong>Configuration</strong>

                  <br><hr><br>

                  <strong>HRTPV</strong><br><br>

                  <hr>

                  <table class="table table-striped">
                    <tr>
                      <td>
                        HRTPV enabled
                      </td>
                      <td>
                        <label class="switch switch-text switch-lg switch-pill switch-primary">
                          <input 
                            type="checkbox" 
                            class="switch-input" 
                            name="hrtpv" 
                            :checked="vendor.hrtpv"
                          >
                          <span
                            class="switch-label"
                            data-on="On"
                            data-off="Off"
                          />
                          <span class="switch-handle" />
                        </label>
                      </td>
                    </tr>
                  </table>

                  <br><br>

                  <strong>HTTP Post</strong><br><br>

                  <hr>

                  <table class="table table-striped">
                    <tr>
                      <td>
                        HTTP Post enabled
                      </td>
                      <td>
                        <label class="switch switch-text switch-lg switch-pill switch-primary">
                          <input 
                            type="checkbox" 
                            class="switch-input" 
                            name="http_post" 
                            :checked="vendor.http_post"
                          >
                          <span
                            class="switch-label"
                            data-on="On"
                            data-off="Off"
                          />
                          <span class="switch-handle" />
                        </label>
                      </td>
                    </tr>
                  </table>

                  <table
                    v-if="vendor.http_post_username"
                    class="table table-striped"
                  >
                    <tr>
                      <td>
                        Username: {{ vendor.http_post_username }}
                      </td>
                    </tr>
                    <tr v-if="httpPostPassword">
                      <td>
                        Password: {{ httpPostPassword }}<br>
                        <span class="text-danger">
                          Only shown this time so make sure it is saved.
                        </span>
                      </td>
                    </tr>
                  </table>

                  <br>

                  <strong>Live Enrollments</strong><br><br>

                  <hr>

                  <table class="table table-striped">
                    <tr>
                      <td>
                        Live Enrollments enabled
                      </td>
                      <td>
                        <label class="switch switch-text switch-lg switch-pill switch-primary">
                          <input 
                            type="checkbox" 
                            class="switch-input" 
                            name="live_enroll_enabled" 
                            :checked="vendor.live_enroll_enabled"
                          >
                          <span
                            class="switch-label"
                            data-on="On"
                            data-off="Off"
                          />
                          <span class="switch-handle" />
                        </label>
                      </td>
                    </tr>
                  </table>

                  <br>
                  <strong title="This is ONLY active if there is a Service for it.  Brands & Services - Services (tab) - Services Configuration">
                    Active Customer Lists
                  </strong><br><br>
                  <hr>

                  <table class="table table-striped">
                    <tr>
                      <td>Active Customer List Enabled</td>
                      <td>
                        <label class="switch switch-text switch-lg switch-pill switch-primary">
                          <input 
                            type="checkbox" 
                            class="switch-input" 
                            name="active_customer_check_enabled" 
                            :checked="vendor.active_customer_check_enabled"
                          >
                          <span
                            class="switch-label"
                            data-on="On"
                            data-off="Off"
                          />
                          <span class="switch-handle" />
                        </label>
                      </td>
                    </tr>
                  </table>                  

                </div>
              </div>

              <br>

              <hr>

              <br>

              <button
                type="submit"
                class="btn btn-primary"
                :disabled="vendor.deleted_at"
                :title="(vendor.deleted_at ? 'This vendor is inactive and cannot be edited.' : '')"
              >
                <i
                  class="fa fa-floppy-o"
                  aria-hidden="true"
                /> 
                {{ (vendor.deleted_at ? 'This vendor is inactive and cannot be edited.' : 'Submit') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </brand-vendor-nav>
</template>
<script>
import {TheMask} from 'vue-the-mask';
import BrandVendorNav from './BrandVendorNav';

export default {
    name: 'EditBrandVendor',
    components: {
        'brand-vendor-nav': BrandVendorNav,
        TheMask,
    },
    props: {
        brand: {
            type: Object,
            default: () => ({}),
        },
        vendor: {
            type: Object,
            default: () => ({}),
        },
        errors: {
            type: Array,
            default: () => [],
        },
        states: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: null,
        },
        httpPostPassword: {
            type: String,
            default: null,
        },  
    },
    data() {
        return {
            csrfToken: window.csrf_token, 
        };
    },
};
</script>
