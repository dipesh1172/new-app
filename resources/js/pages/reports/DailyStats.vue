<template>
  <div>
    <div class="row">
      <div
        class="col-8"
        :style="{paddingRight: 0}"
      >
        <breadcrumb
          :items="[
            {name: 'Home', url: '/'},
            {name: 'Reports', url: '/reports'},
            {name: 'Daily Stats Report', url: '/reports/daily_stats', active: true}
          ]"
        />
      </div>
      <div class="col-4 breadcrumb-right">
        <button
          v-if="$mq === 'sm'"
          class="navbar-toggler sidebar-minimizer float-right"
          type="button"
          @click="displaySearchBar = !displaySearchBar"
        >
          <i class="fa fa-bars" />
        </button>
      </div>
    </div>

    <div
      v-if="$mq !== 'sm' || displaySearchBar"
      class="page-buttons filter-bar-row"
    >
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form
          :on-submit="searchData"
          :initial-values="initialSearchValues"
          :hide-search-box="true"
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
        <div class="card daily-calls-card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Daily Stats Report
          </div>
          <div class="card-body">
            <div
              v-if="hasFlashMessage"
              class="alert alert-success"
            >
              <span class="fa fa-check-circle" />
              <em>{{ flashMessage }}</em>
            </div>
            <div class="table-responsive">
              <custom-table
                :headers="headers"
                :data-grid="events"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No events were found."
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
import { hasProperties } from 'utils/helpers';
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Breadcrumb from 'components/Breadcrumb';

import { mapState } from 'vuex';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'Events',
    components: {
        CustomTable,
        SearchForm,
        Breadcrumb,
    },
    data() {
        return {
            totalRecords: 0,
            events: [],
            headers: [
                {
                    label: 'Brand',
                    key: 'name',
                    serviceKey: 'name',
                    width: '60px',
                    canSort: true,
                    type: 'string',
                    sorted: DESC_SORTED,
                },
                {
                    align: 'center',
                    label: 'Total Mins',
                    key: 'total_live_min',
                    serviceKey: 'total_live_min',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'TM',
                    key: 'live_channel_tm',
                    serviceKey: 'live_channel_tm',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'DTD',
                    key: 'live_channel_dtd',
                    serviceKey: 'live_channel_dtd',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Retail',
                    key: 'live_channel_retail',
                    serviceKey: 'live_channel_retail',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'English',
                    key: 'live_english_min',
                    serviceKey: 'live_english_min',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Spanish',
                    key: 'live_spanish_min',
                    serviceKey: 'live_spanish_min',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Tulsa',
                    key: 'live_cc_tulsa_min',
                    serviceKey: 'live_cc_tulsa_min',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Tahlequah',
                    key: 'live_cc_tahlequah_min',
                    serviceKey: 'live_cc_tahlequah_min',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Las Vegas',
                    key: 'live_cc_lasvegas_min',
                    serviceKey: 'live_cc_lasvegas_min',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Records',
                    key: 'total_records',
                    serviceKey: 'total_records',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'EZTPV',
                    key: 'total_eztpvs',
                    serviceKey: 'total_eztpvs',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Contracts',
                    key: 'eztpv_contract',
                    serviceKey: 'eztpv_contract',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Digital',
                    key: 'digital_transaction',
                    serviceKey: 'digital_transaction',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Imprint',
                    key: 'voice_imprint',
                    serviceKey: 'voice_imprint',
                    width: '60px',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
            ],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            displaySearchBar: false,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        ...mapState({
            hasFlashMessage: (state) => hasProperties(state, 'session.flash_message') && state.session.flash_message,
            flashMessage: (state) => state.session.flash_message,
        }),
        sortParams() {
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        filterParams() {
            return [
                this.getParams().startDate ? `&startDate=${this.getParams().startDate}` : '',
                this.getParams().endDate ? `&endDate=${this.getParams().endDate}` : '',
            ].join('');
        },
        initialSearchValues() {
            return {
                startDate: this.getParams().startDate,
                endDate: this.getParams().endDate,
            };
        },
        exportUrl() {
            return `/reports/listDailyStats?${this.filterParams}${this.sortParams}&csv=true`;
        },
    },
    mounted() {
        const params = this.getParams(); 
        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }
        axios
            .get(`/reports/listDailyStats?${this.filterParams}${this.sortParams}`)
            .then((response) => {
                const events = [];
                response.data.forEach((event) => {
                    events.push(this.getObject(event));
                });

                this.events = events;
                this.dataIsLoaded = true;
                this.totalRecords = events.length;
            })
            .catch(console.log);
    },
    methods: {
        parseFloat(x) {
            return (Number.parseFloat(x)) ? Number.parseFloat(x).toFixed(2) : x;
        },
        getObject(event) {
            return {
                name: event.name,
                total_live_min: this.parseFloat(event.total_live_min),
                live_channel_tm: this.parseFloat(event.live_channel_tm),
                live_channel_dtd: this.parseFloat(event.live_channel_dtd),
                live_channel_retail: this.parseFloat(event.live_channel_retail),
                live_english_min: this.parseFloat(event.live_english_min),
                live_spanish_min: this.parseFloat(event.live_spanish_min),
                live_cc_tulsa_min: this.parseFloat(event.live_cc_tulsa_min),
                live_cc_tahlequah_min: this.parseFloat(event.live_cc_tahlequah_min),
                live_cc_lasvegas_min: this.parseFloat(event.live_cc_lasvegas_min),
                total_records: event.total_records,
                total_eztpvs: event.total_eztpvs,
                eztpv_contract: event.eztpv_contract,
                digital_transaction: event.digital_transaction,
                voice_imprint: event.voice_imprint,
            };
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.events = arraySort(this.headers, this.events, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        searchData({ startDate, endDate }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
            ].join('');
            window.location.href = `/reports/daily_stats?${filterParams}${this.sortParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const startDate = url.searchParams.get('startDate')
        || this.$moment().subtract(1, 'd').format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';

            return {
                startDate,
                endDate,
                column,
                direction,
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

.daily-calls-card {
  margin-top: 43px;
}

.breadcrumb-right {
  border-bottom: 1px solid #a4b7c1;
  background-color: #fff;
  padding: 0.5rem 1rem;
}
</style>
