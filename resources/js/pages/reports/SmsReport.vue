<template>
  <div id="main-app">
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'SMS Report', url: '/reports/sms', active: true}
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
          <div class="form-group ml-2">
            <label>Mode:</label>
            <select
              v-model="smsMode"
              class="form-control"
              @change="updateMode"
            >
              <option :value="'all'">
                All SMS
              </option>
              <option :value="'notif'">
                Notification SMS Only
              </option>
            </select>
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
            <i class="fa fa-th-large" /> Report: SMS
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-12">
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
    name: 'SmsReport',
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
            smsMode: 'all',
            totalRecords: 0,
            events: [],
            headers: (() => {
            const commonsmsreportHeader = {
                canSort: true,
                sorted: NO_SORTED,
                align: 'center',
                width: '35%',
                type: 'string', 
            };

            return [
                    { label: 'Date', key: 'created_at', serviceKey: 'created_at', width: '30%', type: 'date', align: 'center',...commonsmsreportHeader },
                    { label: 'Brand', key: 'brand_name', serviceKey: 'brand_name', width: '35%', sorted: ASC_SORTED, align: 'left',...commonsmsreportHeader },
                    { label: 'Sent From', key: 'sent_from', serviceKey: 'sent_from', width: '35%',...commonsmsreportHeader },
                    { label: 'Sent To', key: 'sent_to', serviceKey: 'sent_to', width: '35%',...commonsmsreportHeader },
                    { label: 'Content', key: 'content', serviceKey: 'content', canSort: false,sorted: NO_SORTED, type: 'string',},
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
            return `/reports/list_sms_report?mode=${this.smsMode}${this.filterParams}${this.sortParams}&csv=true`;
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
        if (params.mode !== undefined && params.mode != null) {
            this.smsMode = params.mode;
        }

        axios
            .get(
                `/reports/list_sms_report?mode=${this.smsMode}${pageParam}${this.filterParams}${this.sortParams}`,
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
        updateMode() {
            this.selectPage(this.activePage);
        },
        searchData({ startDate, endDate, brand }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                brand ? formArrayQueryParam('brand', brand) : '',
            ].join('');
            window.location.href = `/reports/sms?mode=${this.smsMode}${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/reports/sms?mode=${this.smsMode}&page=${page}${this.filterParams}${this.sortParams}`;
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            // this.events = arraySort(this.headers, this.events, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
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
            const mode = url.searchParams.get('mode');

            return {
                brand,
                startDate,
                endDate,
                column,
                direction,
                page,
                mode,
            };
        },
    },
};
</script>
