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
            {name: 'Raw Billing Report', url: '/reports/raw_billing', active: true}
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
        <div class="card mt-4">
          <div class="card-header">
            <i class="fa fa-th-large" /> Raw Billing Report
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
            headers: (() => {
            const commonHeaderrawbilling = {
                align: 'center',
                width: '60px',
                canSort: true,
                type: 'number',
                sorted: NO_SORTED,
            };

            return [
                    { label: 'Client', key: 'client_name', serviceKey: 'client_name', width: '60px', canSort: true, type: 'string', sorted: DESC_SORTED },
                    { label: 'Brand', key: 'brand_name', serviceKey: 'brand_name', width: '60px', canSort: true, type: 'string', sorted: DESC_SORTED },
                    { label: 'Date', key: 'period', serviceKey: 'period', width: '60px', canSort: true, type: 'string', sorted: DESC_SORTED },
                    { label: 'Total Records', key: 'total_records', serviceKey: 'total_records', ...commonHeaderrawbilling },
                    { label: 'Total Live Min', key: 'total_live_min', serviceKey: 'total_live_min', ...commonHeaderrawbilling },
                    { label: 'Total Live Inbound Min', key: 'total_live_inbound_min', serviceKey: 'total_live_inbound_min', ...commonHeaderrawbilling },
                    { label: 'Total Live Outbound Min', key: 'total_live_outbound_min', serviceKey: 'total_live_outbound_min', ...commonHeaderrawbilling },
                    { label: 'Live English Min', key: 'live_english_min', serviceKey: 'live_english_min', ...commonHeaderrawbilling },
                    { label: 'Live Spanish Min', key: 'live_spanish_min', serviceKey: 'live_spanish_min', ...commonHeaderrawbilling },
                    { label: 'Live Good Sale', key: 'live_good_sale', serviceKey: 'live_good_sale', ...commonHeaderrawbilling },
                    { label: 'Live No Sale', key: 'live_no_sale', serviceKey: 'live_no_sale', ...commonHeaderrawbilling },
                    { label: 'Live Channel Dtd', key: 'live_channel_dtd', serviceKey: 'live_channel_dtd', ...commonHeaderrawbilling },
                    { label: 'Live Channel Tm', key: 'live_channel_tm', serviceKey: 'live_channel_tm', ...commonHeaderrawbilling },
                    { label: 'Live Channel Retail', key: 'live_channel_retail', serviceKey: 'live_channel_retail', ...commonHeaderrawbilling },
                    { label: 'Live Cc Tulsa Min', key: 'live_cc_tulsa_min', serviceKey: 'live_cc_tulsa_min', ...commonHeaderrawbilling },
                    { label: 'Live Cc Tahlequah Min', key: 'live_cc_tahlequah_min', serviceKey: 'live_cc_tahlequah_min', ...commonHeaderrawbilling },
                    { label: 'Live Cc Lasvegas Min', key: 'live_cc_lasvegas_min', serviceKey: 'live_cc_lasvegas_min', ...commonHeaderrawbilling },
                    { label: 'Total Ivr Min', key: 'total_ivr_min', serviceKey: 'total_ivr_min', ...commonHeaderrawbilling },
                    { label: 'Total Ivr Inbound Min', key: 'total_ivr_inbound_min', serviceKey: 'total_ivr_inbound_min', ...commonHeaderrawbilling },
                    { label: 'Total Ivr Outbound Min', key: 'total_ivr_outbound_min', serviceKey: 'total_ivr_outbound_min', ...commonHeaderrawbilling },
                    { label: 'Dnis Tollfree', key: 'dnis_tollfree', serviceKey: 'dnis_tollfree', ...commonHeaderrawbilling },
                    { label: 'Dnis Local', key: 'dnis_local', serviceKey: 'dnis_local', ...commonHeaderrawbilling },
                    { label: 'Total Eztpvs', key: 'total_eztpvs', serviceKey: 'total_eztpvs', ...commonHeaderrawbilling },
                    { label: 'Total Dtd Eztpvs', key: 'total_dtd_eztpvs', serviceKey: 'total_dtd_eztpvs', ...commonHeaderrawbilling },
                    { label: 'Total Retail Eztpvs', key: 'total_retail_eztpvs', serviceKey: 'total_retail_eztpvs', ...commonHeaderrawbilling },
                    { label: 'Total Tm Eztpvs', key: 'total_tm_eztpvs', serviceKey: 'total_tm_eztpvs', ...commonHeaderrawbilling },
                    { label: 'Eztpv Contract', key: 'eztpv_contract', serviceKey: 'eztpv_contract', ...commonHeaderrawbilling },
                    { label: 'Eztpv Photo', key: 'eztpv_photo', serviceKey: 'eztpv_photo', ...commonHeaderrawbilling },
                    { label: 'Ld Dom', key: 'ld_dom', serviceKey: 'ld_dom', ...commonHeaderrawbilling },
                    { label: 'Ld Intl', key: 'ld_intl', serviceKey: 'ld_intl', ...commonHeaderrawbilling },
                    { label: 'Hrtpv Live Min', key: 'hrtpv_live_min', serviceKey: 'hrtpv_live_min', ...commonHeaderrawbilling },
                    { label: 'Hrtpv Records', key: 'hrtpv_records', serviceKey: 'hrtpv_records', ...commonHeaderrawbilling },
                    { label: 'Survey Live Min', key: 'survey_live_min', serviceKey: 'survey_live_min', ...commonHeaderrawbilling },
                    { label: 'Digital Transaction', key: 'digital_transaction', serviceKey: 'digital_transaction', ...commonHeaderrawbilling },
                    { label: 'Voice Imprint', key: 'voice_imprint', serviceKey: 'voice_imprint', ...commonHeaderrawbilling },

                ];
                })(),
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
            return `/reports/listRawBilling?${this.filterParams}${this.sortParams}&csv=true`;
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
            .get(`/reports/listRawBilling?${this.filterParams}${this.sortParams}`)
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
                brand_name: event.brand_name,
                client_name: event.client_name,
                period: event.period,
                total_live_min: this.parseFloat(event.total_live_min),
                total_live_inbound_min: this.parseFloat(event.total_live_inbound_min),
                total_live_outbound_min: this.parseFloat(event.total_live_outbound_min),
                live_english_min: this.parseFloat(event.live_english_min),
                live_spanish_min: this.parseFloat(event.live_spanish_min),
                live_good_sale: this.parseFloat(event.live_good_sale),
                live_no_sale: this.parseFloat(event.live_no_sale),
                live_channel_dtd: this.parseFloat(event.live_channel_dtd),
                live_channel_tm: this.parseFloat(event.live_channel_tm),
                live_channel_retail: this.parseFloat(event.live_channel_retail),
                live_cc_tulsa_min: this.parseFloat(event.live_cc_tulsa_min),
                live_cc_tahlequah_min: this.parseFloat(event.live_cc_tahlequah_min),
                live_cc_lasvegas_min: this.parseFloat(event.live_cc_lasvegas_min),
                total_ivr_min: this.parseFloat(event.total_ivr_min),
                total_ivr_inbound_min: this.parseFloat(event.total_ivr_inbound_min),
                total_ivr_outbound_min: this.parseFloat(event.total_ivr_outbound_min),
                hrtpv_live_min: this.parseFloat(event.hrtpv_live_min),
                survey_live_min: this.parseFloat(event.survey_live_min),
                digital_transaction: event.digital_transaction,
                voice_imprint: event.voice_imprint,
                dnis_tollfree: event.dnis_tollfree,
                dnis_local: event.dnis_local,
                total_eztpvs: event.total_eztpvs,
                total_dtd_eztpvs: event.total_dtd_eztpvs,
                total_retail_eztpvs: event.total_retail_eztpvs,
                total_tm_eztpvs: event.total_tm_eztpvs,
                eztpv_contract: event.eztpv_contract,
                eztpv_photo: event.eztpv_photo,
                ld_dom: event.ld_dom,
                ld_intl: event.ld_intl,
                total_records: event.total_records,
                hrtpv_records: event.hrtpv_records,
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
            window.location.href = `/reports/raw_billing?${filterParams}${this.sortParams}`;
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
