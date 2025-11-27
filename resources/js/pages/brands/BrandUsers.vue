<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Brand Users', url: '/brands', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="tab-content mb-4">
        <div
          role="tabpanel"
          class="tab-pane active p-0"
        >
          <div class="animated fadeIn">
            <div class="card mb-0">
              <div class="card-header brands-header">
                <div
                  class="pull-left"
                  style="margin-top:0.75rem !important;"
                >
                  <i class="fa fa-th-large" /> Brand Users
                </div>

                <div class="form-group m-0 mt-1 mb-1 form-inline pull-right">
                  <select
                    v-model="brandId"
                    name="brands"
                    class="form-control w-30 d-inline pull-left mr-1 mt-1 mb-1"
                    @change="updateVendors"
                  >
                    <option :value="null">
                      All Brands
                    </option>
                    <option
                      v-for="brand in brands"
                      :key="brand.id"
                      :value="brand.id"
                    >
                      {{ brand.name }}
                    </option>
                  </select>
                  <select
                    v-model="vendorId"
                    name="vendors"
                    class="form-control w-20 d-inline pull-left mr-1 mt-1 mb-1"
                  >
                    <option :value="null">
                      All Vendors
                    </option>
                    <option
                      v-for="vendor in filteredVendors"
                      :key="vendor.id"
                      :value="vendor.id"
                    >
                      {{ vendor.name }}
                    </option>
                  </select>
                  <select
                    v-model="roleId"
                    name="role"
                    class="form-control w-15 d-inline pull-left mr-1 mt-1 mb-1"
                  >
                    <option :value="null">
                      All Roles
                    </option>
                    <option
                      v-for="(role, rolen) in roles"
                      :key="`key-${role.id}-${rolen}`"
                      :value="role.id"
                    >
                      {{ role.name }}
                    </option>
                  </select>
                  <!-- change placeholder from "Enter FirstN, LastN, VendorN or BrandN" to "Search" -->
                  <custom-input
                    :value="searchValue"
                    placeholder="Search"
                    name="search"
                    :style="{ marginRight: 0 }"
                    @onChange="updateSearchValue"
                    @onKeyUpEnter="searchData"
                  />
                  <button
                    class="btn btn-primary m-1"
                    style="height: 100%;"
                    @click="searchData"
                  >
                    <i class="fa fa-search" />
                  </button>
                  <a
                    v-if="showBrandLogin"
                    :href="`/brands/login?brand=${brandId}`"
                    target="_blank"
                    class="btn btn-primary m-1 ml-1"
                  ><i class="fa fa-sign-in" /> Brand Login</a>
                  <a
                    v-if="showBrandLogin"
                    :href="`/brands/${brandId}/edit`"
                    class="btn btn-secondary m-1 ml-1"
                  ><i class="fa fa-pencil" /> Edit Brand</a>
                </div>
              </div>
              <div class="card-body p-0">
                <div
                  v-if="hasFlashMessage"
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" /> <em>{{ flashMessage }}</em>
                </div>
                <custom-table
                  :headers="headers"
                  :data-grid="dataGrid"
                  :data-is-loaded="dataIsLoaded"
                  show-action-buttons
                  has-action-buttons
                  empty-table-message="No brand users were found."
                  @sortedByColumn="sortData"
                />
                <pagination
                  v-if="dataIsLoaded"
                  :active-page="activePage"
                  :number-pages="numberPages"
                  @onSelectPage="selectPage"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { status, statusLabel } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';
import { mapState } from 'vuex';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'BrandUsersList',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
        Breadcrumb,
    },
    props: {
        roles: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            showBrandLogin: false,
            brands: [],
            vendors: [],
            filteredVendors: [],
            sales_agents: [],
            headers: [{
                label: 'Status',
                key: 'statusLabel',
                serviceKey: 'status',
                width: '5%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Role',
                key: 'roleName',
                serviceKey: 'role_name',
                width: '5%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Rep ID',
                key: 'repId',
                serviceKey: 'tsr_id',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'First Name',
                key: 'firstName',
                serviceKey: 'first_name',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Last Name',
                key: 'lastName',
                serviceKey: 'last_name',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Brand Name',
                key: 'brandName',
                serviceKey: 'works_for',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Vendor Name',
                key: 'vendorName',
                serviceKey: 'employee_of',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Office Name',
                key: 'officeName',
                serviceKey: 'office_name',
                width: '15%',
                sorded: NO_SORTED,
                canSort: true,
            }],
            searchValue: this.getParams().search,
            brandId: this.getParams().brandId, 
            vendorId: this.getParams().vendorId,
            roleId: this.getParams().roleId,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        dataGrid() {
            return this.sales_agents;
        },
        ...mapState({
            session: 'session',
            hasFlashMessage: (state) => state.session && Object.keys(state.session).length && state.session.hasOwnProperty('flash_message') && state.session.flash_message,
            flashMessage: (state) => state.session.flash_message,
        }),
        filterParams() {
            return [
                this.brandId ? `&brand_id=${this.brandId}` : '',
                this.vendorId ? `&vendor_id=${this.vendorId}` : '',
                this.roleId ? `&role_id=${this.roleId}` : '',
            ].join('');
        },
    },
    mounted() {
        const params = this.getParams();
        const searchParam = this.searchValue ? `&search=${this.searchValue}` : '';
        const pageParam = params.page ? `&page=${params.page}` : '';

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === params.column);
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        if (params.brandId !== null && params.vendorId == null) {
            this.showBrandLogin = true;
        }

        axios.get('/list/brands?all=true')
            .then((response) => {
                this.brands = response.data;
            })
            .catch(console.log);

        axios.get('/list/getVendors')
            .then((response) => {
                const mapVendors = {};
                response.data.forEach((element) => {
                    if (typeof mapVendors[element.vendor_id] === 'undefined') {
                        mapVendors[element.vendor_id] = {...element, brands: [element.brand_id]};
                    }
                    else {
                        mapVendors[element.vendor_id].brands.push(element.brand_id);
                    }
                });
                
                Object.keys(mapVendors).forEach((key) => this.vendors.push(mapVendors[key]));

                this.updateVendors();
            })
            .catch(console.log);

        const url = `/list/sales_agents?${this.filterParams}${pageParam}${searchParam}${this.getSortParams()}`;
        axios.get(url)
            .then((response) => {
                this.sales_agents = response.data.data.map((sales_agent) => this.getDataObject(sales_agent));
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
            })
            .catch(console.log);
    },
    methods: {
        updateVendors() {
            if (this.brandId !== null) {
                this.showBrandLogin = true;
            }
            else {
                this.showBrandLogin = false;
            }
            this.filteredVendors = (this.brandId) ? this.vendors.filter((v) => v.brands.includes(this.brandId)) : this.vendors;
        },
        getDataObject(salesAgent) {
            const buttons = [];
            if (salesAgent.status === 1) {
                buttons.push({
                    type: 'edit',
                    target: 'new',
                    url: `/brand_users/login?id=${salesAgent.id}&dest=/users/profileEdit`,
                });
            }
            buttons.push({
                type: 'login',
                icon: 'sign-in',
                url: `/brand_users/login?id=${salesAgent.id}`,
            });
            buttons.push({
                type: 'custom',
                label: 'Audits',
                icon: 'cogs',
                classNames: 'btn btn-secondary',
                url: `/reports/search_user_info_from_audits?&userId=${salesAgent.id}`,
            });
            return {
                enabled: salesAgent.status === 1,
                id: salesAgent.id,
                createdDate: salesAgent.created_at,
                repId: salesAgent.tsr_id ? salesAgent.tsr_id : '--',
                firstName: salesAgent.first_name,
                lastName: salesAgent.last_name,
                vendorName: salesAgent.employee_of ? salesAgent.employee_of : '--',
                brandName: salesAgent.works_for ? salesAgent.works_for : '--',
                officeName: salesAgent.office_name ? salesAgent.office_name : '--',
                roleName: salesAgent.role_name,
                buttons,
                status: salesAgent.status,
                statusLabel: statusLabel[salesAgent.status],
            };
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/brand_users?column=${serviceKey}&direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}${this.filterParams}`;
        },
        searchData() {
            window.location.href = `/brand_users?${this.getSearchParams()}${this.getSortParams()}${this.filterParams}`;
        },
        selectPage(page) {
            window.location.href = `/brand_users?page=${page}${this.getSortParams()}${this.getSearchParams()}${this.filterParams}`;
        },
        getSearchParams() {
            return this.searchValue ? `&search=${this.searchValue}` : '';
        },
        getSortParams() {
            const params = this.getParams();
            return !!params.column && !!params.direction
                ? `&column=${params.column}&direction=${params.direction}` : '';
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
        getParams() {
            const url = new URL(window.location.href);
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page');
            const brandId = url.searchParams.get('brand_id');
            const vendorId = url.searchParams.get('vendor_id');
            const search = url.searchParams.get('search');
            const roleId = url.searchParams.get('role_id');
            return {
                column,
                direction,
                brandId,
                vendorId,
                page,
                search,
                roleId,
            };
        },
    },
};
</script>

<style lang="scss" scoped>
    .brands-header {
        clear: none;
        float: left;
        padding-top: 0;
        padding-bottom: 0;
    }
</style>
