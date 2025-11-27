<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Call Center Dashboard', active: true}
      ]"
    />
    <div class="container-fluid mt-2">
      <div class="animated fadeIn">
        <div id="main-app">
          <div class="page-buttons filter-bar-row">
            <nav class="navbar navbar-light bg-light filter-navbar">
              <search-form
                :on-submit="searchData"
                :initial-values="initialSearchValues"
                :hide-search-box="true"
                include-date-range
              />
            </nav>
          </div>

          <div class="container-fluid">
            <div class="animated fadeIn">
              <br class="clearfix">
              <br>
              <div class="card sales-agent-dashboard-card">
                <div class="card-body">
                  <div class="row mt-5">
                    <div class="col-sm-4 col-md-4 col-lg-3 col-xl-3">
                      <div class="card">
                        <div class="card-body p-3 d-flex align-items-center">
                          <i class="fa fa-user bg-success p-3 font-2xl mr-3" />
                          <div>
                            <div class="text-value-sm text-info">
                              {{ totalOccupancy }}
                            </div>
                            <div class="text-muted text-uppercase font-weight-bold small">
                              Occupancy
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
                              {{ avgSpeedToAnswer }}
                            </div>
                            <div class="text-muted text-uppercase font-weight-bold small">
                              ASA
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
                              {{ totalCalls }}
                            </div>
                            <div class="text-muted text-uppercase font-weight-bold small">
                              Total Calls
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-4 col-md-4 col-lg-3 col-xl-2">
                      <div class="card">
                        <div class="card-body p-3 d-flex align-items-center">
                          <i class="fa fa-level-up bg-info p-3 font-2xl mr-3" />
                          <div>
                            <div class="text-value-sm text-info">
                              {{ serviceLevel }}
                            </div>
                            <div class="text-muted text-uppercase font-weight-bold small">
                              Service Level
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-4 col-md-4 col-lg-3 col-xl-3">
                      <div class="card">
                        <div class="card-body p-3 d-flex align-items-center">
                          <i class="fa fa-clock-o bg-danger p-3 font-2xl mr-3" />
                          <div>
                            <div class="text-value-sm text-info">
                              {{ avgHandleTime }}
                            </div>
                            <div
                              class="text-muted text-uppercase font-weight-bold small"
                            >
                              Average Handle Time
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
                      Talk Time and Payroll Hours
                    </h3>
                    <i
                      v-if="loadingLineChart"
                      class="fa fa-spinner fa-spin fa-3x text-center spinner_centered"
                    />
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
                <div class="row mt-5 p-3">
                  <div class="col-md-12">
                    <!-- <a class="btn btn-success pull-right mt-2 mb-2" :href="exportUrl"> Export Table</a> -->
                    <custom-table
                      :headers="headers"
                      :data-grid="tableDataset"
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
      </div>
    </div>
  </div>
</template>

<script>
import ChartDataLabels from 'chartjs-plugin-datalabels';
import { replaceFilterBar } from 'utils/domManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Breadcrumb from 'components/Breadcrumb';
// avoid to show the labels in every chart on the site
Chart.plugins.unregister(ChartDataLabels);

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';
export default {
    name: 'CallCDashboard',
    components: {
        CustomTable,
        SearchForm,
        Breadcrumb,
    },
    props: {
        columnParameter: {
            type: String,
            default: '',
        },
        directionParameter: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            totalRecords: 0,
            tableDataset: [],
            totalCalls: 0,
            totalOccupancy: 0,
            avgSpeedToAnswer: 0,
            serviceLevel: 0,
            avgHandleTime: 0,
            talkAndPayrollTime: [],
            loading: false,
            exportUrl: '/reports/call_center_dashboard?&csv=true',
            headers: [],
            dataIsLoaded: false,
            loadingLineChart: true,
            displaySearchBar: false,
        };
    },
    computed: {
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
    },
    mounted() {
        document.addEventListener(
            'scroll',
            replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced')
        );
        axios
            .get(
                `/reports/call_center_dashboard/call_center_dataset?${
                    this.filterParams
                }${this.sortParams}`
            )
            .then((response) => {
                this.dataIsLoaded = true;
                const h = [];
                const keys = Object.keys(response.data[0]);
                const totals = response.data.pop();

                keys.forEach((obj, i) => {
                    h.push({
                        align: i === 0 ? 'left' : 'center',
                        label: i === 0 ? '' : obj,
                        key: obj,
                        serviceKey: obj,
                        width: i === 0 ? '180px' : '40px',
                        sorted: NO_SORTED,
                        canSort: false,
                    });
                });

                this.avgHandleTime = totals.total_aht;
                this.serviceLevel = `${totals.total_service_level}%`;
                this.totalOccupancy = `${totals.total_occupancy}%`;
                this.avgSpeedToAnswer = totals.total_asa;
                this.totalCalls = totals.total_calls;
                this.headers = h;
                this.tableDataset = response.data;
            })
            .catch(console.log);
        axios
            .get(
                `/reports/call_center_dashboard/talk_and_payroll_time?${
                    this.filterParams
                }${this.sortParams}`
            )
            .then((response) => {
                this.talkAndPayrollTime = response.data;
                const data_talk_time = [];
                const data_payroll_time = [];
                this.loadingLineChart = false;

                const labels = Object.keys(this.talkAndPayrollTime);
                labels.forEach((key) => {
                    data_talk_time.push(this.talkAndPayrollTime[key].talk_time);
                    data_payroll_time.push(this.talkAndPayrollTime[key].payroll_time);
                });
                // console.log(data_talk_time, data_payroll_time);
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
                                label: 'Talk Time',
                                backgroundColor: '#0077c8',
                                borderColor: '#0077c8',
                                data: data_talk_time,
                                datalabels: {
                                    align: 'center',
                                    anchor: 'center',
                                },
                            },
                            {
                                label: 'Payroll Time',
                                backgroundColor: '#ed8b00',
                                borderColor: '#ed8b00',
                                data: data_payroll_time,
                                datalabels: {
                                    align: 'end',
                                    anchor: 'end',
                                },
                            },
                        ],
                    },
                    options: {
                        legend: {
                            position: 'bottom',
                        },
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
    },
    beforeDestroy() {
        document.removeEventListener(
            'scroll',
            replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced')
        );
    },
    methods: {
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            window.location.href = `/reports/call_center_dashboard?column=${serviceKey}&direction=${labelSort}${
                this.filterParams
            }`;
        },
        searchData({ startDate, endDate }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
            ].join('');
            window.location.href = `/reports/call_center_dashboard?${filterParams}`;
        },
        selectPage(page) {
            window.location.href = `/reports/call_center_dashboard?page=${page}${this.filterParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');

            return {
                startDate,
                endDate,
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
.sales-agent-dashboard-card {
  margin-top: 3rem;
}
.text-info {
  font-size: 2em;
}
.chartColumn-container {
  height: 300px;
  width: 100%;
  position: relative;
}
.spinner_centered {
  margin: 0 auto;
  align-items: center;
  justify-content: space-around;
  display: flex;
  float: none;
}
</style>