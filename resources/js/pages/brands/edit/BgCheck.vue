<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: `Background Checks for Brand ${brand.name}`, active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <div class="row">
        <div
          v-if="flashMessage"
          class="alert alert-success"
        >
          <span class="fa fa-check-circle" />
          <em> {{ flashMessage }}</em>
        </div>
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
          <form
            method="POST"
            :action="`/brands/${brand.id}/store_bgchk`"
            accept-charset="UTF-8"
            autocomplete="off"
          >
            <input
              name="_token"
              type="hidden"
              :value="cvsrfToken"
            > 
            <div class="form-group">
              <label for="bgchk_provider_id">Provider</label>
              <select
                id="bgchk_provider_id"
                name="bgchk_provider_id"
                class="form-control form-control-lg"
              >
                <option value="">
                  Please select a provider
                </option>
                <option
                  v-for="provider in providers"
                  :key="provider.id"
                  :value="provider.id"
                  :selected="provider.id == creds.bgchk_provider_id"
                >
                  {{ provider.provider }}
                </option>
              </select>    
            </div>
            <div class="form-group">
              <label for="details">Details</label>
              <input
                id="details"
                :placeholder="brand.name"
                class="form-control form-control-lg"
                name="details"
                type="text"
                :value="creds.details || ''"
              >
            </div>
            <div class="form-group">
              <label for="package">Package</label>
              <input
                id="package"
                placeholder="Enter a Package"
                class="form-control form-control-lg"
                name="package"
                type="text"
                :value="creds.package || ''"
              >
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
  </layout>
</template>
<script>
import Layout from './Layout';

export default {
    name: 'BackgroundChecks',
    components: {
        Layout,
    },
    props: {
        brand: {
            type: Object,
            default: () => ({}),
        },
        creds: {
            type: Object,
            default: () => ({}),
        },
        flashMessage: {
            type: String,
            default: '',
        },
        providers: {
            type: Array,
            default: () => [],
        },
        errors: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            cvsrfToken: window.csrf_token,
        };
    },
};
</script>
