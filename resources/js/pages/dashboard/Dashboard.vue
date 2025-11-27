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
            :on-submit="filterData"
            :initial-values="initialSearchValues"
            btn-label="Update Page"
            btn-icon="refresh"
            include-brand
            include-date-range
            include-market
            include-channel
            include-language
            include-commodity
            include-state
            hide-search-box
          />
        </nav>
      </div>
    </div>
    <br >
    <br >
    <br >
    <div class="card dashboard-card">
      <div class="card-body">
        <p class="text-right text-muted small">
          Data updated every 5 minutes
        </p>

        <div class="row">
          <div class="col-md-4">
            <h3 class="text-center">
              Good Sale vs No Sale
            </h3>
            <pie-chart
              :data="[
                ['Sales', salesNoSales.sales],
                ['No Sales', salesNoSales.nosales],
              ]"
              :colors="['#0077c8', '#ed8b00']"
              height="200px"
            />
            <hr>
            <div class="row">
              <div class="col-md-6 text-center">
                <div class="card p-3">
                  <strong>SALE</strong>
                  {{ salesNoSales.sales }} ({{ salesNoSales.sale_percentage }})%
                </div>
              </div>
              <div class="col-md-6 text-center">
                <div class="card p-3">
                  <strong>NO SALE</strong>
                  {{ salesNoSales.nosales }} ({{ salesNoSales.no_sale_percentage }})%
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-12 text-center">
                <div class="card p-3">
                  <strong>Billable Minutes</strong>
                  {{ totalInteractionTime() }}
                  <column-chart
                    :data="[
                      {
                        name: startDateParameter == endDateParameter ? 'Billable Minutes per Hour' : 'Billable Minutes per Day',
                        data: salesNoSalesDataset.itime.map(
                          (s, i) => [salesNoSalesDataset.labels[i], s]
                        ),
                        color: '#0077c8',
                      },

                    ]"
                    height="200px"
                  />
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-8">
            <GmapMap
              ref="salesMap"
              :center="{ lat: 37.44, lng: -95.71 }"
              :zoom="4"
              map-type-id="terrain"
              style="width: 100%; height: 300px"
            >
              <GmapMarker
                v-for="(m, index) in markers"
                :key="index"
                :position="m.position"
                :clickable="true"
                :title="m.zip"
                :label="m.sales"
                @click="m.infoOpened = !m.infoOpened"
              >
                <GmapInfoWindow :opened="m.infoOpened">
                  <b>Zip Code:</b>
                  {{ m.zip }}
                </GmapInfoWindow>
              </GmapMarker>
            </GmapMap>

            <br>

            <h3 class="text-center">
              Sales by
              <template v-if="startDateParameter == endDateParameter">
                Hour
              </template>
              <template v-else>
                Day
              </template>
            </h3>
            <column-chart
              :data="[
                {
                  name: 'Sales',
                  data: salesNoSalesDataset.sales.map(
                    (s, i) => [salesNoSalesDataset.labels[i], s]
                  ),
                  color: '#0077c8',
                },
                {
                  name: 'No Sales',
                  data: salesNoSalesDataset.nosales.map(
                    (s, i) => [salesNoSalesDataset.labels[i], s]
                  ),
                  color: '#ed8b00',
                }
              ]"
            />
          </div>
        </div>
        <div class="row">
          <div
            v-for="widget in byChannel"
            :key="widget.id"
            class="col-md-4 p-3"
          >
            <div class="card p-3 col text-center">
              <div
                class="card-title"
                style="border: 0px;"
              >
                <strong>{{ widget.id }} by Channel</strong>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 text-center">
                    <div class="card p-3">
                      <a
                        v-if="widget.id !== 'AHT'"
                        :href="`/reports/sales_by_channel?startDate=${startDateParameter}&endDate=${endDateParameter}`"
                        class="sales_by_channel"
                      >
                        <strong>DTD</strong>
                        <br>
                        {{ widget.data.DTD }}
                      </a>
                      <span v-if="widget.id === 'AHT'">
                        <strong>DTD</strong>
                        <br>
                        {{ widget.data.DTD }}
                      </span>
                    </div>
                  </div>
                  <div class="col-md-6 text-center">
                    <div class="card p-3">
                      <a
                        v-if="widget.id !== 'AHT'"
                        :href="`/reports/sales_by_channel?startDate=${startDateParameter}&endDate=${endDateParameter}`"
                        class="sales_by_channel"
                      >
                        <strong>TM</strong>
                        <br>
                        {{ widget.data.TM }}
                      </a>
                      <span v-if="widget.id === 'AHT'">
                        <strong>TM</strong>
                        <br>
                        {{ widget.data.TM }}
                      </span>
                    </div>
                  </div>
                  <div class="col-md-6 text-center">
                    <div class="card p-3">
                      <a
                        v-if="widget.id !== 'AHT'"
                        :href="`/reports/sales_by_channel?startDate=${startDateParameter}&endDate=${endDateParameter}`"
                        class="sales_by_channel"
                      >
                        <strong>Retail</strong>
                        <br>
                        {{ widget.data.Retail }}
                      </a>
                      <span v-if="widget.id === 'AHT'">
                        <strong>Retail</strong>
                        <br>
                        {{ widget.data.Retail }}
                      </span>
                    </div>
                  </div>
                  <div class="col-md-6 text-center">
                    <div class="card p-3">
                      <a
                        v-if="widget.id !== 'AHT'"
                        :href="`/reports/sales_by_channel?startDate=${startDateParameter}&endDate=${endDateParameter}`"
                        class="sales_by_channel"
                      >
                        <strong>Care</strong>
                        <br>
                        {{ widget.data.Care }}
                      </a>
                      <span v-if="widget.id === 'AHT'">
                        <strong>Care</strong>
                        <br>
                        {{ widget.data.Care }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <hr>

        <div class="row">
          <div
            v-if="brandParameter == null"
            class="col-md-4"
          >
            <div class="card">
              <div class="card-header text-center">
                <strong>Top Brands</strong>
              </div>
              <div class="card-body table-responsive p-0">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th scope="col">
                        #
                      </th>
                      <th scope="col">
                        Brand
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Sales
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        E%
                      </th>
                    </tr>
                  </thead>
                  <tbody v-if="topBrandsDataset.length">
                    <tr
                      v-for="(brand, index) in topBrandsDataset"
                      :key="index"
                    >
                      <th scope="row">
                        {{ index + 1 }}
                      </th>
                      <td>{{ brand.brand_name }}</td>
                      <td class="text-center">
                        {{ brand.sales }}
                      </td>
                      <td class="text-center">
                        <div
                          v-if="Number(brand.sales) > 0 && Number(brand.no_sales) > 0"
                        >
                          {{ (100 * Number(brand.sales) / (Number(brand.sales) + Number(brand.no_sales))).toFixed(2) }}
                        </div>
                        <div
                          v-else-if="Number(brand.sales) > 0 && Number(brand.no_sales) === 0"
                        >
                          100.00
                        </div>
                        <div v-else>
                          0.00
                        </div>
                      </td>
                    </tr>
                  </tbody>
                  <tbody v-else>
                    <tr>
                      <td
                        class="text-center"
                        colspan="4"
                      >
                        No Information available.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div :class="{'col-md-4':brandParameter == null, 'col-md-6': brandParameter != null}">
            <div class="card">
              <div class="card-header text-center">
                <strong>Sales by Vendor</strong>
              </div>
              <div class="card-body table-responsive p-0">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th scope="col">
                        #
                      </th>
                      <th scope="col">
                        Vendor
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Sales
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                        title="Efficiency percentage."
                      >
                        E%
                      </th>
                    </tr>
                  </thead>
                  <tbody v-if="salesByVendorDataset.length">
                    <tr
                      v-for="(vendor,index) in salesByVendorDataset"
                      :key="index"
                    >
                      <th scope="row">
                        {{ index + 1 }}
                      </th>
                      <td>{{ vendor.name !== null ? vendor.name : 'Unknown Vendor' }}</td>
                      <td class="text-center">
                        {{ vendor.sales_num }}
                      </td>
                      <td class="text-center">
                        <div
                          v-if="Number(vendor.sales_num) > 0 && Number(vendor.nosales_num) > 0"
                        >
                          {{ (100 * Number(vendor.sales_num) / (Number(vendor.sales_num) + Number(vendor.nosales_num))).toFixed(2) }}
                        </div>
                        <div
                          v-else-if="Number(vendor.sales_num) > 0 && Number(vendor.nosales_num) === 0"
                        >
                          100.00
                        </div>
                        <div v-else>
                          100
                        </div>
                      </td>
                    </tr>
                  </tbody>
                  <tbody v-else>
                    <tr>
                      <td
                        class="text-center"
                        colspan="4"
                      >
                        No Information available.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div :class="{'col-md-4':brandParameter == null, 'col-md-6': brandParameter != null}">
            <div class="card">
              <div class="card-header text-center">
                <strong>Top States</strong>
              </div>
              <div class="card-body table-responsive p-0">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th scope="col">
                        #
                      </th>
                      <th scope="col">
                        States
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Sales
                      </th>
                    </tr>
                  </thead>
                  <tbody v-if="topStatesDataset.length">
                    <tr
                      v-for="(state, index) in topStatesDataset"
                      :key="index"
                    >
                      <th scope="row">
                        {{ index + 1 }}
                      </th>
                      <td>{{ state.name }}</td>
                      <td class="text-center">
                        {{ state.sales }}
                      </td>
                    </tr>
                  </tbody>
                  <tbody v-else>
                    <tr>
                      <td
                        class="text-center"
                        colspan="4"
                      >
                        No Information available.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <br>

        <div class="row">
          <div class="col-12 card-header">
            <h3 class="text-center mb-0 pb-0">
              Sales By Source
            </h3>
          </div>
          <div class="col-md-6 pt-2">
            <h3 class="text-center">
              Sales
            </h3>
            <pie-chart
              v-if="salesSource_salesChannel.length > 0"
              :data="salesSource_salesChannel"
              height="300px"
            />
            <div
              v-else
              class="text-center"
              style="height:300px"
            >
              No Data for this Period
            </div>
          </div>
          <div class="col-md-6 pt-2">
            <h3 class="text-center">
              No Sales
            </h3>
            <pie-chart
              v-if="salesSource_nosalesChannel.length > 0"
              :data="salesSource_nosalesChannel"
              height="300px"
            />
            <div
              v-else
              class="text-center"
              style="height:300px"
            >
              No Data for this Period
            </div>
          </div>
        </div>

        <br>

        <div class="row">
          <div class="col-12 card-header">
            <h3 class="text-center mb-0 pb-0">
              Event Sources
            </h3>
          </div>
          <div class="col-md-4 pt-2">
            <h3 class="text-center">
              Sales
            </h3>
            <pie-chart
              v-if="salesSource_sales.length > 0"
              :data="salesSource_sales"
              height="200px"
            />
            <div
              v-else
              class="text-center"
              style="height:200px"
            >
              No Data for this Period
            </div>
          </div>
          <div class="col-md-4 pt-2">
            <h3
              class="text-center"
            >
              No Sales
            </h3>
            <pie-chart
              v-if="salesSource_other.length > 0"
              :data="salesSource_other"
              height="200px"
            />
            <div
              v-else
              class="text-center"
              style="height:200px"
            >
              No Data for this Period
            </div>
          </div>
          <div class="col-md-4 pt-2">
            <h3
              class="text-center"
            >
              Pending/Abandoned
            </h3>
            <pie-chart
              v-if="salesSource_pending.length > 0"
              :data="salesSource_pending"
              height="200px"
            />
            <div
              v-else
              class="text-center"
              style="height:200px"
            >
              No Data for this Period
            </div>
          </div>
        </div>

        <br>
        <hr>
        <br>

        <div
          v-if="startDateParameter != endDateParameter"
          class="row"
        >
          <div class="col-md-12">
            <h3 class="text-center">
              Sales by Day of Week
            </h3>
            <line-chart
              :data="[
                {
                  name: 'Sales',
                  data: salesByDayOfWeekDataset.sales.map(
                    (s, i) => [salesByDayOfWeekDataset.labels[i], s]
                  ),
                  color: '#0077c8',
                },
                {
                  name: 'No Sales',
                  data: salesByDayOfWeekDataset.nosales.map(
                    (s, i) => [salesByDayOfWeekDataset.labels[i], s]
                  ),
                  color: '#ed8b00',
                }
              ]"
            />
          </div>
        </div>
      </div>

      <br>

      <div
        v-if="noSaleDispositionsDataset.length > 1"
        class="row"
      >
        <div class="col-md-12 p-5">
          <h3 class="text-center">
            No Sale Dispositions Chart
          </h3>
          <GBarChart
            :chart-data="noSaleDispositionsDataset.map(d => [d.reason, d.no_sales_num])"
            :colors="['#ed8b00']"
          />
          <!-- <a
            :href="`/sales_dashboard/report/pending?startDate=${startDateParameter}&endDate=${endDateParameter}`"
            class="btn btn-secondary btn-sm mt-2 pull-right"
          >Pending Status Report</a>-->
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import SearchForm from 'components/SearchForm';
import { formArrayQueryParam } from 'utils/stringHelpers';
import { replaceFilterBar } from 'utils/domManipulation';
import GBarChart from 'components/GBarChart';

import { mapState } from 'vuex';

export default {
    name: 'Dashboard',
    components: {
        SearchForm,
        GBarChart,
    },
    props: {
        startDateParameter: {
            type: String,
            default() {
                return this.$moment().format('YYYY-MM-DD');
            },
        },
        endDateParameter: {
            type: String,
            default() {
                return this.$moment().format('YYYY-MM-DD');
            },
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
            salesNoSalesDataset: {
                labels: [],
                nosales: [],
                sales: [],
                itime: [],
            },
            salesByDayOfWeekDataset: {
                labels: [],
                nosales: [],
                sales: [],
            },
            byChannel: [
                {
                    id: 'Sales',
                    data: {
                        Care: 0, Retail: 0, TM: 0, DTD: 0,
                    },
                },
                {
                    id: 'Calls',
                    data: {
                        Care: 0, Retail: 0, TM: 0, DTD: 0,
                    },
                },
                {
                    id: 'AHT',
                    data: {
                        Care: 0, Retail: 0, TM: 0, DTD: 0,
                    },
                },
            ],
            salesNoSales: {},
            salesBySource: {
                sales: [],
                other: [],
                pending: [],
                sales_ch: [],
                nsales_ch: [],
            },
            noSaleDispositionsDataset: [],
            topBrandsDataset: [],
            topStatesDataset: [],
            salesByVendorDataset: [],
            markers: [],
            salesByChannel: {
                DTD: 0,
                TM: 0,
                Retail: 0,
                Care: 0,
            },
            displaySearchBar: true,
        };
    },
    computed: {
        salesSource_sales() {
            return this.salesBySource.sales.map((item) => [item.source, item.cnt]);
        },
        salesSource_other() {
            return this.salesBySource.other.map((item) => [item.source, item.cnt]);
        },
        salesSource_pending() {
            return this.salesBySource.pending.map((item) => [item.source, item.cnt]);
        },
        salesSource_salesChannel() {
            return this.salesBySource.sales_ch.map((item) => [this.interactionTypeTransform(item.interaction_type), item.cnt]);
        },
        salesSource_nosalesChannel() {
            return this.salesBySource.nsales_ch.map((item) => [this.interactionTypeTransform(item.interaction_type), item.cnt]);
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
    },
    watch: {
        markers(newMarkers) {
            if (!newMarkers.length) {
                return;
            }
            const bounds = new google.maps.LatLngBounds();
            newMarkers.forEach((marker) => bounds.extend(marker.position));
            this.$refs.salesMap.fitBounds(bounds);
        },
    },
    mounted() {

        this.displaySearchBar = !this.isMobile();

        document.addEventListener(
            'scroll',
            replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced'),
        );
        axios
            .get(`/sales_dashboard/sales_no_sales_dataset?${this.filterParams}`)
            .then(({ data }) => (this.salesNoSalesDataset = data)).catch(console.log);

        axios
            .get(
                `/sales_dashboard/sales_by_source?${this.filterParams}`,
            )
            .then((response) => {
                this.salesBySource = response.data;
            })
            .catch(console.log);

        axios
            .get(`/sales_dashboard/no_sale_dispositions?${this.filterParams}`)
            .then(({ data }) => {
                const arr = [];
                arr.push({ reason: 'Disposition', no_sales_num: 'Amount' });
                const keys = Object.keys(data);
                keys.forEach((elemt) => {
                    arr.push(data[elemt]);
                });

                this.noSaleDispositionsDataset = arr;
            }).catch(console.log);

        axios
            .get(`/sales_dashboard/sales_by_day_of_week?${this.filterParams}`)
            .then(({ data }) => (this.salesByDayOfWeekDataset = data)).catch(console.log);

        axios
            .get(`/sales_dashboard/sales_no_sales?${this.filterParams}`)
            .then(({ data }) => (this.salesNoSales = data)).catch(console.log);

        axios
            .get(`/sales_dashboard/top_brands_sales?${this.filterParams}`)
            .then(({ data }) => (this.topBrandsDataset = data)).catch(console.log);

        axios
            .get(`/sales_dashboard/top_states_sales?${this.filterParams}`)
            .then(({ data }) => (this.topStatesDataset = data)).catch(console.log);

        axios
            .get(`/sales_dashboard/sales_by_vendor?${this.filterParams}`)
            .then(({ data }) => (this.salesByVendorDataset = data)).catch(console.log);

        axios.get(`/sales_dashboard/good_sales_by_zip?${this.filterParams}`).then(
            ({ data }) => (this.markers = data.map((zip) => ({
                zip: zip.service_zip,
                sales: zip.sales.toString(),
                position: {
                    lat: parseFloat(zip.lat),
                    lng: parseFloat(zip.lon),
                },
                infoOpened: false,
            }))),
        ).catch(console.log);

        axios
            .get(`/sales_dashboard/sales_amount_by_channel?${this.filterParams}`)
            .then(({ data }) => (this.byChannel[0].data = data))
            .catch(console.log);

        axios
            .get(
                `/sales_dashboard/calls_amount_and_aht_by_channel?${this.filterParams}`,
            )
            .then(({ data }) => {
                Object.keys(data).forEach((key) => {
                    this.byChannel[1].data[key] = data[key].calls_amount;
                    this.byChannel[2].data[key] = data[key].aht;
                });
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
        interactionTypeTransform(interaction_type) {
            switch (interaction_type) {
                case 'http_post':
                case 'api_submission':
                    return 'API';
                case 'call_inbound':
                    return 'Incoming Call';
                case 'call_outbound':
                    return 'Outgoing Call';
                case 'digital':
                    return 'Digital';
                case 'eztpv':
                    return 'EzTPV';
                case 'ivr_script':
                    return 'IVR';
                case 'web_enroll':
                    return 'Web Enroll';
                case 'tablet':
                    return 'Tablet';
                case 'qa_update':
                    return 'QA Updated';
                case 'placed_in_call_queue':
                    return 'Queued Call';
                case 'voice_imprint':
                    return 'Voice Imprint';
                default:
                    return `Unknown (${interaction_type})`;
            }
        },
        totalInteractionTime() {
            if (
                !this.salesNoSalesDataset.itime
        || this.salesNoSalesDataset.itime.length == 0
            ) {
                return 0;
            }
            return this.salesNoSalesDataset.itime.reduce((p, c) => p + c).toFixed(2);
        },
        filterData({
            startDate,
            endDate,
            channel,
            market,
            brand,
            language,
            commodity,
            state,
        }) {
            const params = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                channel ? formArrayQueryParam('channel', channel) : '',
                brand ? formArrayQueryParam('brand', brand) : '',
                market ? formArrayQueryParam('market', market) : '',
                language ? formArrayQueryParam('language', language) : '',
                commodity ? formArrayQueryParam('commodity', commodity) : '',
                state ? formArrayQueryParam('state', state) : '',
            ].join('');
            window.location.href = `/sales_dashboard?${params}`;
        },

        isMobile() {
            return this.$mq === 'sm';
        },
    },
};
</script>

<style>
.dashboard-card {
  margin-top: 15px;
}
.sales_by_channel {
  text-decoration: none;
  color: black;
}
a.sales_by_channel:hover {
  text-decoration: none;
  color: black;
}
</style>

