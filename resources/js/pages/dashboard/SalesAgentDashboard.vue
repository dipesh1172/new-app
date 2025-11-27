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
            btn-label="Update Page"
            btn-icon="refresh"
            include-brand
            include-date-range
            include-market
            include-channel
            include-language
            include-commodity
            include-state
          />
        </nav>
      </div>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card sales-agent-dashboard-card">
          <div class="card-body">
            <div class="row mt-5">
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card">
                  <div class="card-body p-3 d-flex align-items-center">
                    <i class="fa fa-user bg-success p-3 font-2xl mr-3" />
                    <div>
                      <div class="text-value-sm text-info">
                        {{ activeAgents }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Active Agents
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card">
                  <div class="card-body p-3 d-flex align-items-center">
                    <i class="fa fa-trophy bg-light p-3 font-2xl mr-3" />
                    <div>
                      <div class="text-value-sm text-info">
                        {{ avgSalesPerDay }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Average Sales
                        per Day
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card">
                  <div class="card-body p-3 d-flex align-items-center">
                    <i class="fa fa-phone bg-warning p-3 font-2xl mr-3" />
                    <div>
                      <div class="text-value-sm text-info">
                        {{ avgCallsPerDay }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Average Calls
                        per Day
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card">
                  <div class="card-body p-3 d-flex align-items-center">
                    <i class="fa fa-user bg-info p-3 font-2xl mr-3" />
                    <div>
                      <div class="text-value-sm text-info">
                        {{ avgAgentsActivePerDay }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Average Active Agents
                        per Day
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card">
                  <div class="card-body p-3 d-flex align-items-center">
                    <i class="fa fa-trophy bg-danger p-3 font-2xl mr-3" />
                    <div>
                      <div class="text-value-sm text-info">
                        {{ avgDailySalesPerAgent }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Average Daily
                        Sales per Agent
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                <div class="card">
                  <div class="card-body p-3 d-flex align-items-center">
                    <i class="fa fa-phone bg-secondary p-3 font-2xl mr-3" />
                    <div>
                      <div class="text-value-sm text-info">
                        {{ avgDailyCallsPerAgent }}
                      </div>
                      <div class="text-muted text-uppercase font-weight-bold small">
                        Average Daily
                        Calls per Agent
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
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
              <!--<line-chart
                            :data="[
                                {
                                name: 'Calls',
                                data: callsByHalfHour.map(
                                    (elemt) => [elemt.halfhour, elemt.calls]
                                ),
                                color: '#0077c8',
                                }
                            ]"
              />-->
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
              <!-- <column-chart id="chart-column-1" :legend="false" :data="[
                                    {
                                        name: 'Calls',
                                        data: callsByWeek.map(
                                        (elemt) => [elemt.dayofweek, elemt.calls]
                                        ),
                                        color: '#0077c8'
                                    }
              ]" />-->
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
              <!-- <column-chart id="chart-column-2" :legend="false" :data="[
                                {
                                    name: 'Active Agents',
                                    data: avgActiveAgentsPerWeek.map(
                                    (elemt) => [elemt.dayofweek, elemt.active_agents]
                                    ),
                                    color: '#ed8b00'
                                }
              ]" />-->
              <div class="chartColumn-container">
                <canvas id="chartColumnAgents" />
              </div>
            </div>
          </div>
          <div class="row mt-5 p-3">
            <div class="col-md-12">
              <!-- <a class="btn btn-success pull-right mt-2 mb-2" :href="exportUrl"> Export Table</a> -->
              <custom-table
                :headers="headers"
                :data-grid="tableDataset"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No clients were found."
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
            exportUrl: '/sales_dashboard/agents/s_a_d_table_dataset?&csv=true',
            activeAgents: 0,
            avgSalesPerDay: 0,
            avgCallsPerDay: 0,
            avgAgentsActivePerDay: 0,
            callsByWeek: [],
            callsByHalfHour: [],
            avgActiveAgentsPerWeek: [],
            tableDataset: [],
            headers: [
                {
                    align: 'left',
                    label: 'Client',
                    key: 'client_name',
                    serviceKey: 'client_name',
                    width: '115px',
                    sorted: NO_SORTED,
                    canSort: false,
                },
                {
                    align: 'left',
                    label: 'Brand',
                    key: 'brand_name',
                    serviceKey: 'brand_name',
                    width: '115px',
                    sorted: NO_SORTED,
                    canSort: false,
                },
                {
                    align: 'center',
                    label: 'Active Agents',
                    key: 'active_agents',
                    serviceKey: 'active_agents',
                    width: '115px',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
                {
                    align: 'center',
                    label: 'AVG Sales per Day',
                    key: 'avg_sales_per_day',
                    serviceKey: 'avg_sales_per_day',
                    width: '30px',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
                {
                    align: 'center',
                    label: 'AVG Calls per Day',
                    key: 'avg_calls_per_day',
                    serviceKey: 'avg_calls_per_day',
                    width: '30px',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
                {
                    align: 'center',
                    label: 'AVG Agents per Day',
                    key: 'avg_agents_per_day',
                    serviceKey: 'avg_agents_per_day',
                    width: '30px',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
                {
                    align: 'center',
                    label: 'AVG Sales per Agent',
                    key: 'avg_sales_per_agent',
                    serviceKey: 'avg_sales_per_agent',
                    width: '30px',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
                {
                    align: 'center',
                    label: 'AVG Calls per Agent',
                    key: 'avg_calls_per_agent',
                    serviceKey: 'avg_calls_per_agent',
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
        avgDailyCallsPerAgent() {
            return this.avgCallsPerDay != 0 && this.avgAgentsActivePerDay != 0
                ? (this.avgCallsPerDay / this.avgAgentsActivePerDay).toFixed(2)
                : 0;
        },
        avgDailySalesPerAgent() {
            return this.avgSalesPerDay != 0 && this.avgAgentsActivePerDay != 0
                ? (this.avgSalesPerDay / this.avgAgentsActivePerDay).toFixed(2)
                : 0;
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
        this.exportUrl = `/sales_dashboard/agents/s_a_d_table_dataset?${this.filterParams}${this.sortParams}&csv=true`;
        axios
            .get(
                `/sales_dashboard/agents/get_active_agents?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.activeAgents = response.data.active_agents;
            })
            .catch(console.log);
        axios
            .get(
                `/sales_dashboard/agents/avg_sales_per_day?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.avgSalesPerDay = response.data.avg_sales_per_day;
            })
            .catch(console.log);
        axios
            .get(
                `/sales_dashboard/agents/avg_calls_per_day?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.avgCallsPerDay = response.data.avg_calls_per_day;
            })
            .catch(console.log);
        axios
            .get(
                `/sales_dashboard/agents/avg_agents_active_per_day?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.avgAgentsActivePerDay = response.data.avg_agents_active_per_day;
            })
            .catch(console.log);
        axios
            .get(
                `/sales_dashboard/agents/avg_calls_per_week?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.callsByWeek = response.data;
                this.loading = false;
                const labels = this.callsByWeek.map((elemt) => elemt.dayofweek);
                const data = this.callsByWeek.map((elemt) => elemt.avg);

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
                        point: {
                            hoverRadius: 7,
                            radius: 5,
                        },
                    },
                    plugins: {
                        legend: false,
                        title: false,
                    },
                });

                const chart = new Chart('chartColumnCalls', {
                    type: 'bar',
                    plugins: [ChartDataLabels],
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Calls',
                                backgroundColor: '#0077c8',
                                data: data,
                                datalabels: {
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
                                // backgroundColor: 'white',
                                backgroundColor: function(context) {
                                    return context.active
                                        ? 'white'
                                        : context.dataset.backgroundColor;
                                },
                                borderColor: function(context) {
                                    return context.dataset.backgroundColor;
                                },
                                // borderRadius: function(context) {
                                //     return context.active ? 0 : 32;
                                // },
                                color: function(context) {
                                    return context.active ? 'black' : 'white';
                                },
                                borderWidth: 1,
                                display: function(context) {
                                    return context.dataset.data[context.dataIndex] != 0;
                                },
                                formatter: function(value, context) {
                                    // value = Math.round(value * 100) / 100;
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
                                    stacked: true,
                                },
                            ],
                            yAxes: [
                                {
                                    stacked: true,
                                },
                            ],
                        },
                    },
                });
            })
            .catch(console.log);
        axios
            .get(
                `/sales_dashboard/agents/avg_active_agents_per_week?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.avgActiveAgentsPerWeek = response.data;
                this.loading = false;

                const labels = this.avgActiveAgentsPerWeek.map(
                    (elemt) => elemt.dayofweek,
                );
                const data = this.avgActiveAgentsPerWeek.map(
                    (elemt) => elemt.avg_agents,
                );

                Chart.helpers.merge(Chart.defaults.global, {
                    aspectRatio: 4 / 3,
                    tooltips: true,
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 32,
                        },
                    },
                    elements: {
                        line: {
                            fill: false,
                        },
                    },
                    plugins: {
                        legend: false,
                        title: false,
                    },
                });

                const chart = new Chart('chartColumnAgents', {
                    type: 'bar',
                    plugins: [ChartDataLabels],
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                backgroundColor: '#ed8b00',
                                data: data,
                            },
                        ],
                    },
                    options: {
                        plugins: {
                            datalabels: {
                                align: 'end',
                                anchor: 'end',
                                color: function(context) {
                                    return 'black';
                                },
                                font: function(context) {
                                    const w = context.chart.width;
                                    return {
                                        size: w < 512 ? 14 : 16,
                                    };
                                },
                                display: function(context) {
                                    return context.dataset.data[context.dataIndex] != 0;
                                },
                                formatter: function(value, context) {
                                    return data[context.dataIndex];
                                },
                            },
                        },
                        scales: {
                            xAxes: [
                                {
                                    display: true,
                                    offset: true,
                                },
                            ],
                            yAxes: [
                                {
                                    ticks: {
                                        beginAtZero: true,
                                    },
                                },
                            ],
                        },
                    },
                });
            })
            .catch(console.log);
        axios
            .get(
                `/sales_dashboard/agents/avg_calls_by_half_hour?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.callsByHalfHour = response.data;
                this.loading = false;

                const labels = this.callsByHalfHour.map((elemt) => elemt.halfhour);
                const data = this.callsByHalfHour.map((elemt) => elemt.avg);

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
                        legend: false,
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
                                backgroundColor: '#0077c8',
                                borderColor: '#0077c8',
                                data: data,
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
                    },
                });
            })
            .catch(console.log);
        axios
            .get(
                `/sales_dashboard/agents/s_a_d_table_dataset?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.tableDataset = response.data;
                this.dataIsLoaded = true;
                this.totalRecords = response.data.length;
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
            window.location.href = `/sales_dashboard/agents?${filterParams}${this.sortParams}`;
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
</style>
