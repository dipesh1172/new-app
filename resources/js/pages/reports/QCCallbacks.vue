<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'QC Callbacks', url: '/reports/report_qc_callbacks', active: true}
      ]"
    />

    <div class="col-4 breadcrumb-right">
      <button
        v-if="$mq === 'sm'"
        class="navbar-toggler sidebar-minimizer float-left"
        type="button"
        @click="displaySearchBar = !displaySearchBar"
      >
        <i class="fa fa-bars" />
      </button>
    </div>

    <div
      v-if="$mq !== 'sm' || displaySearchBar"
      class="page-buttons filter-bar-row qc-callback-filter-div"
    >
      <nav class="navbar navbar-light bg-light filter-navbar">
        <div class="search-form form-inline pull-left">
          <div class="form-group">
            <label for="confirmation_code_search">Confirmation Code</label>
            <input
              id="confirmation_code_search"
              ref="confirmation_code_search"
              class="form-control"
              placeholder="Confirmation Code"
              autocomplete="off"
              name="confirmation_code_search"
              type="text"
              :value="getParams().confirmationCode"
            >
          </div>
          <div class="form-group">
            <label for="tpv_agent_name_search">TPV Agent Name</label>
            <input
              id="tpv_agent_name_search"
              ref="tpv_agent_name_search"
              class="form-control"
              placeholder="TPV Agent Name"
              autocomplete="off"
              name="tpv_agent_name_search"
              type="text"
              :value="getParams().tpvAgentName"
            >
          </div>
          <search-form
            :on-submit="filterData"
            :initial-values="initialSearchValues"
            :hide-search-box="true"
            include-date-range
            :btn-label="`<i class='fa fa-filter'></i> Filter`"
          >
            <div class="form-group">
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
        </div>
      </nav>
    </div>

    <div class="container-fluid">
      <!-- navlist include goes here -->
          <div class="animated fadeIn">
            <div class="row page-buttons">
              <div class="col-md-12" />
            </div>
            <div class="card mt-5">
              <div class="card-header">
                <i class="fa fa-th-large" /> Report: QC Callbacks
              </div>
              <div class="card-body">
                <div
                  v-if="statehasFlashMessage"
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" />
                  <em>{{ stateflashMessage }}</em>
                </div>
                <div class="table-responsive">
                  <custom-table
                    :headers="headers"
                    :data-grid="calls"
                    :data-is-loaded="dataIsLoaded"
                    :total-records="totalRecords"
                    empty-table-message="No events were found."
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
</template>
<script>
import { status, statusLabel, portal } from 'utils/constants';
import { formArrayQueryParam } from 'utils/stringHelpers';
import { replaceFilterBar } from 'utils/domManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import SimplePagination from 'components/SimplePagination';
import Breadcrumb from 'components/Breadcrumb';

import { mapState } from 'vuex';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'QCCallbacks',
    components: {
        CustomTable,
        SearchForm,
        SimplePagination,
        Breadcrumb,
    },
    props: {
        hasFlashMessage: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            calls: [],
            exportUrl: '/reports/report_qc_callbacks?csv=1',
             headers: (() => {
            const commonHeader = {
            width: '14.2%',
            canSort: true
            };
            return[
                  { label: 'Channel', key: 'channel', serviceKey: 'channel', ...commonHeader },
                  { label: 'TPV Agent Name', key: 'tpv_agent_name', serviceKey: 'tpv_agent_name', ...commonHeader },
                  { label: 'Confirmation Code', key: 'confirmation_code', serviceKey: 'confirmation_code', ...commonHeader },
                  { label: 'CB Date', key: 'interaction_created_at', serviceKey: 'interaction_created_at', ...commonHeader },
                  { label: 'CB Attempts Log', key: 'date_log', serviceKey: 'date_log', ...commonHeader },
                  { label: 'CB Status', key: 'log', serviceKey: 'log', ...commonHeader },
                  { label: 'CB Notes Log', key: 'comments', serviceKey: 'comments', ...commonHeader },
            ]; })(),
            dataIsLoaded: false,
            totalRecords: 0,
            pageNav: { next: null, last: null },
            displaySearchBar: false,
        };
    },
    computed: {
        ...mapState({
            currentPortal: 'portal',
            statehasFlashMessage: (state) => state.session && Object.keys(state.session).length && state.session.hasOwnProperty('flash_message') && state.session.flash_message,
            stateflashMessage: (state) => state.session.flash_message,
        }),
        sortParams() {
            const params = this.getParams();
            return !!params.column && !!params.direction
                ? `&column=${params.column}&direction=${params.direction}`
                : '';
        },
        filterParams() {
            const params = this.getParams();
            return [
                params.startDate ? `&startDate=${params.startDate}` : '',
                params.endDate ? `&endDate=${params.endDate}` : '',
                params.confirmationCode
                    ? `&confirmation_code_search=${params.confirmationCode}`
                    : '',
                params.tpvAgentName
                    ? `&tpv_agent_name_search=${params.tpvAgentName}`
                    : '',
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
        this.exportUrl = `/reports/report_qc_callbacks?${this.filterParams}&csv=1`;
        axios
            .get(
                `/reports/report_qc_callbacks?${pageParam}${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;
                this.pageNav = res.page_nav;

                this.totalRecords = res.calls.length;
                res.calls.forEach((call) => {
                    call = this.getObject(call);
                });
                this.calls = res.calls;
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
        getObject(call) {
            call.confirmation_code = `<a href="/events/${call.event_id}">${call.confirmation_code}</a>`;
            call.interaction_created_at = this.$moment(
                call.interaction_created_at,
            ).format('MM/DD/YYYY, h:mm:ss a');
            return call;
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            window.location.href = `/reports/report_qc_callbacks?column=${serviceKey}&direction=${labelSort}${this.filterParams}`;
        },
        filterData({ startDate, endDate }) {
            const confirmation_code_search = this.$refs.confirmation_code_search.value;
            const tpv_agent_name_search = this.$refs.tpv_agent_name_search.value;
            const params = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                confirmation_code_search
                    ? `&confirmation_code_search=${confirmation_code_search}`
                    : '',
                tpv_agent_name_search
                    ? `&tpv_agent_name_search=${tpv_agent_name_search}`
                    : '',
            ].join('');
            window.location.href = `/reports/report_qc_callbacks?${params}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page');
            const confirmationCode = url.searchParams.get('confirmation_code_search');
            const tpvAgentName = url.searchParams.get('tpv_agent_name_search');

            return {
                startDate,
                endDate,
                column,
                direction,
                page,
                confirmationCode,
                tpvAgentName,
            };
        },
    },
};
</script>
