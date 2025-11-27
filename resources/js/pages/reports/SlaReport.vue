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
            {name: 'SLA Report', url: '/reports/sla_report', active: true}
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
            
            <button
              class="btn btn-primary m-0"
              :class="{'disabled': !totalRecords}"
              @click="exportCsv"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Export Data</button>
           
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>

        <!-- Validation messages, if any -->
        <div v-if="flashMessage" class="card sla-card">
          <div class="card-body p-0">
            <div class="alert alert-warning m-0">
              <span class="fa fa-exclamation-circle" />
              <em>{{ flashMessage }}</em>
            </div>
          </div>
        </div>

        <!-- Show spinner during lookup -->
        <div v-if="isSearching" class="card sla-card">
          <div class="card-body p-2">
            <div class="col-md-12 text-center">
              <i class="fa fa-pulse fa-spinner fa-3x" />
              <h3>{{$t('searching')}}</h3>
            </div>            
          </div>
        </div>

        <!-- Totals -->
        <div v-if="!flashMessage && dataIsLoaded" class="card sla-card mt-4">
          <div class="card-header">
            <i class="fa fa-th-large" /> Totals
          </div>
          <div class="card-body">

            <div class="table-responsive">
              <custom-table
                :headers="totalsHeader"
                :data-grid="statsTotals"
                :data-is-loaded="dataIsLoaded"
                :total-records="`1`"
                empty-table-message="No statistics were found."
              />
            </div>

          </div>
        </div>

        <!-- Per-interval -->
        <div v-if="!flashMessage && dataIsLoaded" class="card sla-card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Company-wide By Interval
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <custom-table
                :headers="intervalHeader"
                :data-grid="statsByInterval"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No statistics were found."
              />
            </div>
          </div>
        </div>

        <!-- Brand per-interval -->
        <div v-if="!flashMessage && dataIsLoaded">
          <div v-for="(brand, index) in brandStatsByInterval" :key=index class="card sla-card">
            <div class="card-header">
              <i class="fa fa-th-large" /> {{ brand.name }} By Interval
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <custom-table
                  :headers="brandIntervalHeader"
                  :data-grid="brand.stats"
                  :data-is-loaded="dataIsLoaded"
                  :total-records="brand.stats.length"
                  empty-table-message="No statistics were found."
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
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';

export default {
    name: 'SlaReport',
    components: {
        CustomTable,
        SearchForm,
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
            totalRecords: 0,
            statsTotals: [],
            statsByInterval: [],
            brandStatsByInterval: [],
            flashMessage: '',
            isSearching: false,
            totalsHeader: (() => {
            const commontotleHeader = {
                align: 'center',
                width: '60px',
                canSort: false,
                type: 'number',
                sorted: NO_SORTED,
            };

            return [
                {...commontotleHeader,label: 'Calls Available',key: 'callsAvailable',serviceKey: 'callsAvailable',},
                {...commontotleHeader,label: 'Calls Handled',key: 'callsHandled',serviceKey: 'callsHandled',},
                {...commontotleHeader,label: 'Abandoned Calls',key: 'abdnCalls',serviceKey: 'abdnCalls',},
                {...commontotleHeader,label: 'Abandoned %',key: 'abdnPct',serviceKey: 'abdnPct',},
                {...commontotleHeader,label: 'Avg Answer Time',key: 'avgAnswerTime',serviceKey: 'avgAnswerTime',},
                {...commontotleHeader,label: 'Longest Hold Time',key: 'longestHoldTime',serviceKey: 'longestHoldTime',},
                {...commontotleHeader,label: 'Service Level (%)',key: 'serviceLevel',serviceKey: 'serviceLevel',},
                ];
                })(),
            intervalHeader: (() => {
            const commonintervalHeader = {
               align: 'center',
              width: '60px',
              canSort: false,
              type: 'number',
              sorted: NO_SORTED,
            };

            return [
                      { label: 'Date', key: 'date', serviceKey: 'date', type: 'date',...commonintervalHeader },
                      { label: 'Interval', key: 'interval', serviceKey: 'interval', type: 'string',...commonintervalHeader },
                      { label: 'Calls Available', key: 'callsAvailable', serviceKey: 'callsAvailable',...commonintervalHeader },
                      { label: 'Calls Handled', key: 'callsHandled', serviceKey: 'callsHandled',...commonintervalHeader },
                      { label: 'Abandoned Calls', key: 'abdnCalls', serviceKey: 'abdnCalls',...commonintervalHeader },
                      { label: 'Abandoned %', key: 'abdnPct', serviceKey: 'abdnPct',...commonintervalHeader },
                      { label: 'Avg Answer Time', key: 'avgAnswerTime', serviceKey: 'avgAnswerTime',...commonintervalHeader },
                      { label: 'Longest Hold Time', key: 'longestHoldTime', serviceKey: 'longestHoldTime',...commonintervalHeader },
                      { label: 'Service Level (%)', key: 'serviceLevel', serviceKey: 'serviceLevel',...commonintervalHeader },
                ];
                })(),
            brandIntervalHeader: (() => {
            const commonbrandintervalHeader = {
                            align: 'center',
                            width: '60px',
                            canSort: false,
                            sorted: NO_SORTED,
            };

            return [
                        { label: 'Date', key: 'date', serviceKey: 'date', type: 'date',...commonbrandintervalHeader },
                        { label: 'Interval', key: 'interval', serviceKey: 'interval', type: 'string',...commonbrandintervalHeader },
                        { label: 'Calls Available', key: 'callsAvailable', serviceKey: 'callsAvailable', type: 'number',...commonbrandintervalHeader },
                        { label: 'Calls Handled', key: 'callsHandled', serviceKey: 'callsHandled', type: 'number',...commonbrandintervalHeader },
                        { label: 'Abandoned Calls', key: 'abdnCalls', serviceKey: 'abdnCalls', type: 'number',...commonbrandintervalHeader },
                        { label: 'Abandoned %', key: 'abdnPct', serviceKey: 'abdnPct', type: 'number' ,...commonbrandintervalHeader},
                        { label: 'Avg Answer Time', key: 'avgAnswerTime', serviceKey: 'avgAnswerTime', type: 'number',...commonbrandintervalHeader },
                        { label: 'Longest Hold Time', key: 'longestHoldTime', serviceKey: 'longestHoldTime', type: 'number',...commonbrandintervalHeader },
                        { label: 'Service Level (%)', key: 'serviceLevel', serviceKey: 'serviceLevel', type: 'number',...commonbrandintervalHeader },
                        { label: 'Service Level Split', key: 'serviceLevelSplit', serviceKey: 'serviceLevelSplit', type: 'date',...commonbrandintervalHeader }
                ];
                })(),
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            displaySearchBar: false
        };
    },
    computed: {
        initialSearchValues() {
            return {
                startDate: this.$moment().format('YYYY-MM-DD'),
                endDate: this.$moment().format('YYYY-MM-DD')
            };
        },
    },
    mounted() {
      this.getStatsData(
        this.$moment().format('YYYY-MM-DD'),
        this.$moment().format('YYYY-MM-DD')
      );
    },
    methods: {
        async getStatsData(startDate, endDate) {
          
          try {
            // Reset existing data
            this.flashMessage = '';
            this.statsTotals = [];
            this.statsByInterval = [];
            this.brandStatsByInterval = [];
            this.totalRecords = 0;
            this.dataIsLoaded = false;
            this.isSearching = true;

            let url = 'https://apiv2.tpvhub.com/api/reporting/sla?startdate=' + startDate + '&enddate=' + endDate;
            
            let response = await axios.get(url);
            
            this.isSearching = false;

            if(response.data.result == 'success') {
              console.log("STATS: ", response.data);
              let intervalData = response.data.data.companywide.interval;
              let brandIntervalData = response.data.data.brands;

              let intervalStats = [];
              let brandIntervalStats = [];
            
              // Format totals
              // Basically dump totals from returned object into an array as-is
              this.statsTotals.push(response.data.data.companywide.overall);

              // Format company-wide interval stats
              Object.keys(intervalData).forEach((dateKey) => {
                Object.keys(intervalData[dateKey]).forEach((timeKey) => {
                  intervalStats.push(this.formatIntervalObject(dateKey, intervalData[dateKey][timeKey]));
                });
              });

              // Format brand interval stats
              Object.keys(brandIntervalData).forEach((brandKey) => {
                Object.keys(brandIntervalData[brandKey]).forEach((dateKey) => {
                  Object.keys(brandIntervalData[brandKey][dateKey]).forEach((timeKey) => {

                    // Find brand index
                    let index = -1;
                    for(let i = 0; i < brandIntervalStats.length; i++) {
                      if(brandIntervalStats[i].name == brandKey) {
                        index = i;
                        break;
                      }
                    }

                    // Did we find it? if not, set up the brand property
                    if(index < 0) {
                      let brandProp = {
                        name: brandKey,
                        stats: []
                      }

                      brandProp.stats.push(this.formatBrandIntervalObject(dateKey, brandIntervalData[brandKey][dateKey][timeKey]));
                      brandIntervalStats.push(brandProp);

                    } else { // Prop exists. Push to existing stats array
                      brandIntervalStats[index].stats.push(this.formatBrandIntervalObject(dateKey, brandIntervalData[brandKey][dateKey][timeKey]));
                    }
                    
                    
                  });
                });
              });
              
              // Sort the brand data by brand name
              brandIntervalStats.sort((a, b) => {
                const nameA = a.name.toUpperCase(); // ignore upper and lowercase
                const nameB = b.name.toUpperCase(); // ignore upper and lowercase
                
                if (nameA < nameB) {
                  return -1;
                }
                if (nameA > nameB) {
                  return 1;
                }

                // names must be equal
                return 0;
              });

              this.statsByInterval = intervalStats;
              this.brandStatsByInterval = brandIntervalStats;
              this.dataIsLoaded = true;
              this.totalRecords = this.statsByInterval.length;
            } else {
              this.flashMessage = response.data.message;
              console.warn(response.data);
            }

          } catch (err) {
            console.error(err);
          }
        },
        parseFloat(x) {
            return (Number.parseFloat(x)) ? Number.parseFloat(x).toFixed(2) : x;
        },
        formatIntervalObject(date, data) {
            return {
              date: date,
              interval: data.interval,
              callsAvailable: data.callsAvailable,
              callsHandled: data.callsHandled,
              abdnCalls: data.abdnCalls,
              abdnPct: data.abdnPct,
              avgAnswerTime: data.avgAnswerTime,
              longestHoldTime: data.longestHoldTime,
              serviceLevel: data.serviceLevel
            };
        },
        formatBrandIntervalObject(date, data) {
            return {
              date: date,
              interval: data.interval,
              callsAvailable: data.callsAvailable,
              callsHandled: data.callsHandled,
              abdnCalls: data.abdnCalls,
              abdnPct: data.abdnPct,
              avgAnswerTime: data.avgAnswerTime,
              longestHoldTime: data.longestHoldTime,
              serviceLevel: data.serviceLevel,
              serviceLevelSplit: data.serviceLevelSplit
            };
        },
        searchData({ startDate, endDate }) {
            this.getStatsData(startDate, endDate);
        },
        exportCsv() {

          // CSV header.
          // Hard code first header, since that's not in our data.
          // WE'll write the Task Queue name to CSV as we iterate each brand's stats.
          let csv = 'Task Queue,';

          // Use the brand interval stats header to map the rest of the header names.
          let headers = this.brandIntervalHeader.map((item) => {
            return item.label;
          });

          csv += headers.join(',') + '\n';

          // Iterate interval stats data and append to CSV string...

          // Iterate brands
          this.brandStatsByInterval.forEach((brand) => {            

            // Iterate brand intervals
            brand.stats.forEach((interval) => {
              
              // Add task queue name to CSV data
              csv += '"' + brand.name + '",';

              // Iterate stats object keys and append values to CSV string.
              let rowData = [];
              Object.keys(interval).forEach((index) => {
                rowData.push(interval[index]);
              });

              csv += rowData.join(',') + '\n';

            });
          });

          // Create an anchor, set up content type and programmatically click the anchor
          const anchor = document.createElement('a');
          anchor.href= 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
          anchor.target = '_blank';
          anchor.download = 'SLA Report - Task Queues By Interval.csv';
          anchor.click();
        }
    },
};
</script>
