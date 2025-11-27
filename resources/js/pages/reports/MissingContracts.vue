<template>
  <div id="main-app">
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Missing Contracts', url: '/reports/missing_contracts', active: true}
      ]"
    />

    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form
          :on-submit="filterData"
          :initial-values="initialSearchValues"
          :hide-search-box="true"
          :include-date-range="false"
          include-state
          include-brand
        >
          <div class="form-group pull-right m-0">
            <a
              :href="exportUrl"
              class="btn btn-primary m-0"
              :class="{'disabled': !contracts.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Export Data</a>
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card mt-4">
          <div class="card-header">
            <i class="fa fa-th-large" /> Report: Missing Contracts
          </div>
          <div class="row card-body">
            <div class="col-md-12">
              <custom-table
                :headers="headers"
                :data-grid="contracts"
                :data-is-loaded="dataIsLoaded"
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
  </div>
</template>

<script>
import { arraySort } from 'utils/arrayManipulation';
import { formArrayQueryParam } from 'utils/stringHelpers';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'MissingContracts',
    components: {
        CustomTable,
        SearchForm,
        Pagination,
        Breadcrumb,
    },
    props: {
        brands: {
            type: Array,
            default: () => [],
        },
        states: {
            type: Array,
            default: () => [],
        },
    },
    data() {
      const NO_SORTED = 'NO_SORTED';
        return {
             contracts: [],
            headers: (() => {
              const commonHeaderformissingcontacts = {
                            width: '10%',
                            canSort: true,
                            sorted: NO_SORTED,
              };
              return [
                    { label: 'Brand', key: 'brand_name', serviceKey: 'brand_name', type: 'string', ...commonHeaderformissingcontacts },
                    { label: 'Date', key: 'event_created_at', serviceKey: 'event_created_at', align: 'center', type: 'date', ...commonHeaderformissingcontacts },
                    { label: 'User ID', key: 'user_id', serviceKey: 'user_id', align: 'center', type: 'string', ...commonHeaderformissingcontacts },
                    { label: 'Confirmation Code', key: 'confirmation_code', serviceKey: 'confirmation_code', align: 'center', canSort: false, type: 'string', ...commonHeaderformissingcontacts },
                    { label: 'IP Address', key: 'ip_addr', serviceKey: 'ip_addr', align: 'right', type: 'string', ...commonHeaderformissingcontacts },
            ];
            })(),
            dataIsLoaded: false,
            displaySearchBar: false,
            activePage: 1,
            numberPages: 1,
            totalRecords: 0,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/list_missing_contracts?${this.filterParams}${this.sortParams}&csv=true`;
        },
        sortParams() {
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        filterParams() {
            const params = this.getParams();
            return [
                params.brand ? formArrayQueryParam('brand', params.brand) : '',
                params.state ? formArrayQueryParam('state', params.state) : '',
            ].join('');
        },
        initialSearchValues() {
            const params = this.getParams();
            return {
                brand: params.brand,
                state: params.state,
            };
        },
    },
    created() {
        this.$store.commit('setBrands', this.brands);
        this.$store.commit('setStates', this.states);
    },
    mounted() {
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        axios
            .get(
                `/reports/list_missing_contracts?${pageParam}${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;
                this.contracts = res.data.map((element) => {
                    element.confirmation_code = `<a href="/events/${element.event_id}">${element.confirmation_code}</a>`;
                    return element;
                });
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
                this.totalRecords = res.total;
            })
            .catch(console.log);
    },
    methods: {
        filterData({ brand, state }) {
            const filters = [
                brand ? formArrayQueryParam('brand', brand) : '',
                state ? formArrayQueryParam('state', state) : '',
            ].join('');
            window.location.href = `/reports/missing_contracts?${filters}`;
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.contracts = arraySort(this.headers, this.contracts, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        selectPage(page) {
            window.location.href = `/reports/missing_contracts?page=${page}${this.sortParams}${this.filterParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const brand = url.searchParams.getAll('brand[]');
            const state = url.searchParams.getAll('state[]');
            const page = url.searchParams.get('page');
            const column = url.searchParams.get('column');
            const direction = url.searchParams.get('direction');
            return {
                brand,
                column,
                direction,
                page,
                state,
            };
        },
    },
};
</script>
