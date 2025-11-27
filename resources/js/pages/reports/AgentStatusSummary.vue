<template>
  <div>
    <div class="row">
      <div
        class="col-12"
        :style="{paddingRight: 0}"
      >
        <breadcrumb
          :items="[
            {name: 'Home', url: '/'},
            {name: 'Reports', url: '/reports'},
            {name: 'Agent Status Summary', url: '/agent-status-summary', active: true}
          ]"
        />
      </div>

      <div
        v-if="$mq !== 'sm' || displaySearchBar"
        class="page-buttons filter-bar-row ml-3 mt-3"
      >
        <nav class="navbar navbar-light bg-light filter-navbar">
          <div class="search-form form-inline pull-right">
            <div class="form-group mr-2">
              <label
                for="location"
                class="mr-2"
              >Location</label>
              <select
                ref="locationFilter"
                class="form-control"
                name="location_filter"
              >
                <option
                  selected="selected"
                  value
                >
                  Select a Location...
                </option>
                <option
                  v-for="l in locations"
                  :key="l.id"
                  :value="l.id"
                  :selected="l.id == getParams().locationParam"
                >
                  {{ l.name }}
                </option>
              </select>
            </div>

            <div class="form-group mr-1">
              <label
                for="agent_name_filter"
                class="mr-2"
              >Agent Name</label>
              <input
                id="agent_name_filter"
                ref="agentNameFilter"
                class="form-control"
                name="agent_name_filter"
                type="text"
                placeholder="Agent Name"
                :value="getParams().agentNameParam"
              >
            </div>
          </div>
          <search-form
            :on-submit="searchData"
            :initial-values="initialSearchValues"
            :hide-search-box="true"
          />
          <div class="form-group pull-right m-0">
            <a
              :href="exportURL"
              class="btn btn-primary m-0"
            >Export CSV</a>
          </div>
        </nav>
      </div>

      <div class="container-fluid">
        <!-- navlist include goes here -->

        <div class="tab-content">
          <div
            role="tabpanel"
            class="tab-pane active"
          >
            <div class="animated fadeIn">
              <div class="card">
                <div class="card-header">
                  <i class="fa fa-th-large" /> Agent Status Summary
                </div>

                <br class="clearfix">

                <div class="col-md-12 card pt-3">
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <custom-table
                        :headers="headers"
                        :data-grid="workers"
                        :data-is-loaded="dataIsLoaded"
                        :total-records="totalRecords"
                        empty-table-message="No agents were found."
                        @sortedByColumn="sortData"
                      />
                    </div>
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
    </div>
  </div>
</template>
<script>
import { formArrayQueryParam } from 'utils/stringHelpers';
import { replaceFilterBar } from 'utils/domManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

import { mapState } from 'vuex';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'AgentStatusSummary',
    components: {
        CustomTable,
        Pagination,
        Breadcrumb,
        SearchForm,
    },
    data() {
        return {
            exportURL: null,
            workers: [],
            supervisors: [],
            locations: [
                { id: 1, name: 'Tulsa' },
                { id: 2, name: 'Tahlequah' },
                { id: 3, name: 'Las Vegas' },
                { id: 4, name: 'Work@Home' },
            ],
            headers: [
                {
                    label: 'TPV Agent Name',
                    key: 'tpv_agent_name',
                    serviceKey: 'tpv_agent_name',
                    width: '5%',
                    canSort: true,
                },
                {
                    label: 'TPV ID',
                    key: 'tpv_id',
                    serviceKey: 'tpv_id',
                    width: '5%',
                    canSort: true,
                },
                {
                    label: 'Role',
                    key: 'role',
                    serviceKey: 'role',
                    width: '5%',
                    canSort: true,
                },
                {
                    label: 'Location',
                    key: 'location',
                    serviceKey: 'location',
                    width: '5%',
                    canSort: true,
                },
                {
                    label: 'Event',
                    key: 'event',
                    serviceKey: 'event',
                    width: '5%',
                    canSort: true,
                },
                {
                    label: 'Duration',
                    key: 'duration',
                    serviceKey: 'duration',
                    width: '5%',
                    canSort: true,
                },
            ],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            totalRecords: 0,
            csrf_token: window.csrf_token,
        };
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

        this.exportURL = `/agent-status-summary?${this.filterParams}${this.sortParams}&csv=true`;
        axios
            .get(
                `/agent-status-summary?get_json=1${this.filterParams}${this.sortParams}${pageParam}`,
            )
            .then((response) => {
                const res = response.data;

                this.workers = res.agents.data;
                this.supervisors = Object.entries(res.supervisors).map((val) => ({ id: val[0], name: val[1] }));
                this.dataIsLoaded = true;
                this.totalRecords = res.agents.total;
                this.activePage = res.agents.current_page;
                this.numberPages = res.agents.last_page;
                
                return this;
            })
            .catch(console.log);
    },
    computed: {
        ...mapState({
            currentPortal: 'portal',
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
                params.agentNameParam
                    ? `&agentNameFilter=${params.agentNameParam}`
                    : '',
                params.locationParam ? `&locationFilter=${params.locationParam}` : '',
            ].join('');
        },
        initialSearchValues() {
            return {
                startDate: this.getParams().startDate,
                endDate: this.getParams().endDate,
            };
        },
    },
    beforeDestroy() {
        document.removeEventListener(
            'scroll',
            replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced'),
        );
    },
    methods: {
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            window.location.href = `/agent-status-summary?column=${serviceKey}&direction=${labelSort}${this.filterParams}`;
        },
        searchData({ startDate, endDate }) {
            const agentNameFilter = this.$refs.agentNameFilter.value;
            const locationFilter = this.$refs.locationFilter.value;

            const filterParams = [
                agentNameFilter ? `&agentNameFilter=${agentNameFilter}` : '',
                locationFilter ? `&locationFilter=${locationFilter}` : '',
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
            ].join('');
            window.location.href = `/agent-status-summary?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/agent-status-summary?page=${page}${this.sortParams}${this.filterParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const locationParam = url.searchParams.get('locationFilter');
            const page = url.searchParams.get('page');
            const agentNameParam = url.searchParams.get('agentNameFilter');
            return {
                startDate,
                endDate,
                column,
                direction,
                locationParam,
                agentNameParam,
                page,
            };
        },
    },
};
</script>
