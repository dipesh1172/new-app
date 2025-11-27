<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Agent Statuses', url: '/reports/report_agent_statuses', active: true}
      ]"
    />
    
    <div class="container-fluid mt-4">
          <div class="animated fadeIn">
            <div class="row page-buttons">
              <div class="col-md-12" />
            </div>
            <div class="card">
              <div class="card-header">
                <i class="fa fa-th-large" /> Agent Statuses
              </div>
              <div class="card-body">
                <div class="form-group mb-5">
                  <div class="form-group mr-3">
                    <label
                      for="location_filter"
                      class="mr-2"
                    >Location</label>
                    <select
                      id="location_filter"
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
                        :key="l.name"
                        :value="l.id"
                        :selected="l.id == getParams().locationFilter"
                      >
                        {{ l.name }}
                      </option>
                    </select>
                  </div>

                  <div class="form-group mr-3">
                    <label
                      for="status_filter"
                      class="mr-2"
                    >Status</label>
                    <select
                      id="status_filter"
                      class="form-control"
                      name="status_filter"
                    >
                      <option
                        selected="selected"
                        value
                      >
                        Select a Status...
                      </option>
                      <option
                        v-for="s in statuses"
                        :key="s.id"
                        :value="s.id"
                        :selected="s.id == getParams().statusFilter"
                      >
                        {{ s.name }}
                      </option>
                    </select>
                  </div>
                  <div class="form-group mr-3">
                    <label
                      for="name_filter"
                      class="mr-2"
                    >TPV Agent Name</label>
                    <input
                      id="name_filter"
                      class="form-control"
                      name="name_filter"
                      type="text"
                      :value="getParams().nameFilter"
                    >
                  </div>
                  <button
                    type="submit"
                    class="btn btn-primary"
                    @click="searchData"
                  >
                    Search
                  </button>
                </div>

                <div class="table-responsive">
                  <custom-table
                    :headers="headers"
                    :data-grid="agents"
                    :data-is-loaded="dataIsLoaded"
                    :total-records="totalRecords"
                    empty-table-message="No agents were found."
                    @sortedByColumn="sortData"
                  />
                  <simple-pagination
                    :filter-params="filterParams"
                    :sort-params="sortParams"
                    :page-nav="pageNav"
                  />
                </div>
              </div>
            </div>
          </div>
    </div>
  </div>
</template>
<script>
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import SimplePagination from 'components/SimplePagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'AgentStatuses',
    components: {
        CustomTable,
        SimplePagination,
        Breadcrumb,
    },
    data() {
        return {
             headers: (() => {
            const commonAgentstatusHeader = {
                      canSort: true,
                      align: 'center',
                      width: '20%',
                      type: 'string',
                      sorted: NO_SORTED,
            };

            return [
                { label: 'Agent', key: 'name', serviceKey: 'name', align: 'left', sorted: ASC_SORTED, ...commonAgentstatusHeader},
                { label: 'Status', key: 'event', serviceKey: 'event', ...commonAgentstatusHeader},
                { label: 'Time', key: 'time', serviceKey: 'time', type: 'date', ...commonAgentstatusHeader},
                { label: 'Elapsed', key: 'elapsed', serviceKey: 'elapsed', align: 'right', type: 'date', ...commonAgentstatusHeader},
                { label: 'Location', key: 'call_center_id', serviceKey: 'call_center_id', ...commonAgentstatusHeader},
                ];
                })(),
            agents: [],
            locations: [
                { id: 1, name: 'Tulsa' },
                { id: 2, name: 'Tahlequah' },
                { id: 3, name: 'Las Vegas' },
            ],
            statuses: [],
            pageNav: { next: null, last: null },
            dataIsLoaded: false,
            totalRecords: 0,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        sortParams() {
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        filterParams() {
            const params = this.getParams();
            return [
                params.locationFilter
                    ? `&location_filter=${params.locationFilter}`
                    : '',
                params.statusFilter ? `&status_filter=${params.statusFilter}` : '',
                params.nameFilter ? `&name_filter=${params.nameFilter}` : '',
            ].join('');
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
                `/reports/report_agent_statuses?${this.filterParams}${this.sortParams}${pageParam}`,
            )
            .then((response) => {
                const res = response.data;
                this.agents = res.agents;
                this.pageNav = res.page_nav;

                this.statuses = Object.entries(res.statuses).map((val) => ({ id: val[0], name: val[1] }));

                this.dataIsLoaded = true;
                this.totalRecords = this.agents.length;
            })
            .catch(console.log);
    },
    methods: {
        getParams() {
            const url = new URL(window.location.href);
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page') || '';
            const locationFilter = url.searchParams.get('location_filter') || '';
            const statusFilter = url.searchParams.get('status_filter') || '';
            const nameFilter = url.searchParams.get('name_filter') || '';

            return {
                column,
                direction,
                page,
                nameFilter,
                statusFilter,
                locationFilter,
            };
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            let timeFlag = '';
            if (serviceKey == 'elapsed') {
                timeFlag = 'time';
            }
            this.agents = arraySort(
                this.headers,
                this.agents,
                timeFlag || serviceKey,
                index,
            );
            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        searchData() {
            const params = [
                $('#location_filter').val()
                    ? `&location_filter=${$('#location_filter').val()}`
                    : '',
                $('#status_filter').val()
                    ? `&status_filter=${$('#status_filter').val()}`
                    : '',
                $('#name_filter').val()
                    ? `&name_filter=${$('#name_filter').val()}`
                    : '',
            ].join('');
            window.location.href = `/reports/report_agent_statuses?${params}`;
        },
    },
};
</script>
