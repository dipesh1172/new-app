<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Call Followups', url: '/reports/report_call_followups', active: true}
      ]"
    />
    <div class="page-buttons filter-bar-row mt-3">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form
          :on-submit="filterData"
          :initial-values="initialSearchValues"
          :hide-search-box="true"
          include-date-range
        >
          <div class="form-group pull-left m-0">
            <a
              :href="exportUrl"
              class="btn btn-primary m-0"
              :class="{'disabled': !calls.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Export CSV</a>
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
                <i class="fa fa-th-large" /> Call Followups Report
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <custom-table
                    :headers="headers"
                    :data-grid="calls"
                    :data-is-loaded="dataIsLoaded"
                    :total-records="totalRecords"
                    empty-table-message="No calls were found."
                    @sortedByColumn="sortData"
                  />
                </div>
                <simple-pagination
                  :page-nav="pageNav"
                  :filter-params="filterParams"
                  :sort-params="sortParams"
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
import { status, statusLabel, portal } from 'utils/constants';
import { arraySort } from 'utils/arrayManipulation';
import { formArrayQueryParam } from 'utils/stringHelpers';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import SimplePagination from 'components/SimplePagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';
export default {
    name: 'CallFollowups',
    components: {
        SearchForm,
        CustomTable,
        SimplePagination,
        Breadcrumb,
    },
    data() {
        return {
            calls: [],
            headers: [
                {
                    align: 'center',
                    label: '#',
                    key: 'index',
                    serviceKey: 'index',
                    width: '10%',
                    canSort: false,
                },
                {
                    align: 'center',
                    label: 'Flag Type',
                    key: 'flag_type',
                    serviceKey: 'flag_type',
                    width: '10%',
                    canSort: false,
                },
                {
                    align: 'center',
                    label: 'Date of call',
                    key: 'date_created',
                    serviceKey: 'date_created',
                    width: '10%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'date',
                },
                {
                    align: 'center',
                    label: 'Confirmation Code',
                    key: 'confirmation_code',
                    serviceKey: 'confirmation_code',
                    width: '10%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    align: 'center',
                    label: 'Date completed out of Call Followups',
                    key: 'date_completed',
                    serviceKey: 'date_completed',
                    width: '10%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'date',
                },
                {
                    align: 'center',
                    label: 'Brand',
                    key: 'brand_name',
                    serviceKey: 'brand_name',
                    width: '10%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    align: 'center',
                    label: 'Vendor',
                    key: 'vendor_name',
                    serviceKey: 'vendor_name',
                    width: '10%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    align: 'center',
                    label: 'Sales Agent',
                    key: 'name',
                    serviceKey: 'name',
                    width: '10%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    align: 'center',
                    label: 'BTN',
                    key: 'btn',
                    serviceKey: 'btn',
                    width: '10%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    align: 'center',
                    label: 'Reason (if applicable)',
                    key: 'description',
                    serviceKey: 'description',
                    width: '10%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
            ],
            dataIsLoaded: false,
            totalRecords: 0,
            pageNav: { next: null, last: null },
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/report_call_followups?${this.filterParams}${this.sortParams}&csv=1`;
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
            ].join('');
        },
        initialSearchValues() {
            const params = this.getParams();
            return {
                startDate: params.startDate,
                endDate: params.endDate,
            };
        },
    },
    mounted() {
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
                `/reports/report_call_followups?${this.filterParams}${this.sortParams}${pageParam}`,
            )
            .then((response) => {
                const res = response.data;

                this.pageNav = res.page_nav;
                this.calls = response.data.reports;
                let i = 0;
                this.calls.forEach((call) => {
                    call.name = `${call.first_name} ${call.first_name}`;
                    call.flag_type = call.flag_type === '00000000000000000000000000000000'
                        ? '<i  class="fa fa-flag-checkered center" aria-hidden="true"></i>'
                        : '<i  class="fa fa-exclamation-triangle center" aria-hidden="true"></i>';
                    call.index = ++i;
                });

                this.dataIsLoaded = true;
                this.totalRecords = this.calls.length;
            })
            .catch(console.log);
    },
    methods: {
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.calls = arraySort(this.headers, this.calls, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        filterData({ startDate, endDate }) {
            const params = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
            ].join('');
            window.location.href = `/reports/report_call_followups?${params}${this.sortParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page');

            return {
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
