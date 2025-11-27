<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: 'Pay Link', active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <div
        v-if="flashMessage"
        class="alert alert-success"
      >
        <span class="fa fa-check-circle" />
        <em>{{ flashMessage }}</em>
      </div>

      <form
        method="POST"
        :action="`/brands/${brand.id}/pay/update`"
        accept-charset="UTF-8"
        autocomplete="off"
      >
        <input
          name="_token"
          type="hidden"
          :value="csrfToken"
        >

        <div class="row">
          <div class="col-12">
            <textarea
              v-model="body"
              name="body"
              class="form-control bg-light"
              style="min-height: 500px;"
            />
          </div>
        </div>

        <div class="row mt-4">
          <div class="col-12">
            <button class="btn btn-lg btn-success pull-right">
              <span class="fa fa-save" /> Save
            </button>
          </div>
        </div>
      </form>
    </div>
  </layout>
</template>

<script>
import Layout from './Layout';

export default {
    name: 'Pay',
    components: {
        Layout,
    },
    props: {
        errors: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: '',
        },
        brand: {
            type: Object,
            default() {
                return {};
            },
        },
        tokens: {
            type: Array,
            default() {
                return [];
            },
        },
        results: {
            type: Object,
            default() {
                return {};
            },
        },
    },
    data() {
        return {
            csrfToken: window.csrf_token,
            processing: false,
            vendorOffices: null,
            editorShown: false,
            body: null,
        };
    },
    computed: {

    },
    mounted() {
        if (this.results && this.results.body !== null) {
            this.body = this.results.body;
        }
    },
    methods: {

    },
};
</script>
