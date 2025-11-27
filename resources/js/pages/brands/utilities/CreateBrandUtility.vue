<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Brands', url: '/brands'},
        {name: brand.name, url: `/brands/${brand.id}/edit`},
        {name: `${brand.name} Utilities`, url: `/brands/${brand.id}/utilities`},
        {name: 'Add Utility', active: true}
      ]"
    />

    <div class="container-fluid">
      <div class="card mt-5">
        <div class="card-header">
          <span class="fa fa-th-large" /> Add a Utility to {{ brand.name }}
        </div>

        <div class="card-body">
          <form
            method="POST"
            :action="`/brands/${brand.id}/utilities/storeUtility`"
            accept-charset="UTF-8"
            autocomplete="off"
          >
            <input
              name="_token"
              type="hidden"
              :value="csrfToken"
            >
            <div class="row">
              <div
                v-show="errors.length"
                class="alert alert-danger col-md-12"
                >
                <li
                  v-for="(error, index) in errors"
                  :key="index"
                >
                  {{ error }}
                </li>
              </div>
              <div class="col-8">
                <div class="form-group">
                  <select
                    id="utility"
                    name="utility"
                    class="form-control form-control-lg"
                  >
                    <option value="">
                      Select a Provider
                    </option>
                    <optgroup
                      v-for="state in Object.keys(allUtilities)"
                      :key="state"
                      :label="state"
                    >
                      <option
                        v-for="utility in allUtilities[state]"
                        :key="utility.id"
                        :value="utility.id"
                      >
                        {{ utility['name'] }} ({{ utility['ldc_code'] }})
                      </option>
                    </optgroup>
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-4">
                <div class="form-group">
                  <input
                    type="text"
                    name="utility_label_add"
                    class="form-control form-control-lg"
                    placeholder="Utility Label"
                  >
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="utility_external_id">Utility External ID (optional)</label>
                  <input
                    id="utility_external_id"
                    class="form-control form-control-lg"
                    placeholder="Enter a Utility External ID (optional)"
                    name="utility_external_id"
                    type="text"
                  >
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="commodity">Commodity (optional)</label>
                  <input
                    id="commodity"
                    class="form-control form-control-lg"
                    placeholder="Enter a Commodity (optional)"
                    name="commodity"
                    type="text"
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
              Save
            </button>
          </form>
        </div>
      </div>
    </div> 
  </div>
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'CreateBrandUtility',
    components: {
        Breadcrumb,
    },
    props: {
        allUtilities: {
            type: Object,
            default: () => ({}),
        },
        brand: {
            type: Object,
            default: () => ({}),
        },
        errors: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            csrfToken: window.csrf_token,
        };
    },
};
</script>
