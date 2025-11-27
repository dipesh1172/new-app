<template>
  <div>
    <div class="row">
      <div class="col-12 text-center">
        <button
          v-if="isMobile()"
          class="btn btn-default btn-lg"
          type="button"
          @click="displaySearchBar = !displaySearchBar"
        >
          <i class="fa fa-filter" /> Filter
        </button>
      </div>
    </div>
    <div
      class="row filter-bar-row"
      :class="{ 'mobile' : isMobile() }"
    >
      <div
        v-if="displaySearchBar"
        class="col-md-12"
      >
        <nav class="navbar navbar-light bg-light filter-navbar">
          <search-form
            :on-submit="searchData"
            :initial-values="initialSearchValues"
            :hide-search-box="true"
          />
        </nav>
      </div>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card tpv-agent-dashboard-card">
          <div class="card-body">
            <!-- Focus KPIs -->
            <div class="row mt-5">
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 focus-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon">
                      <i class="fa fa-user-circle p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ activeAgents }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Active Agents (Focus)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 focus-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon">
                      <i class="fa fa-phone p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ totalCalls }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Total Calls (Focus)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 focus-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon">
                      <i class="fa fa-clock-o p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ totalPayrollHrs }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Total Payroll Hrs. (Focus)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 focus-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon">
                      <i class="fa fa-clock-o p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ avgHandleTime }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Avg. Handle Time (Focus)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 focus-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon">
                      <i class="fa fa-tachometer p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ productiveOccupancy }} %
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Productive Occupancy (Focus)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 focus-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon">
                      <i class="fa fa-dollar p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ overallRevPerHour }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Overall Rev. per Hour (Focus)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- End Focus KPIs -->

            <!-- DXC KPIs -->
            <div class="row mt-3">
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 dxc-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon dxc">
                      <i class="fa fa-user-circle p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ dxcActiveAgents }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Active Agents (DXC)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 dxc-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon dxc">
                      <i class="fa fa-phone p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ dxcTotalCalls }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Total Calls (DXC)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 dxc-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon dxc">
                      <i class="fa fa-clock-o p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ dxcTotalPayrollHrs }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Total Payroll Hrs. (DXC)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 dxc-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon dxc">
                      <i class="fa fa-clock-o p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ dxcAvgHandleTime }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Avg. Handle Time (DXC)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 dxc-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon dxc">
                      <i class="fa fa-tachometer p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ dxcProductiveOccupancy }} %
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Productive Occupancy (DXC)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 dxc-kpi">
                  <div class="card-body p-2 d-flex align-items-center">
                    <span class="kpi-icon dxc">
                      <i class="fa fa-dollar p-2 fa-3x mr-2" />
                    </span>
                    <div>
                      <div class="text-value-sm text-info">
                        {{ dxcOverallRevPerHour }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Overall Rev. per Hour (DXC)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- End DXC KPIs -->
          </div>
          <div class="row chart-line-container">
            <div class="col-md-12">
              <h3 class="text-center">
                Calls by Half Hour
              </h3>
              <div
                v-if="loading"
                class="d-flex justify-content-center"
              >
                <span class="fa fa-spinner fa-spin fa-2x" />
                <span class="sr-only">Loading...</span>
              </div>

              <div class="chartColumn-container">
                <canvas id="chart-line" />
              </div>
            </div>
          </div>
          <div class="row mt-5">
            <div class="col-md-6">
              <h3 class="text-center">
                AVG Calls By Day
              </h3>
              <div
                v-if="loading"
                class="d-flex justify-content-center"
              >
                <span class="fa fa-spinner fa-spin fa-2x" />
                <span class="sr-only">Loading...</span>
              </div>

              <div class="chartColumn-container">
                <canvas id="chartColumnCalls" />
              </div>
            </div>
            <div class="col-md-6">
              <h3 class="text-center">
                Average Active Agents By Day
              </h3>
              <div
                v-if="loading"
                class="d-flex justify-content-center"
              >
                <span class="fa fa-spinner fa-spin fa-2x" />
                <span class="sr-only">Loading...</span>
              </div>

              <div class="chartColumn-container">
                <canvas id="chartColumnAgents" />
              </div>
            </div>
          </div>
          <div class="row mt-5 p-3">
            <div class="col-md-12">
              <custom-table
                :headers="headers"
                :data-grid="tableDataset"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No records were found."
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
import ChartDataLabels from 'chartjs-plugin-datalabels';
import { formArrayQueryParam } from 'utils/stringHelpers';
import { replaceFilterBar } from 'utils/domManipulation';
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
// avoid to show the labels in every chart on the site
Chart.plugins.unregister(ChartDataLabels);
const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';
export default {
    name: 'SalesAgentD',
    components: {
        CustomTable,
        SearchForm,
    },
    props: {
        columnParameter: {
            type: String,
            default: '',
        },
        startDateParameter: {
            type: String,
            default: function() {
                return this.$moment()
                    .subtract(1, 'days')
                    .format('YYYY-MM-DD');
            },
        },
        endDateParameter: {
            type: String,
            default: function() {
                return this.$moment().format('YYYY-MM-DD');
            },
        },
        directionParameter: {
            type: String,
            default: '',
        },
        channelParameter: {
            type: Array,
            default: () => null,
        },
        marketParameter: {
            type: Array,
            default: () => null,
        },
        brandParameter: {
            type: Array,
            default: () => null,
        },
        languageParameter: {
            type: Array,
            default: () => null,
        },
        commodityParameter: {
            type: Array,
            default: () => null,
        },
        stateParameter: {
            type: Array,
            default: () => null,
        },
    },
    data() {
        return {
            totalRecords: 0,
            loading: true,
            activeAgents: 0,
            dxcActiveAgents: 0,
            totalCalls: 0,
            dxcTotalCalls: 0,
            totalPayrollHrs: 0,
            dxcTotalPayrollHrs: 0,
            avgHandleTime: 0,
            dxcAvgHandleTime: 0,
            productiveOccupancy: 0,
            dxcProductiveOccupancy: 0,
            overallRevPerHour: 0,
            dxcOverallRevPerHour: 0,
            avgCallsByDow: [],
            dxcAvgCallsByDow: [],
            callsByHalfHour: [],
            dxcCallsByHalfHour: [],
            avgActiveAgentsByDow: [],
            dxcAvgActiveAgentsByDow: [],
            tableDataset: [],
            headers: [
                {
                    align: 'left',
                    label: 'Name',
                    key: 'name',
                    serviceKey: 'name',
                    width: '115px',
                    sorted: NO_SORTED,
                    canSort: true,
                },
                {
                    align: 'left',
                    label: 'Platform',
                    key: 'platform',
                    serviceKey: 'platform',
                    width: '115px',
                    sorted: NO_SORTED,
                    canSort: true,
                },
                {
                    align: 'center',
                    label: 'Total Calls',
                    key: 'total_calls',
                    serviceKey: 'total_calls',
                    width: '115px',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
                {
                    align: 'center',
                    label: 'Calls Per Hour',
                    key: 'cph',
                    serviceKey: 'cph',
                    width: '30px',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
                {
                    align: 'center',
                    label: 'Productive Occupancy',
                    key: 'productive_occupancy',
                    serviceKey: 'productive_occupancy',
                    width: '30px',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
                {
                    align: 'center',
                    label: 'Revenue per Payroll Hour',
                    key: 'rev_per_payroll_hour',
                    serviceKey: 'rev_per_payroll_hour',
                    width: '30px',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
            ],
            dataIsLoaded: false,
            displaySearchBar: false,
            column: this.columnParameter,
            direction: this.columnParameter,
        };
    },
    computed: {
        sortParams() {
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        filterParams() {
            return [
                this.startDateParameter ? `&startDate=${this.startDateParameter}` : '',
                this.endDateParameter ? `&endDate=${this.endDateParameter}` : '',
                this.channelParameter
                    ? formArrayQueryParam('channel', this.channelParameter)
                    : '',
                this.brandParameter
                    ? formArrayQueryParam('brand', this.brandParameter)
                    : '',
                this.marketParameter
                    ? formArrayQueryParam('market', this.marketParameter)
                    : '',
                this.languageParameter
                    ? formArrayQueryParam('language', this.languageParameter)
                    : '',
                this.commodityParameter
                    ? formArrayQueryParam('commodity', this.commodityParameter)
                    : '',
                this.stateParameter
                    ? formArrayQueryParam('state', this.stateParameter)
                    : '',
            ].join('');
        },
        initialSearchValues() {
            return {
                startDate: this.startDateParameter,
                endDate: this.endDateParameter,
                channel: this.channelParameter,
                market: this.marketParameter,
                brand: this.brandParameter,
                language: this.languageParameter,
                commodity: this.commodityParameter,
                state: this.stateParameter,
            };
        },
    },
    mounted() {

        this.displaySearchBar = !this.isMobile();

        document.addEventListener(
            'scroll',
            replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced'),
        );

        if (!!this.columnParameter && !!this.directionParameter) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === this.columnParameter,
            );
            this.headers[sortHeaderIndex].sorted = this.directionParameter;
        }

        axios
            .get(
                `/sales_dashboard/tpv_agents/get_call_center_stats?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.activeAgents = response.data.focus.active_agents;
                this.dxcActiveAgents = response.data.dxc.active_agents;

                this.totalCalls = response.data.focus.total_calls;
                this.dxcTotalCalls = response.data.dxc.total_calls;

                this.totalPayrollHrs = response.data.focus.payroll_hours;
                this.dxcTotalPayrollHrs = response.data.dxc.payroll_hours;

                this.avgHandleTime = response.data.focus.avg_handle_time;
                this.dxcAvgHandleTime = response.data.dxc.avg_handle_time;

                this.productiveOccupancy = response.data.focus.productive_occupancy;
                this.dxcProductiveOccupancy = response.data.dxc.productive_occupancy;

                this.overallRevPerHour = response.data.focus.revenue_per_hour;
                this.dxcOverallRevPerHour = response.data.dxc.revenue_per_hour;
            })
            .catch(console.log);

        axios
            .get(
                `/sales_dashboard/tpv_agents/get_dow_stats?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.avgCallsByDow = response.data.calls_by_dow.focus;
                this.dxcAvgCallsByDow = response.data.calls_by_dow.dxc;

                this.avgActiveAgentsByDow = response.data.tpv_agents_by_dow.focus;
                this.dxcAvgActiveAgentsByDow = response.data.tpv_agents_by_dow.dxc;

                this.loading = false;

                const callsLabels = this.avgCallsByDow.map((elemt) => elemt.dayofweek);
                const callsData = this.avgCallsByDow.map((elemt) => elemt.avg);
                const dxcCallsData = this.dxcAvgCallsByDow.map((elemt) => elemt.avg);

                const agentsLabels = this.avgActiveAgentsByDow.map((elemt) => elemt.dayofweek);
                const agentsData = this.avgActiveAgentsByDow.map((elemt) => elemt.avg);
                const dxcAgentsData = this.dxcAvgActiveAgentsByDow.map((elemt) => elemt.avg);

                Chart.helpers.merge(Chart.defaults.global, {
                    aspectRatio: 4 / 3,
                    tooltips: true,
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 32,
                            // top: 42,
                            // right: 16,
                            // bottom: 32,
                            // left: 8,
                        },
                    },
                    elements: {
                        line: {
                            fill: false,
                        },
                        point: {
                            hoverRadius: 7,
                            radius: 5,
                        },
                    },
                    plugins: {
                        legend: true,
                        title: false,
                    },
                });

                //  Calls by DOW chart
                const chart1 = new Chart('chartColumnCalls', {
                    type: 'bar',
                    plugins: [ChartDataLabels],
                    data: {
                        labels: callsLabels,
                        datasets: [
                            {
                                label: 'Focus',
                                backgroundColor: '#0077c8',
                                data: callsData,
                                dataLabels: {
                                    align: 'end',
                                    anchor: 'center',
                                },
                            },
                            {
                                label: 'DXC',
                                backgroundColor: '#ed8b00',
                                data: dxcCallsData,
                                dataLabels: {
                                    align: 'end',
                                    anchor: 'center',
                                },
                            },
                        ],
                    },
                    options: {
                        hover: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            datalabels: {
                                color: 'black',
                                font: {
                                    weight: 'bold',
                                    size: 16,
                                },
                                backgroundColor: function(context) {
                                    return context.active
                                        ? 'white'
                                        : context.dataset.backgroundColor;
                                },
                                borderColor: function(context) {
                                    return context.dataset.backgroundColor;
                                },
                                color: function(context) {
                                    return context.active ? 'black' : 'white';
                                },
                                borderWidth: 1,
                                display: function(context) {
                                    return context.dataset.data[context.dataIndex] != 0;
                                },
                                formatter: function(value, context) {
                                    return context.active
                                        ? `${context.dataset.label}\n${value}`
                                        : value;
                                },
                                offset: 8,
                                textAlign: 'center',
                            },
                        },
                        scales: {
                            xAxes: [
                                {
                                    stacked: false,
                                },
                            ],
                            yAxes: [
                                {
                                    stacked: false,
                                },
                            ],
                        },
                    },
                });

                //  Active Agents by DOW chart
                const chart2 = new Chart('chartColumnAgents', {
                    type: 'bar',
                    plugins: [ChartDataLabels],
                    data: {
                        labels: agentsLabels,
                        datasets: [
                            {
                                label: 'Focus',
                                backgroundColor: '#0077c8',
                                data: agentsData,
                                dataLabels: {
                                    align: 'end',
                                    anchor: 'center',
                                },
                            },
                            {
                                label: 'DXC',
                                backgroundColor: '#ed8b00',
                                data: dxcAgentsData,
                                dataLabels: {
                                    align: 'end',
                                    anchor: 'center',
                                },
                            },
                        ],
                    },
                    options: {
                        hover: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            datalabels: {
                                color: 'black',
                                font: {
                                    weight: 'bold',
                                    size: 16,
                                },
                                backgroundColor: function(context) {
                                    return context.active
                                        ? 'white'
                                        : context.dataset.backgroundColor;
                                },
                                borderColor: function(context) {
                                    return context.dataset.backgroundColor;
                                },
                                color: function(context) {
                                    return context.active ? 'black' : 'white';
                                },
                                borderWidth: 1,
                                display: function(context) {
                                    return context.dataset.data[context.dataIndex] != 0;
                                },
                                formatter: function(value, context) {
                                    return context.active
                                        ? `${context.dataset.label}\n${value}`
                                        : value;
                                },
                                offset: 8,
                                textAlign: 'center',
                            },
                        },
                        scales: {
                            xAxes: [
                                {
                                    stacked: false,
                                },
                            ],
                            yAxes: [
                                {
                                    stacked: false,
                                },
                            ],
                        },
                    },
                });
            })
            .catch(console.log);

        axios
            .get(
                `/sales_dashboard/tpv_agents/avg_calls_by_half_hour?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.callsByHalfHour = response.data.focus;
                this.dxcCallsByHalfHour = response.data.dxc;
                this.loading = false;

                const labels = this.callsByHalfHour.map((elemt) => elemt.halfhour);
                const data = this.callsByHalfHour.map((elemt) => elemt.avg);
                const data2 = this.dxcCallsByHalfHour.map((elemt) => elemt.avg);

                Chart.helpers.merge(Chart.defaults.global, {
                    aspectRatio: 4 / 3,
                    tooltips: true,
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 42,
                            right: 16,
                            bottom: 32,
                            left: 8,
                        },
                    },
                    elements: {
                        line: {
                            fill: false,
                        },
                    },
                    plugins: {
                        legend: true,
                        title: false,
                    },
                });

                const chart = new Chart('chart-line', {
                    type: 'line',
                    plugins: [ChartDataLabels],
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Focus',
                                backgroundColor: '#0077c8',
                                borderColor: '#0077c8',
                                data: data,
                                datalabels: {
                                    align: 'end',
                                    anchor: 'end',
                                },
                            }, {
                                label: 'DXC',
                                backgroundColor: '#ed8b00',
                                borderColor: '#ed8b00',
                                data: data2,
                                datalabels: {
                                    align: 'end',
                                    anchor: 'end',
                                },
                            },
                        ],
                    },
                    options: {
                        plugins: {
                            datalabels: {
                                backgroundColor: function(context) {
                                    return context.dataset.backgroundColor;
                                },
                                borderRadius: 4,
                                color: 'white',
                                font: {
                                    weight: 'bold',
                                    size: 14,
                                },
                            },
                        },
                        scales: {
                            yAxes: [
                                {
                                    stacked: true,
                                },
                            ],
                        },
                        legend: {
                            position: 'top',
                            align: 'start',
                        },
                    },
                });
            })
            .catch(console.log);

        axios
            .get(
                `/sales_dashboard/tpv_agents/tpv_agent_stats?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.tableDataset = response.data.data;
                this.dataIsLoaded = true;
                this.totalRecords = response.data.data.length;
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
        getObject(event) {
            return {
                sales_agent_name: event.sales_agent_name,
                office_name: event.office_name,
                vendor_name: event.vendor_name,
                selling_days: event.selling_days,
                sales: event.sales,
                no_sales: event.no_sales,
                e_: event.e_,
            };
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.tableDataset = arraySort(this.headers, this.tableDataset, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        searchData({
            startDate,
            endDate,
            channel,
            market,
            brand,
            language,
            commodity,
            state,
        }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                channel ? formArrayQueryParam('channel', channel) : '',
                brand ? formArrayQueryParam('brand', brand) : '',
                market ? formArrayQueryParam('market', market) : '',
                language ? formArrayQueryParam('language', language) : '',
                commodity ? formArrayQueryParam('commodity', commodity) : '',
                state ? formArrayQueryParam('state', state) : '',
            ].join('');
            window.location.href = `/sales_dashboard/tpv_agents?${filterParams}${this.sortParams}`;
        },

        isMobile() {
            return this.$mq === 'sm';
        },
    },
};
</script>

<style>
.daily-calls-card {
  margin-top: 43px;
}
.focus-kpi {
  border: 2px solid #0077c833;
  border-radius: 15px;
  /*background: linear-gradient(0deg, '#0077c833' 0%, rgba(255,255,255,0) 50%);*/
}
.dxc-kpi {
  border: 2px solid #ed8b0033;
  border-radius: 15px;
  /*background: linear-gradient(0deg, '#ed8b0033' 0%, rgba(255,255,255,0) 50%);*/
}
span.kpi-icon {
  color: #0077c899;
}
span.kpi-icon.dxc {
  color: #ed8b0099;
}
</style>
