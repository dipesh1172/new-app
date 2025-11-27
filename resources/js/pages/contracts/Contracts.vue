<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Contracts', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <nav
          class="nav nav-tabs"
          role="tablist"
        >
          <a
            :class="{ 'nav-item': true, 'nav-link': true, 'active': url === '/contracts' }"
            href="/contracts"
          >Summary Filters</a>
          <a
            :class="{ 'nav-item': true, 'nav-link': true, 'active': url === '/contracts/tcs' }"
            href="/contracts/tcs"
          >Terms & Conditions</a>
        </nav>
        <div class="card tab-content">
          <div class="card-body">
            <div class="row page-buttons mb-2">
              <div class="col-md-12">
                <div class=" form-group form-inline pull-right">
                  <a
                    href="/contracts/add"
                    class="btn btn-lg btn-success"
                  >
                    <i
                      class="fa fa-plus"
                      aria-hidden="true"
                    /> Add a Summary Filter
                  </a>
                </div>
              </div>
            </div>

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
              empty-table-message="No contracts were found."
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
</template>

<script>
import { status, statusLabel } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'Contracts',
    components: {
        CustomTable,
        Pagination,
        Breadcrumb,
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
            url: null,
            object: [],
            headers: [{
                label: 'Brand',
                key: 'brand_name',
                serviceKey: 'brand_name',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'State',
                key: 'state_abbrev',
                serviceKey: 'state_abbrev',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Channel(s)',
                key: 'channel',
                serviceKey: 'channel',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Market(s)',
                key: 'market',
                serviceKey: 'market',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Commodities(s)',
                key: 'commodities',
                serviceKey: 'commodities',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Rate Type',
                key: 'rate_type',
                serviceKey: 'rate_type',
                width: '15%',
                sorted: NO_SORTED,
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
            return this.object;
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

        axios.get(`/contracts/list?${pageParam}${searchParam}${sortParams}`)
            .then((response) => {
                this.object = response.data.data.map((object) => this.getObject(object));
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
            })
            .catch((error) => {
                console.log(error);
            });

        this.url = window.location.pathname;
    },
    methods: {
        getObject(object) {
            return {
                contract_name: object.contract_name,
                brand_name: object.brand_name,
                state_abbrev: object.state_abbrev,
                channel: object.channel,
                market: object.market,
                language: object.language,
                commodities: object.commodities,
                rate_type: object.rate_type ? object.rate_type : '--',
                terms_and_conditions_name: object.terms_and_conditions_name ? object.terms_and_conditions_name : '--',
                buttons: [{
                    type: 'edit',
                    url: `/contracts/${object.id}/show`,
                }],
            };
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/contracts?column=${serviceKey}&direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}`;
        },
        searchData() {
            window.location.href = `/contracts?${this.getSearchParams()}${this.getSortParams()}`;
        },
        selectPage(page) {
            window.location.href = `/contracts?page=${page}${this.getSortParams()}${this.getSearchParams()}`;
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

</style>
