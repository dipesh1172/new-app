<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Vendors', url: '/vendors', active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <brands-nav />
      <div class="tab-content mb-4">
        <div 
          role="tabpanel" 
          class="tab-pane active p-0"
        >
          <div class="row ">
            <div class="col-md-12">
              <div class="form-group form-inline pull-right mr-2 mt-2">
                <custom-input
                  :value="searchValue"
                  placeholder="Search"
                  name="search"
                  :style="{ marginRight: 0 }"
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
          
          <div
            v-if="hasFlashMessage"
            class="alert alert-success"
          >
            <span class="fa fa-check-circle" />
            <em>{{ flashMessage }}</em>
          </div>
          <custom-table
            :headers="headers"
            :data-grid="dataGrid"
            :data-is-loaded="dataIsLoaded"
            show-action-buttons
            has-action-buttons
            :no-bottom-padding="numberPages <= 1"
            empty-table-message="No vendors were found."
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
</template>

<script>
import { status, statusLabel } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';
import BrandsNav from '../clients/BrandsNav';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'Vendors',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
        Breadcrumb,
        BrandsNav,
    },
    props: {
        searchParameter: {
            type: String,
            default: '',
        },
        columnParameter: {
            type: String,
            default: '',
        },
        directionParameter: {
            type: String,
            default: '',
        },
        pageParameter: {
            type: String,
            default: '',
        },
        hasFlashMessage: {
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
            vendors: [],
            headers: [{
                label: 'Status',
                key: 'statusLabel',
                serviceKey: 'status',
                width: '35%',
                canSort: true,
            }, {
                label: 'Vendor Name',
                key: 'vendorName',
                serviceKey: 'name',
                width: '35%',
                canSort: true,
            }],
            searchValue: this.searchParameter,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        dataGrid() {
            return this.vendors;
        },
    },
    mounted() {
        const searchParam = this.searchParameter ? `&search=${this.searchParameter}` : '';
        const sortParams = !!this.columnParameter && !!this.directionParameter
            ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';

        if (!!this.columnParameter && !!this.directionParameter) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
            this.headers[sortHeaderIndex].sorted = this.directionParameter;
        }

        axios.get(`/list/vendors?${pageParam}${searchParam}${sortParams}`)
            .then((response) => {
                this.vendors = response.data.data.map((vendor) => this.getVendorObject(vendor));
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
            })
            .catch((error) => {
                console.log(error);
            });
    },
    methods: {
        getVendorObject(vendor) {
            return {
                id: vendor.id,
                vendorName: vendor.name,
                status: vendor.active,
                statusLabel: statusLabel[vendor.active],
                buttons: [{
                    type: 'login',
                    icon: 'sign-in',
                    url: `/brands/login?brand=${vendor.id}&vendor=true`,
                }],
            };
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/vendors?column=${serviceKey}&direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}`;
        },
        searchData() {
            window.location.href = `/vendors?${this.getSearchParams()}${this.getSortParams()}`;
        },
        selectPage(page) {
            window.location.href = `/vendors?page=${page}${this.getSortParams()}${this.getSearchParams()}`;
        },
        getSearchParams() {
            return this.searchValue ? `&search=${this.searchValue}` : '';
        },
        getSortParams() {
            return !!this.columnParameter && !!this.directionParameter
                ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
    },
};
</script>

<style lang="scss" scoped>
.vendors-header {
  clear: none;
  float: left;
}
</style>
