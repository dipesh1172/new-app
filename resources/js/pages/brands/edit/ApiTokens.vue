<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: 'API Access', active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active pt-0 pb-0"
    >
      <div
        v-if="!editorShown"
        class="row"
      >
        <table class="table table-striped pb-0 mb-0">
          <thead>
            <tr>
              <th
                colspan="5"
              >
                <button
                  type="button"
                  class="btn btn-info pull-right"
                  @click="showEditor"
                >
                  <i class="fa fa-plus" /> Add Access Token
                </button>
              </th>
            </tr>
            <tr>
              <th>Issued</th>
              <th>Label</th>
              <th>Vendor</th>
              <th>Office</th>
              <th>&nbsp;</th>
            </tr>
          </thead>
          <tbody v-if="tokens.length > 0">
            <tr
              v-for="(token, i) in tokens"
              :key="i"
            >
              <td>{{ token.created_at }}</td>
              <td>{{ token.label }}</td>
              <td>
                <template v-if="token.vendor_id == null">
                  All Vendors
                </template>
                <template v-else>
                  {{ getVendor(token.vendor_id).vendor_label }}
                </template>
              </td>
              <td>
                <template v-if="token.office_id == null">
                  All Offices
                </template>
                <template v-else>
                  {{ getVendorOffice(token.vendor_id, token.office_id).label }}
                </template>
              </td>
              <td>
                <button
                  type="button"
                  class="btn btn-warning"
                  @click="viewToken(i)"
                >
                  <i class="fa fa-eye" /> View
                </button>
                <button
                  type="button"
                  class="btn btn-danger"
                  @click="revokeToken(token.id)"
                >
                  <i class="fa fa-trash" /> Revoke
                </button>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td
                colspan="5"
                class="text-center"
              >
                No Access Tokens exist
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <template v-else>
        <div class="row p-3">
          <div class="col-6">
            <label>Label</label>
            <input
              v-model="ntoken_label"
              class="form-control"
              type="text"
              maxlength="30"
            >
          </div>
          <div class="col-3">
            <label>Vendor?</label>
            <select
              v-model="ntoken_vendor"
              class="form-control"
            >
              <option :value="null">
                All Vendors
              </option>
              <option
                v-for="(vendor, vi) in vendors"
                :key="vi"
                :value="vendor.id"
              >
                {{ vendor.vendor_label }}
              </option>
            </select>
          </div>
          <div
            v-if="ntoken_vendor !== null"
            class="col-3"
          >
            <label>Office?</label>
            <select
              v-model="ntoken_office"
              class="form-control"
            >
              <option :value="null">
                All Offices
              </option>
              <option
                v-for="(office, oi) in vendorOffices[ntoken_vendor]"
                :key="oi"
                :value="office.id"
              >
                {{ office.label }}
              </option>
            </select>
          </div>
        </div>
        <div class="row p-3">
          <div class="col-12">
            <button
              class="btn btn-danger pull-left"
              @click="cancelEdit"
            >
              <i
                class="fa fa-ban"
                aria-hidden="true"
              />
              Cancel
            </button>
            <button
              class="btn btn-success pull-right"
              :disabled="processing"
              @click="submitNewToken"
            >
              <i
                v-show="processing"
                class="fa fa-spinner fa-spin"
              />
              <i
                class="fa fa-plus"
                aria-hidden="true"
              />
              Add Token
            </button>
          </div>
        </div>
      </template>
    </div>
    <div
      ref="modal"
      class="modal"
      tabindex="-1"
      role="dialog"
    >
      <div
        class="modal-dialog"
        role="document"
      >
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ selectedToken !== null ? selectedToken.label : '' }}
            </h5>
            <button
              type="button"
              class="close"
              data-dismiss="modal"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div
            v-if="selectedToken !== null"
            class="modal-body"
          >
            <div class="row">
              <div class="col-6">
                <label>Vendor</label>
                <input
                  class="form-control"
                  type="text"
                  readonly
                  :value="selectedToken.vendor_id == null ? 'All Vendors' : getVendor(selectedToken.vendor_id).vendor_label"
                >
              </div>
              <div class="col-6">
                <label>Office</label>
                <input
                  class="form-control"
                  type="text"
                  readonly
                  :value="selectedToken.office_id == null ? 'All Offices' : selectedOfficeName"
                >
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-12">
                <label>API Secret</label>
                <input
                  class="form-control"
                  type="text"
                  readonly
                  :value="selectedToken.secret"
                >
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-12">
                <label>Application Token</label>
                <input
                  class="form-control"
                  type="text"
                  readonly
                  :value="selectedToken.app_token"
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </layout>
</template>

<script>
import Layout from './Layout';

export default {
    name: 'ApiTokenMgmt',
    components: {
        Layout,
    },
    props: {
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
        vendors: {
            type: Array,
            default() {
                return [];
            },
        },
        view: {
            type: Number,
            default: -1,
        },
    },
    data() {
        return {
            processing: false,
            vendorOffices: null,
            editorShown: false,
            ntoken_label: null,
            ntoken_vendor: null,
            ntoken_office: null,
            selectedToken: null,
        };
    },
    computed: {
        selectedOfficeName() {
            return this.selectedToken !== null ? this.getVendorOffice(this.selectedToken.vendor_id, this.selectedToken.office_id).label : 'invalid';
        },
    },
    mounted() {
        for (let i = 0, len = this.vendors.length; i < len; i += 1) {
            this.doGetOffice(this.vendors[i].id);
        }
        $(this.$refs.modal).on('hide.bs.modal', () => {
            this.selectedToken = null;
        });
        if (this.view > -1) {
            if (this.view < this.tokens.length) {
                this.viewToken(this.view);
            }
            else {
                window.location = `/brands/${this.brand.id}/api_tokens`;
            }
        }
    },
    methods: {
        getVendor(id) {
            for (let i = 0, len = this.vendors.length; i < len; i += 1) {
                if (this.vendors[i].id === id) {
                    return this.vendors[i];
                }
            }
            return {
                vendor_label: 'Invalid Vendor Id',
            };
        },
        getVendorOffice(vendor_id, office_id) {
            if (
                this.vendorOffices == null
                || this.vendorOffices[vendor_id] == undefined
            ) {
                return {
                    label: 'invalid',
                };
            }
            if (this.vendorOffices !== null) {
                for (
                    let i = 0, len = this.vendorOffices[vendor_id].length;
                    i < len;
                    i += 1
                ) {
                    if (this.vendorOffices[vendor_id][i].id == office_id) {
                        return this.vendorOffices[vendor_id][i];
                    }
                }
            }

            return {
                label: 'Invalid Office Id',
            };
        },
        async getVendorOfficeName(vendor_id, office_id) {
            const office = await this.getVendorOffice(vendor_id, office_id);
            console.log('office.label', office);
            return office.label;
        },
        async doGetOffice(vendor_id) {
            return axios
                .get(`/brand/api/vendor/${vendor_id}/get/offices`)
                .then((response) => {
                    const offices = response.data;
                    if (this.vendorOffices == null) {
                        this.vendorOffices = {};
                        this.vendorOffices[vendor_id] = offices;
                    }
                    else {
                        this.vendorOffices[vendor_id] = offices;
                    }
                })
                .catch((e) => {
                    window.alert(`There was an error getting the list of offices: ${e}`);
                });
        },
        revokeToken(token_id) {
            if (
                window.confirm(
                    'Are you sure you want to permanently revoke this token, this cannot be undone.',
                )
            ) {
                // do revokation
                axios.delete(`/brands/api/token/${token_id}`, {
                    _token: window.csrf_token,
                }).then((response) => {
                    if (response.data.error) {
                        window.alert(`Cannot revoke token: ${response.data.message}`);
                    }
                    else {
                        window.alert('Token Revoked');
                        window.location = `/brands/${this.brand.id}/api_tokens`;
                    }
                }).catch((e) => {
                    window.alert(`Cannot revoke token: ${e}`);
                });
            }
            return false;
        },
        viewToken(i) {
            this.selectedToken = this.tokens[i];
            
            $(this.$refs.modal).modal('show');
        },
        showEditor() {
            this.editorShown = true;
        },
        cancelEdit() {
            this.editorShown = false;
            this.ntoken_label = null;
            this.ntoken_vendor = null;
            this.ntoken_office = null;
        },
        submitNewToken() {
            this.processing = true;
            axios
                .post(`/brands/${this.brand.id}/api_tokens`, {
                    _token: window.csrf_token,
                    office: this.ntoken_office,
                    label: this.ntoken_label,
                    vendor: this.ntoken_vendor,
                })
                .then((response) => {
                    if ('error' in response.data && response.data.error !== false) {
                        throw new Error(response.data.error);
                    }
                    window.location = `/brands/${this.brand.id}/api_tokens?view=${this.tokens.length}`;
                })
                .catch((e) => {
                    if ('response' in e && e.response.status == 422) {
                        let errorText = e.response.data.message;
                        const keys = Object.keys(e.response.data.errors);
                        for (let i = 0, len = keys.length; i < len; i += 1) {
                            errorText += `\n${e.response.data.errors[keys[i]].join('\n')}`;
                        }
                        window.alert(errorText);
                    }
                    else {
                        window.alert(`Could not create token: ${e}`);
                    }
                }).finally(() => {
                    this.processing = false;
                });
        },
    },
};
</script>
