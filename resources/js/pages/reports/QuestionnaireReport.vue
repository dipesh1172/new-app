<template>
  <div id="main-app">
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Questionnaire Report', url: '/reports/questionnaire_report', active: true}
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

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card mt-5"">
          <div class="card-header">
            <i class="fa fa-th-large" /> Report: Questionnaire
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
    name: 'QuesationnaireReport',
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
                      width: '35%',
                      canSort: true,
                      sorted: NO_SORTED,
                      type: 'string',
                      align: 'center'
            };

            return [
                    {
                        label: 'Brand',
                        align: 'left',
                        key: 'name',
                        serviceKey: 'name',
                        width: '35%',
                        canSort: true,
                        sorted: ASC_SORTED,
                        type: 'string'
                    },
                    { label: 'Date', key: 'created_at', serviceKey: 'created_at', width: '30%', type: 'date', align: 'center', canSort: true, sorted: NO_SORTED },
                    { label: 'Vendor Label', key: 'vendor_label', serviceKey: 'vendor_label', ...commonHeader },
                    { label: 'Tsr ID', key: 'tsr_id', serviceKey: 'tsr_id', ...commonHeader },
                    { label: 'First Name', key: 'first_name', serviceKey: 'first_name', ...commonHeader },
                    { label: 'Last Name', key: 'last_name', serviceKey: 'last_name', ...commonHeader }
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
            return `/reports/list_questionnaire?${this.filterParams}${this.sortParams}&csv=true`;
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
                `/reports/list_questionnaire?${pageParam}${this.filterParams}${this.sortParams}`,
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
            window.location.href = `/reports/questionnaire_report?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/reports/questionnaire_report?page=${page}${this.filterParams}${this.sortParams}`;
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
