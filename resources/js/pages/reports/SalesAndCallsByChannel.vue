<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Sales and Calls by Channel', url: '/reports/sales_by_channel', active: true}
      ]"
    />

    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form
          :on-submit="searchData"
          :initial-values="initialSearchValues"
          :hide-search-box="true"
          include-brand
          include-date-range
          include-market
          include-channel
          include-language
          include-commodity
          include-state
        >
          <div class="form-group pull-right m-0">
            <a
              :href="exportSalesUrl"
              class="btn btn-primary m-0"
              :class="{'disabled': !sales.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Export Sales</a>
            <a
              :href="exportCallsUrl"
              class="btn btn-dark m-0 ml-1"
              :class="{'disabled': !calls.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Export Calls</a>
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <ul class="nav nav-tabs mt-4">
          <li
            class="nav-item"
            @click="activeTable = 'sales'"
          >
            <a
              class="nav-link"
              href="#"
              :class="{'active': activeTable == 'sales'}"
            >
              <i class="fa fa-usd" /> Sales
            </a>
          </li>
          <li
            class="nav-item"
            @click="activeTable = 'calls'"
          >
            <a
              class="nav-link"
              href="#"
              :class="{'active': activeTable == 'calls'}"
            >
              <i
                class="fa fa-phone"
                aria-hidden="true"
              /> Calls
            </a>
          </li>
        </ul>
        <div class="card card-body">
          <div class="card-header">
            <i class="fa fa-th-large" /> Sales and Calls by Channel
          </div>
          <div class="row card-body">
            <div class="col-md-12">
              <custom-table
                :headers="headers"
                :data-grid="dataGrid"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                :empty-table-message="`No ${activeTable} were found.`"
                @sortedByColumn="sortData"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import { formArrayQueryParam } from 'utils/stringHelpers';
import { replaceFilterBar } from 'utils/domManipulation';
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'SalesAndCallsByChannel',
    components: {
        CustomTable,
        SearchForm,
        Breadcrumb,
    },
    props: {
        brands: {
            type: Array,
            default: () => [],
        },
        languages: {
            type: Array,
            default: () => [],
        },
        commodities: {
            type: Array,
            default: () => [],
        },
        states: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            sales: [],
            calls: [],
            activeTable: 'sales',
            channels: [
                {
                    id: 1,
                    name: 'DTD',
                },
                {
                    id: 2,
                    name: 'TM',
                },
                {
                    id: 3,
                    name: 'Retail',
                },
                {
                    id: 4,
                    name: 'Care',
                },
            ],
            markets: [
                {
                    id: 1,
                    name: 'Residential',
                },
                {
                    id: 2,
                    name: 'Commercial',
                },
            ],
            headers: (() => {
                const commonHeaderforsalesandcalls = {
                    align: 'center',
                    width: '12%',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                };

            return [
                    {align: 'left',label: 'Brand',key: 'brand',serviceKey: 'brand',width: '40%',canSort: true,type: 'string',sorted: NO_SORTED,},
                    { label: 'DTD', key: 'DTD', serviceKey: 'DTD', ...commonHeaderforsalesandcalls },
                    { label: 'TM', key: 'TM', serviceKey: 'TM', ...commonHeaderforsalesandcalls },
                    { label: 'Retail', key: 'Retail', serviceKey: 'Retail', ...commonHeaderforsalesandcalls },
                    { label: 'Care', key: 'Care', serviceKey: 'Care', ...commonHeaderforsalesandcalls },
                    { label: 'Total', key: 'Total', serviceKey: 'Total', ...commonHeaderforsalesandcalls },
            ];
                })(),
            dataIsLoaded: false,
            column: this.getParams().column,
            direction: this.getParams().direction,
            dataSC: {
                sales: {
                    totalRecords: 0,
                },
                calls: {
                    totalRecords: 0,
                },
            },
        };
    },
    computed: {
        dataGrid: {
            get() {
                return this.activeTable == 'sales' ? this.sales : this.calls;
            },
            set(val) {
                if (this.activeTable == 'sales') {
                    this.sales = val;
                }
                else {
                    this.calls = val;
                }
            },
        },
        totalRecords() {
            return this.activeTable == 'sales'
                ? this.dataSC.sales.totalRecords
                : this.dataSC.calls.totalRecords;
        },
        exportSalesUrl() {
            return `/reports/sales_by_channel?${this.filterParams}${this.sortParams}&csv=1`;
        },
        exportCallsUrl() {
            return `/reports/calls_by_channel?${this.filterParams}${this.sortParams}&csv=1`;
        },
        sortParams() {
            const params = this.getParams();
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        filterParams() {
            const params = this.getParams();
            return [
                params.startDate ? `&startDate=${params.startDate}` : '',
                params.endDate ? `&endDate=${params.endDate}` : '',
                params.language ? formArrayQueryParam('language', params.language) : '',
                params.commodity
                    ? formArrayQueryParam('commodity', params.commodity)
                    : '',
                params.brand ? formArrayQueryParam('brand', params.brand) : '',
                params.state ? formArrayQueryParam('state', params.state) : '',
                params.channel ? formArrayQueryParam('channel', params.channel) : '',
                params.market ? formArrayQueryParam('market', params.market) : '',
            ].join('');
        },
        initialSearchValues() {
            const params = this.getParams();
            return {
                startDate: params.startDate,
                endDate: params.endDate,
                channel: params.channel,
                market: params.market,
                brand: params.brand,
                language: params.language,
                commodity: params.commodity,
                state: params.state,
            };
        },
    },
    created() {
        this.$store.commit('setStates', this.states);
        this.$store.commit('setBrands', this.brands);
        this.$store.commit('setLanguages', this.languages);
        this.$store.commit('setCommodities', this.commodities);
        this.$store.commit('setMarkets', this.markets);
        this.$store.commit('setChannels', this.channels);
    },
    mounted() {
        document.addEventListener(
            'scroll',
            replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced'),
        );
        const params = this.getParams();
        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        axios
            .get(`/reports/sales_by_channel?${this.filterParams}${this.sortParams}`)
            .then((response) => {
                const res = response.data;

                this.sales = res;
                this.dataSC.sales.totalRecords = res.length;
                this.dataIsLoaded = true;
            })
            .catch(console.log);
        axios
            .get(`/reports/calls_by_channel?${this.filterParams}${this.sortParams}`)
            .then((response) => {
                const res = response.data;

                this.calls = res;
                this.dataSC.calls.totalRecords = res.length;
                this.dataIsLoaded = true;
            })
            .catch(console.log);
    },
    beforeDestroy() {
        document.removeEventListener(
            'scroll',
            replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced'),
        );
    },
    methods: {
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.dataGrid = arraySort(this.headers, this.dataGrid, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        searchData({
            startDate,
            endDate,
            channel,
            market,
            brand,
            language,
            commodity,
            state,
        }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                channel ? formArrayQueryParam('channel', channel) : '',
                brand ? formArrayQueryParam('brand', brand) : '',
                market ? formArrayQueryParam('market', market) : '',
                language ? formArrayQueryParam('language', language) : '',
                commodity ? formArrayQueryParam('commodity', commodity) : '',
                state ? formArrayQueryParam('state', state) : '',
            ].join('');
            window.location.href = `/reports/sales_by_channel?${filterParams}${this.sortParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const brand = url.searchParams.getAll('brand[]');
            const state = url.searchParams.getAll('state[]');
            const commodity = url.searchParams.getAll('commodity[]');
            const language = url.searchParams.getAll('language[]');
            const channel = url.searchParams.getAll('channel[]');
            const market = url.searchParams.getAll('market[]');

            return {
                startDate,
                endDate,
                column,
                direction,
                brand,
                state,
                commodity,
                language,
                channel,
                market,
            };
        },
    },
};
</script>

<style>

.nav nav-tabs {
  background-color: #e4e5e6 !important;
}
.nav-tabs .nav-item {
  background-color: hsla(0, 0%, 100%, 0.75);
}
</style>
