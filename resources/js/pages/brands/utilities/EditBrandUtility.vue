<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Brands', url: '/brands'},
        {name: brand.name, url: `/brands/${brand.id}/edit`},
        {name: `${brand.name} Utilities`, url: `/brands/${brand.id}/utilities`},
        {name: utility.name, active: true}
      ]"
    />
    <div class="container-fluid">
      <div class="card mt-5">
        <div class="card-header">
          <em class="fa fa-th-large" /> Edit <strong>{{ utility.name }}</strong> for {{ brand.name }}
        </div>
        <div class="card-body p-1">
          <form
            method="POST"
            :action="`/brands/${brand.id}/utilities/${utility.id}/updateUtility`"
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
                v-if="errors.length > 0"
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

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="utility_label">Utility Label</label>
                    <input
                      id="utility_label"
                      class="form-control form-control-lg"
                      placeholder="Enter a Utility Label"
                      name="utility_label"
                      type="text"
                      :value="utility.utility_label"
                    >
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="utility_external_id">Utility External ID (optional)</label>
                    <input
                      id="utility_external_id"
                      class="form-control form-control-lg"
                      placeholder="Enter a Utility External ID (optional)"
                      name="utility_external_id"
                      type="text"
                      :value="utility.utility_external_id"
                    >
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="service_territory">Territory Label (optional)</label>
                    <input
                      id="service_territory"
                      class="form-control form-control-lg"
                      placeholder="Enter a Service Territory (optional)"
                      name="service_territory"
                      type="text"
                      :value="utility.service_territory"
                    >
                  </div>
                </div>
              </div>

              <table class="table table-shaded table-bordered">
                <tr>
                  <th>
                    Type
                  </th>
                  <th>
                    LDC Code
                  </th>
                  <th>
                    External ID (optional)
                  </th>
                </tr>
                <tr v-if="!utility.supported_fuels.length">
                  <td colspan="4">
                    No results were found.
                  </td>
                </tr>
                <tr
                  v-for="usf in utility.supported_fuels"
                  v-else
                  :key="usf.id"
                >
                  <td>
                    {{ usf.utility_fuel_type.utility_type }}
                  </td>
                  <td>
                    <input
                      type="hidden"
                      :name="`supported_fuel[${usf.utility_fuel_type.id}]`"
                      :value="usf.id"
                    >

                    <input
                      type="text"
                      :name="`ldc_code[${usf.utility_fuel_type.id}]`"
                      class="form-control form-control-lg"
                      :placeholder="`Input LDC Code for ${usf.utility_fuel_type.utility_type}`"
                      :value="(usf.brand_utility_supported_fuels['ldc_code']) ? usf.brand_utility_supported_fuels['ldc_code'] : ''"
                    >
                  </td>
                  <td>
                    <input
                      type="text"
                      :name="`external_id[${usf.utility_fuel_type.id}]`"
                      class="form-control form-control-lg"
                      :placeholder="`Input External ID for ${usf.utility_fuel_type.utility_type}`"
                      :value="(usf.brand_utility_supported_fuels['external_id']) ? usf.brand_utility_supported_fuels['external_id'] : ''"
                    >
                  </td>
                </tr>
              </table>

              <button
                type="submit"
                class="btn btn-primary btn-lg pull-right mb-3"
              >
                <i
                  class="fa fa-floppy-o"
                  aria-hidden="true"
                />
                Save
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'EditBrandUtility',
    components: {
        Breadcrumb,
    },
    props: {
        errors: {
            type: Array,
            default: function() {
                return [];
            },
        },
        brand: {
            type: Object,
            default: () => {},
        },
        utility: {
            type: Object,
            default: () => {},
        },
    },
    data() {
        return {
            csrfToken: window.csrf_token,
        };
    },
};
</script>
