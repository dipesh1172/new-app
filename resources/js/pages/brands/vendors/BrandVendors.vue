<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: `${brand.name} Vendors`, active: true}
    ]"
  >
    <div class="card mb-0">
      <div class="card-body">
        <div
          v-if="flashMessage"
          class="alert alert-success"
        >
          <span class="fa fa-check-circle" />
          <em>{{ flashMessage }}</em>
        </div>
        <div class="row">
          <div class="col-md-12">
            <div class="form-group pull-right m-0 ml-1">
              <a
                :href="`/brands/${brand.id}/vendors/create`"
                class="btn btn-success m-0"
              ><i
                class="fa fa-plus"
                aria-hidden="true"
              /> Add Vendor</a> 
            </div>
            <div class="form-group form-inline pull-right">
              <custom-input
                :value="searchValue"
                :style="{ marginRight: 0 }"
                placeholder="Search"
                name="search"
                @onChange="updateSearchValue"
                @onKeyUpEnter="searchData"
              />
              <button 
                class="btn btn-primary" 
                @click="searchData"
              >
                <i class="fa fa-search" />
              </button>
            </div>
          </div>
        </div>
        <custom-table
          :headers="headers"
          :data-grid="vendors"
          :data-is-loaded="dataIsLoaded"
          :show-action-buttons="true"
          :total-records="totalRecords"
          :no-bottom-padding="numberPages <= 1"
          :has-action-buttons="tableHasActions"
          empty-table-message="No vendors were found."
        />
        <pagination
          v-if="dataIsLoaded"
          :active-page="activePage"
          :number-pages="numberPages"
          @onSelectPage="selectPage"
        />
      </div>
    </div>
  </layout>
</template>

<script>
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import Pagination from 'components/Pagination';
import { mapState } from 'vuex';
import Layout from '../edit/Layout';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'BrandVendors',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
        Layout,
    },
    props: {
        brand: {
            type: Object,
            default: () => {},
        },
        tableHasActions: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            totalRecords: 0,
            vendors: [],
            headers: [
                {
                    align: 'left',
                    label: 'Status',
                    key: 'status',
                    serviceKey: 'status',
                    width: '20%',
                    sorted: NO_SORTED,
                    canSort: false,
                },
                {
                    align: 'left',
                    label: 'Vendor Name',
                    key: 'name',
                    serviceKey: 'name',
                    width: '20%',
                    sorted: NO_SORTED,
                    canSort: false,
                },
                {
                    align: 'left',
                    label: 'Vendor Label',
                    key: 'vendor_label',
                    serviceKey: 'vendor_label',
                    width: '20%',
                    sorted: NO_SORTED,
                    canSort: false,
                },
                {
                    align: 'left',
                    label: 'Vendor ID',
                    key: 'grp_id',
                    serviceKey: 'grp_id',
                    width: '20%',
                    sorted: NO_SORTED,
                    canSort: false,
                },
            ],
            dataIsLoaded: false,
            searchValue: this.getParams().search,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        ...mapState({
            currentPortal: 'portal',
            readOnly: (state) => state.role_id === 4,
        }),
        pageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        filterParams() {
            return [
                this.searchValue ? `&search=${this.searchValue}` : '',
            ].join('');
        },
    },
    mounted() {
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        axios
            .get(
                `/brands/${this.brand.id}/list_vendors?${pageParam}${this.filterParams}`,
            )
            .then((response) => {
                const res = response.data;
                this.vendors = res.data.map((i) => {
                    i.status = i.deleted_at
                        ? '<span class="badge badge-danger">Inactive</span>'
                        : '<span class="badge badge-success">Active</span>';
                    i.buttons = [
                        {
                            type: 'edit',
                            url: `/brands/${this.brand.id}/vendor/${i.id}/editVendor`,
                            buttonSize: 'medium',
                            showButton: !this.readOnly,
                        },
                        {
                            type: 'delete',
                            counterName: 'Delete',
                            url: `/brands/${this.brand.id}/vendor/${i.id}/permDestroyVendor`,
                            messageAlert: 'Are you sure want to delete this vendor?',
                            buttonSize: 'medium',
                        },
                    ];
                    if (!i.deleted_at) {
                        i.buttons.push({
                            type: 'disable',
                            url: `/brands/${this.brand.id}/vendor/${i.id}/destroyVendor`,
                            buttonSize: 'medium',
                            showButton: !this.readOnly,
                        });
                    }
                    else {
                        i.buttons.push({
                            type: 'custom',
                            url: `/brands/${this.brand.id}/vendor/${i.id}/enableVendor`,
                            buttonSize: 'medium',
                            showButton: !this.readOnly,
                            classNames: 'btn-success',
                            counterName: 'Enable',
                            label: 'Enable',
                        });
                    }
                    return i;
                });
                this.dataIsLoaded = true;
                this.totalRecords = res.total;
                this.numberPages = res.last_page;
                this.activePage = res.current_page;
            })
            .catch(console.log);
    },

    methods: {
        searchData() {
            const filterParams = [this.searchValue ? `&search=${this.searchValue}` : ''].join('');
            window.location.href = `/brands/${this.brand.id}/vendors?${filterParams}`;
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
        selectPage(page) {
            window.location.href = `/brands/${this.brand.id}/vendors?page=${page}${
                this.filterParams
            }`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const page = url.searchParams.get('page') || '';
            const search = url.searchParams.get('search') || '';

            return {
                search,
                page,
            };
        },
    },
};
</script>

<style>
.breadcrumb {
  margin-bottom: 0.5rem;
}

.daily-calls-card {
  margin-top: 43px;
}

.breadcrumb-right {
  border-bottom: 1px solid #a4b7c1;
  background-color: #fff;
  padding: 0.5rem 1rem;
}
</style>
