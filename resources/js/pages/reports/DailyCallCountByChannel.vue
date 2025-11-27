<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Daily Call Count by Channel', url: '/reports/report_daily_call_count_by_channel', active: true}
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
              :class="{'disabled': !counts.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Export Data</a>
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Daily Call Count by Channel
          </div>
          <div class="row mt-5 p-3">
            <div class="col-md-12">
              <custom-table
                :headers="headers"
                :data-grid="counts"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No calls were found."
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
import { formArrayQueryParam } from 'utils/stringHelpers';
import { replaceFilterBar } from 'utils/domManipulation';
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';
export default {
    name: 'DailyCallCountByChannel',
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
            counts: [],
            headers: [
                {
                    label: 'Client',
                    key: 'company',
                    serviceKey: 'company',
                    width: '12.5%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    label: 'State',
                    key: 'state',
                    serviceKey: 'state',
                    width: '12.5%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    label: 'Brand',
                    key: 'vendor',
                    serviceKey: 'vendor',
                    width: '12.5%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    label: 'Channel',
                    key: 'channel',
                    serviceKey: 'channel',
                    width: '12.5%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    label: 'Total',
                    key: 'total',
                    serviceKey: 'total',
                    width: '12.5%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    label: 'Electric',
                    key: 'electric',
                    serviceKey: 'electric',
                    width: '12.5%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    label: 'Gas',
                    key: 'gas',
                    serviceKey: 'gas',
                    width: '12.5%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    label: 'Unknown',
                    key: 'unknown',
                    serviceKey: 'unknown',
                    width: '12.5%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
            ],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/report_daily_call_count_by_channel?${this.filterParams}${this.sortParams}&csv=true`;
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
        document.addEventListener(
            'scroll',
            replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced'),
        );
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        axios
            .get(
                `/reports/report_daily_call_count_by_channel?${pageParam}${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                console.log(response);

                const res = response.data;

                this.counts = res.data;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
                this.totalRecords = res.total;
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
        searchData({ startDate, endDate, brand }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                brand ? formArrayQueryParam('brand', brand) : '',
            ].join('');
            window.location.href = `/reports/report_daily_call_count_by_channel?${filterParams}${this.sortParams}`;
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.counts = arraySort(this.headers, this.counts, serviceKey, index);
            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        selectPage(page) {
            window.location.href = `/reports/report_daily_call_count_by_channel?page=${page}${this.filterParams}${this.sortParams}`;
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
    },
};
</script>

<style>
.breadcrumb {
  margin-bottom: 0.5rem;
}
.filter-navbar {
  box-shadow: 0px 10px 5px -5px rgba(184, 173, 184, 1);
}
.filter-bar-row {
  width: 100%;
  position: fixed;
  z-index: 1000;
}
.filter-bar-row.filter-bar-replaced {
  top: 75px;
}
.breadcrumb-right {
  border-bottom: 1px solid #a4b7c1;
  background-color: #fff;
  padding: 0.5rem 1rem;
}
.text-info {
  font-size: 2em;
}
</style>
