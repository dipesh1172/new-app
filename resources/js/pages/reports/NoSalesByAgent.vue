<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'No Sales by Agent', url: '/reports/report_no_sales_by_agent', active: true}
      ]"
    />

    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <div class="search-form form-inline pull-left">
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
            :on-submit="searchData"
            :initial-values="initialSearchValues"
            :hide-search-box="true"
            include-date-range
            include-brand
          >
            <div class="form-group pull-right m-0">
              <a
                :href="exportUrl"
                class="btn btn-primary m-0"
                :class="{'disabled': !agents.length}"
              ><i
                class="fa fa-download"
                aria-hidden="true"
              /> Data Export</a>
            </div>
          </search-form>
        </div>
      </nav>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card  mt-4">
          <div class="card-header">
            <i class="fa fa-th-large" /> No Sales by Agent
          </div>
          <div class="row card-body">
            <div class="col-md-12">
              <div class="table-responsive">
                <p
                  v-if="totalRecords"
                  align="right"
                >
                  Total Records: {{ totalRecords }}
                </p>
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th />
                      <th
                        v-for="h in headers"
                        :key="h.name"
                      >
                        {{ h.name }}
                        <i
                          class="fa fa-sort"
                          @click="sortData(h)"
                        />
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!dataIsLoaded">
                      <td
                        colspan="5"
                        class="text-center"
                      >
                        <span class="fa fa-spinner fa-spin fa-2x" />
                      </td>
                    </tr>
                    <template v-if="agents.length === 0 && dataIsLoaded">
                      <tr>
                        <td
                          colspan="5"
                          class="text-center"
                        >
                          No sales were found.
                        </td>
                      </tr>
                    </template>
                    <template
                      v-for="agent in agents"
                      v-else
                    >
                      <tr :key="agent.id">
                        <td>
                          <i
                            v-if="agent.total_sales || agent.total_no_sales"
                            :id="agent.id"
                            class="fa fa-plus-square"
                            name="agent"
                            @click="openSubrows($event, agent)"
                          />
                        </td>
                        <td>{{ agent.name }}</td>
                        <td>{{ agent.total_sales }}</td>
                        <td>{{ agent.total_no_sales }}</td>
                        <td>{{ number_format(agent.total_pct_no_sales, 2) }}%</td>
                      </tr>
                      <template v-for="brand in agent.brands">
                        <tr
                          :id="`brand_${agent.id}`"
                          :key="`${brand.id}_${agent.id}`"
                          class="row-brand"
                          name="subrow"
                        >
                          <td />
                          <td colspan="4">
                            {{ brand.name }}
                          </td>
                        </tr>
                        <tr
                          v-for="channel in brand.channels"
                          :id="`channel_${channel.name}_${agent.id}`"
                          :key="`${channel.name}_${brand.id}_${agent.id}`"
                          class="row-channel"
                          name="subrow"
                        >
                          <td />
                          <td>-- {{ channel.name }}</td>
                          <td>{{ channel.sales }}</td>
                          <td>{{ channel.no_sales }}</td>
                          <td>{{ number_format(channel.pct_no_sales, 2) }}%</td>
                        </tr>
                      </template>
                    </template>
                    <tr
                      v-if="agents.length !== 0 && dataIsLoaded"
                      style="border-top: 2px solid #C2CFD6; font-weight: bold;"
                    >
                      <td colspan="2">
                        Grand Totals
                      </td>
                      <td>{{ gtSales }}</td>
                      <td>{{ gtNoSales }}</td>
                      <td>{{ number_format(gtPercentNoSales, 2) }}%</td>
                    </tr>
                  </tbody>
                </table>
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
import SearchForm from 'components/SearchForm';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'NoSalesByAgent',
    components: {
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
            gtSales: 0,
            gtNoSales: 0,
            gtPercentNoSales: 0,
            totalRecords: 0,
            agents: [],
            headers: [
                { name: 'Sales Agent', sorted: NO_SORTED, key: 'name' },
                { name: 'Good Sales', sorted: NO_SORTED, key: 'total_sales' },
                { name: 'No Sales', sorted: NO_SORTED, key: 'total_no_sales' },
                { name: '% of No Sales', sorted: NO_SORTED, key: 'total_pct_no_sales' },
            ],
            exportUrl: '/reports/report_no_sales_by_agent?&csv=true',
            dataIsLoaded: false,
        };
    },
    computed: {
        filterParams() {
            const params = this.getParams();
            return [
                params.startDate ? `&startDate=${params.startDate}` : '',
                params.endDate ? `&endDate=${params.endDate}` : '',
                params.brand ? formArrayQueryParam('brand', params.brand) : '',
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
                brand: params.brand,
            };
        },
        sortParams() {
            const params = this.getParams();
            return !!params.column && !!params.direction
                ? `&column=${params.column}&direction=${params.direction}`
                : '';
        },
    },
    created() {
        this.$store.commit('setBrands', this.brands);
    },
    mounted() {
        const params = this.getParams();

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.key === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        this.exportUrl = `/reports/report_no_sales_by_agent?${this.filterParams}${this.sortParams}&csv=true`;
        axios
            .get(
                `/reports/report_no_sales_by_agent?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;

                this.agents = res.agents;
                this.gtSales = res.gt_sales;
                this.gtPercentNoSales = res.gt_pct_no_sales;
                this.gtNoSales = res.gt_no_sales;
                this.totalRecords = this.agents.length;
            })
            .catch(console.log);
    },
    beforeDestroy() {
    },
    methods: {
        openSubrows(e, agent) {
            if ($(e.target).hasClass('fa-plus-square')) {
                $('[name=\'subrow\']').hide();
                $('[name=\'agent\']')
                    .removeClass('fa-minus-square')
                    .addClass('fa-plus-square');
                $(`[name$='subrow'][id*='${agent.id}']`).show();
                $(e.target)
                    .removeClass('fa-plus-square')
                    .addClass('fa-minus-square');
            }
            else {
                $('[name=\'subrow\']').hide();
                $('[name=\'agent\']')
                    .removeClass('fa-minus-square')
                    .addClass('fa-plus-square');
            }
        },
        searchData({ startDate, endDate, brand }) {
            const tpv_agent_name_search = this.$refs.tpv_agent_name_search.value;
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                brand ? formArrayQueryParam('brand', brand) : '',
                tpv_agent_name_search
                    ? `&tpv_agent_name_search=${tpv_agent_name_search}`
                    : '',
            ].join('');
            window.location.href = `/reports/report_no_sales_by_agent?${filterParams}${this.sortParams}`;
        },
        sortData(h) {
            const labelSort = this.headers[this.headers.indexOf(h)].sorted === ASC_SORTED
                ? DESC_SORTED
                : ASC_SORTED;
            window.location.href = `/reports/report_no_sales_by_agent?column=${h.key}&direction=${labelSort}${this.filterParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const brand = url.searchParams.getAll('brand[]').length > 0
                ? url.searchParams.getAll('brand[]')
                : null;
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const tpvAgentName = url.searchParams.get('tpv_agent_name_search');
            return {
                brand,
                startDate,
                endDate,
                column,
                direction,
                tpvAgentName,
            };
        },
        number_format(number, decimals, decPoint, thousandsSep) {
            number = (`${number}`).replace(/[^0-9+\-Ee.]/g, '');
            const n = !isFinite(+number) ? 0 : +number;
            const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
            const sep = typeof thousandsSep === 'undefined' ? ',' : thousandsSep;
            const dec = typeof decPoint === 'undefined' ? '.' : decPoint;
            let s = '';

            const toFixedFix = function(n, prec) {
                if ((`${n}`).indexOf('e') === -1) {
                    return +(`${Math.round(`${n}e+${prec}`)}e-${prec}`);
                } 
                const arr = (`${n}`).split('e');
                let sig = '';
                if (+arr[1] + prec > 0) {
                    sig = '+';
                }
                return (+(
                    `${Math.round(`${+arr[0]}e${sig}${+arr[1] + prec}`) 
                    }e-${ 
                        prec}`
                )).toFixed(prec);
        
            };

            // @todo: for IE parseFloat(0.55).toFixed(0) = 0;
            s = (prec ? toFixedFix(n, prec).toString() : `${Math.round(n)}`).split(
                '.',
            );
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }

            return s.join(dec);
        },
    },
};
</script>

