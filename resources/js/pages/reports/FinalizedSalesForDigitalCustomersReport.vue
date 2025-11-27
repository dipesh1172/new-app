<template>
  <div id="main-app">
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Finalized Sales for Digital Customers Report', url: '/reports/finalized_sales_for_digital_customers_report', active: true}
      ]"
    />

    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form
          :on-submit="searchData"
          :initial-values="initialSearchValues"
          :hide-search-box="true"
          include-date-range
          include-brand
        >
          <div class="form-group pull-right m-0">
            <a
              :href="exportUrl"
              class="btn btn-primary m-0"
              :class="{'disabled': !events.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Export Data</a>
          </div>
        </search-form>
      </nav>
    </div>
    <br />

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix"><br>
        <div class="card mt-4">
        <div class="card-header">
            <i class="fa fa-th-large" /> Report: Finalized Sales for Digital Customers
        </div>
          
          <div class="card-body">
            <custom-table
              :headers="headers"
              :data-grid="events"
              :data-is-loaded="dataIsLoaded"
              :total-records="totalRecords"
              empty-table-message="No events were found."
              :no-bottom-padding="true"
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
import { formArrayQueryParam } from 'utils/stringHelpers';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import { arraySort } from 'utils/arrayManipulation';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'FinalizedSalesforDigitalCustomersReport',
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
    },
    data() {
        return {
            totalRecords: 0,
            events: [],
            headers: (() => {
            const commonHeader = {
                align: 'center',
                width: '12%',
                canSort: true,
                sorted: 'NO_SORTED',
                type: 'string'
            };

            return [
                {
                    ...commonHeader,
                    label: 'Event Created At',
                    align: 'left',
                    key: 'event_created_at',
                    serviceKey: 'event_created_at',
                    sorted: 'ASC_SORTED',
                    type: 'date'
                },
                {
                    ...commonHeader,
                    label: 'Confirmation Code',
                    key: 'confirmation_code',
                    serviceKey: 'confirmation_code',
                    type: 'number'
                },
                { label: 'Result', key: 'result', serviceKey: 'result', ...commonHeader },
                { label: 'Interaction Type', key: 'interaction_type', serviceKey: 'interaction_type', ...commonHeader },
                { label: 'Channel', key: 'channel', serviceKey: 'channel', ...commonHeader },
                { label: 'Disposition Label', key: 'disposition_label', serviceKey: 'disposition_label', ...commonHeader },
                { label: 'Disposition Reason', key: 'disposition_reason', serviceKey: 'disposition_reason', ...commonHeader },
                { label: 'Brand Name', key: 'brand_name', serviceKey: 'brand_name', ...commonHeader },
                { label: 'Vendor Name', key: 'vendor_name', serviceKey: 'vendor_name', ...commonHeader },
                { label: 'Office Name', key: 'office_name', serviceKey: 'office_name', ...commonHeader },
                { label: 'Sales Agent Name', key: 'sales_agent_name', serviceKey: 'sales_agent_name', ...commonHeader },
                { label: 'Sales Agent Rep ID', key: 'sales_agent_rep_id', serviceKey: 'sales_agent_rep_id', ...commonHeader },
                { label: 'Service State', key: 'service_state', serviceKey: 'service_state', ...commonHeader }
                ];
                })(),
            dataIsLoaded: false,
            displaySearchBar: false,
            activePage: 1,
            numberPages: 1,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/list_finalized_sales_for_digital_customers?${this.filterParams}${this.sortParams}&csv=true`;
        },
        sortParams() {
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        filterParams() {
            const params = this.getParams();
            return [
                params.startDate ? `&startDate=${params.startDate}` : '',
                params.endDate ? `&endDate=${params.endDate}` : '',
                params.brand ? formArrayQueryParam('brand', params.brand) : '',
            ].join('');
        },
        initialSearchValues() {
            const params = this.getParams();
            return {
                startDate: params.startDate,
                endDate: params.endDate,
                brand: params.brand,
            };
        },
    },
    created() {
        this.$store.commit('setBrands', this.brands);
    },
    mounted() {
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        axios
            .get(
                `/reports/list_finalized_sales_for_digital_customers?${pageParam}${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;
                this.events = res.data.map((event) => {
                    event.confirmation_code = `<a href="/events/${event.event_id}">${event.confirmation_code}</a>`;
                    return event;
                });

                this.totalRecords = res.total;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
            })
            .catch(console.log);
    },
    methods: {
        searchData({ startDate, endDate, brand }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                brand ? formArrayQueryParam('brand', brand) : '',
            ].join('');
            window.location.href = `/reports/finalized_sales_for_digital_customers_report?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/reports/finalized_sales_for_digital_customers_report?page=${page}${this.filterParams}${this.sortParams}`;
        },
        sortData(serviceKey, index) {
            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.getParams().direction === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.selectPage(this.activePage);
        },
        getParams() {
            const url = new URL(window.location.href);
            const brand = url.searchParams.getAll('brand[]');
            const startDate = url.searchParams.get('startDate') || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page');

            return {
                brand,
                startDate,
                endDate,
                column,
                direction,
                page,
            };
        },
    },
};
</script>
