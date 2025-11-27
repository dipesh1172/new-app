<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'AHT', url: '/reports/report_cph', active: true}
      ]"
    />

    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form
          :on-submit="filterData"
          :initial-values="initialSearchValues"
          :hide-search-box="true"
          include-date-range
          include-brand
        >
          <div class="form-group pull-right m-0">
            <a
              :href="exportUrl"
              class="btn btn-primary m-0"
              :class="{'disabled': !agents.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Export Data</a>
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid">
      <div class="tab-content">
        <div
          role="tabpanel"
          class="tab-pane active"
        >
          <div class="animated fadeIn">
            <div class="row page-buttons">
              <div class="col-md-12" />
            </div>
            <div class="card">
              <div class="card-header">
                <i class="fa fa-th-large" /> AHT
              </div>
              <div class="card-body">
                <custom-table
                  :headers="headers"
                  :data-grid="agents"
                  :data-is-loaded="dataIsLoaded"
                  :total-records="totalRecords"
                  empty-table-message="No agents were found."
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
import { formArrayQueryParam } from 'utils/stringHelpers';
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'AHT',
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
            agents: [],
            headers: [
                {
                    label: 'Agent',
                    key: 'name',
                    serviceKey: 'name',
                    width: '20%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    label: 'Inbound Calls',
                    key: 'calls_ib',
                    serviceKey: 'calls_ib',
                    width: '16%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    label: 'Outbound Calls',
                    key: 'calls_ob',
                    serviceKey: 'calls_ob',
                    width: '16%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    label: 'Total Calls',
                    key: 'calls_total',
                    serviceKey: 'calls_total',
                    width: '16%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    label: 'Call Time (mins)',
                    key: 'time_total',
                    serviceKey: 'time_total',
                    width: '16%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    label: 'AHT',
                    key: 'cph',
                    serviceKey: 'cph',
                    width: '16%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
            ],
            dataIsLoaded: false,
            totalRecords: 0,
            activePage: 1,
            numberPages: 1,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/list_cph?&csv=1${this.filterParams}${this.sortParams}`;
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

        if (!!this.column && !!this.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === this.column,
            );
            this.headers[sortHeaderIndex].sorted = this.direction;
        }

        const pageParam = params.page ? `&page=${params.page}` : '';
        axios
            .get(
                `/reports/list_cph?${this.filterParams}${this.sortParams}${pageParam}`,
            )
            .then((response) => {
                const res = response.data;
                this.agents = res.data;
                this.dataIsLoaded = true;
                this.totalRecords = res.total;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
            })
            .catch(console.log);
    },
    methods: {
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.agents = arraySort(this.headers, this.agents, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        filterData({ startDate, endDate, brand }) {
            const params = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                brand ? formArrayQueryParam('brand', brand) : '',
            ].join('');
            window.location.href = `/reports/report_cph?${params}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const brand = url.searchParams.getAll('brand[]').length > 0
                ? url.searchParams.getAll('brand[]')
                : null;
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
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
        selectPage(page) {
            window.location.href = `/reports/report_cph?page=${page}${this.sortParams}${this.filterParams}`;
        },
    },
};
</script>
