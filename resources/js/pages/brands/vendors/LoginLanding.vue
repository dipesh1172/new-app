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
      class="tab-pane active p-0"
    >
      <div class="card mb-0">
        <div class="card-header">
          <i class="fa fa-th-large" /> Edit Login Landing page for {{ vendor.name }}
        </div>
        <div class="card-body">
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

            <div
              v-if="slug"
              class="row"
            >
              <div class="col-md-8">
                <div class="form-group">
                  <label for="portal">Login Landing URL</label><br>
                  <h3>
                    <a
                      :href="`${envTpvClients}/landing/login/${slug}`"
                      target="_blank"
                    >
                      {{ envTpvClients }}/landing/login/{{ slug }}
                    </a>
                  </h3>
                </div>
              </div>
              <div
                class="col-12"
              >
                <form
                  method="POST"
                  :action="`/brands/${brand.id}/vendor/${vendor.id}/addLandingIP`"
                  accept-charset="UTF-8"
                  autocomplete="off"
                >
                  <input
                    name="_token"
                    type="hidden"
                    :value="csrfToken"
                  >
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="ip_addr">IP Address</label>
                        <input
                          type="text"
                          name="ip_addr"
                          class="form-control form-control-lg"
                        >
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="ip_addr_comment">Description</label>
                        <input
                          type="text"
                          name="ip_addr_comment"
                          class="form-control form-control-lg"
                        >
                      </div>
                    </div>
                    <div class="col-md-4">
                      <br>
                      <button
                        type="submit"
                        class="btn btn-primary btn-lg mt-2"
                      >
                        <i
                          class="fa fa-plus"
                          aria-hidden="true"
                        />
                        Add IP
                      </button>
                    </div>
                  </div>
                </form>
              </div>
              <div class="table-responsive">
                <table class="table table-striped table-bordered">
                  <thead>
                    <tr>
                      <th>IP Address</th>
                      <th>Description</th>
                      <th width="10%">
                        &nbsp;
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-if="ips.length">
                      <tr
                        v-for="(ip, index) in ips"
                        :key="index"
                      >
                        <td>{{ ip.ip }}</td>
                        <td>{{ ip.description }}</td>
                        <td>
                          <form
                            onsubmit="return confirm('Are you sure you want to remove this IP Address?');"
                            method="POST"
                            :action="`/brands/${brand.id}/vendor/${vendor.id}/landing/${ip.id}/removeIP/${ip.ip}`"
                          >
                            <input
                              type="hidden"
                              name="_method"
                              value="DELETE"
                            > <input
                              type="hidden"
                              name="_token"
                              :value="csrfToken"
                            >
                            <input
                              type="submit"
                              class="btn btn-danger"
                              value="Delete"
                            >
                          </form>
                        </td>
                      </tr>
                    </template>
                    <template v-else>
                      <tr>
                        <td
                          colspan="3"
                          class="text-center"
                        >
                          No IP Address(es) found.
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else>
              No login landing portal is currently configured.<br><br>
            </div>
          </div>
        </div>
      </div>
    </div>
  </brand-vendor-nav>
</template>
<script>
import BrandVendorNav from './BrandVendorNav';

export default {
    name: 'LoginLanding',
    components: {
        'brand-vendor-nav': BrandVendorNav,
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
        ips: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: null,
        },
        envTpvClients: {
            type: String,
            default: null,
        },
        slug: {
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
