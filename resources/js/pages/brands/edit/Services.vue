<template>
  <div>
    <layout
      :brand="brand"
      :breadcrumb="[
        {name: 'Home', url: '/'},
        {name: 'Brands', url: '/brands'},
        {name: brand.name, url: `/brands/${brand.id}/edit`},
        {name: 'Services', active: true}
      ]"
    >
      <div
        role="tabpanel"
        class="tab-pane active p-0 mb-0"
      >
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
        <form
          method="POST"
          action="/brands/saveService"
        >
          <div
            id="serviceCreator"
            class="modal fade"
            tabindex="-1"
            role="dialog"
          >
            <div
              class="modal-dialog"
              role="document"
            >
              <div class="modal-content">
                <div class="modal-header">
                  <h5
                    class="modal-title"
                  >
                    Add Brand Service
                  </h5>
                </div>
                <div class="modal-body">
                  <input
                    type="hidden"
                    name="_token"
                    :value="csrf"
                  >
                  <input
                    type="hidden"
                    name="brand"
                    :value="brand.id"
                  >
                  <div class="form-group">
                    <label for="brand_service_type_id">
                      Service Type
                    </label>
                    <select
                      id="brand_service_type_id"
                      class="form-control"
                      name="brand_service_type_id"
                    >
                      <option>
                        Please choose a service type
                      </option>
                      <option
                        v-for="(serviceType, sti) in serviceTypes"
                        :key="sti"
                        :value="serviceType.id"
                      >
                        {{ serviceType.name }}
                      </option>
                    </select>
                  </div>
                  <!-- <div class="form-group">
                    <label for="rate_card">
                      Rate card
                    </label>
                    <textarea
                      id="rate_card"
                      class="form-control"
                      name="rate_card"
                      rows="5"
                    />
                  </div> -->
                </div>
                <div class="modal-footer">
                  <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal"
                  >
                    <i
                      class="fa fa-times"
                      aria-hidden="true"
                    />
                    Cancel
                  </button>
                  <button
                    type="submit"
                    class="btn btn-primary"
                  >
                    <i
                      class="fa fa-floppy-o"
                      aria-hidden="true"
                    />
                    Save
                  </button>
                </div>
              </div>
            </div>
          </div>
        </form>
        <form
          ref="disable"
          method="POST"
          action="/brands/removeService"
        >
          <input
            type="hidden"
            name="_method"
            value="DELETE"
          >
          <input
            type="hidden"
            name="_token"
            :value="csrf"
          >
          <input
            type="hidden"
            name="brand"
            :value="brand.id"
          >
          <input
            type="hidden"
            name="id"
            :value="disabling"
          >
        </form>
        <div class="card mb-0">
          <div class="card-body p-0">
            <button
              type="button"
              class="btn btn-primary m-2 pull-right"
              data-toggle="modal"
              data-target="#serviceCreator"
            >
              <i class="fa fa-plus" /> Add Service
            </button>
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>
                    Service
                  </th>
                  <!-- <th>
                    Rate Card
                  </th> -->
                  <th>Tools</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="services.length == 0">
                  <td
                    colspan="3"
                    class="text-center"
                  >
                    No Services Enabled
                  </td>
                </tr>
                <tr
                  v-for="(service, i) in services"
                  :key="i"
                >
                  <td>{{ service.brand_service_type.name }}</td>
                  <!-- <td>
                    <form
                      method="POST"
                      action="/brands/saveService"
                    >
                      <input
                        type="hidden"
                        name="_token"
                        :value="csrf"
                      >
                      <input
                        type="hidden"
                        name="id"
                        :value="service.id"
                      >
                      <input
                        type="hidden"
                        name="brand"
                        :value="brand.id"
                      >
                      <input
                        type="hidden"
                        name="brand_service_type_id"
                        :value="service.brand_service_type_id"
                      >
                      <textarea
                        class="form-control"
                        :readonly="!(editing == i)"
                        rows="3"
                        name="rate_card"
                      >{{ service.rate_card }}</textarea>
                      <button
                        v-if="editing == i"
                        type="submit"
                        class="btn btn-primary"
                      >
                        <i class="fa fa-save" /> Save
                      </button>
                    </form>
                  </td> -->
                  <td>
                    <button
                      type="button"
                      class="btn btn-danger"
                      @click="doDisable(i)"
                    >
                      <i
                        class="fa fa-trash"
                        aria-hidden="true"
                      />
                      Disable
                    </button>
                    <!-- <button
                      v-if="editing == null"
                      type="button"
                      class="btn btn-warning"
                      @click="editRate(i)"
                    >
                      <i
                        class="fa fa-pencil"
                        aria-hidden="true"
                      />
                      Edit Rate Card
                    </button>
                    <button
                      v-if="editing == i"
                      type="button"
                      class="btn btn-warning"
                      @click="editRate(null)"
                    >
                      <i
                        class="fa fa-ban"
                        aria-hidden="true"
                      />
                      Cancel Editing
                    </button> -->
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </layout>
  </div>
</template>

<script>
import Layout from './Layout';

export default {
    name: 'BrandServiceConfig',
    components: {
        Layout,
    },
    props: {
        brand: {
            type: Object,
            required: true,
        },
        services: {
            type: Array,
            default() {
                return [];
            },
        },
        serviceTypes: {
            type: Array,
            default() {
                return [];
            },
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
            editing: null,
            disabling: null,
        };
    },
    computed: {
        csrf() {
            return window.csrf_token;
        },
    },
    methods: {
        editRate(i) {
            this.editing = i;
        },
        doDisable(i) {
            if (window.confirm('Are you sure you wish to disable this service for this brand?')) {
                this.disabling = this.services[i].id;
                this.$nextTick().then(() => {
                    this.$refs.disable.submit();
                }).catch((e) => console.log(e));
            }
        },
    },
};
</script>
