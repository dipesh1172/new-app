<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Inbound Call Volume', url: '/reports/report_inbound_call_volume', active: true}
      ]"
    />

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

    <div
      v-if="$mq !== 'sm' || displaySearchBar"
      class="page-buttons filter-bar-row"
    >
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form
          :on-submit="filterData"
          :initial-values="initialSearchValues"
          :hide-search-box="true"
          include-brand
          include-date-range
          include-language
        />

        <div class="form-group pull-right m-0">
          <a
            href="/reports/list_report_inbound_call_volume?csv=true"
            class="btn btn-primary m-0"
          ><i
            class="fa fa-download"
            aria-hidden="true"
          /> Export CSV</a>
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
                <i class="fa fa-th-large" /> Inbound Call Volume
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <custom-table
                    :headers="headers"
                    :data-grid="ibcalls"
                    :data-is-loaded="dataIsLoaded"
                    :total-records="totalRecords"
                    empty-table-message="No interactions were found."
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
import { portal } from 'utils/constants';
import { formArrayQueryParam } from 'utils/stringHelpers';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'InboundCallVolume',
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
        languages: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            ibcalls: [],
            current_time: '07:00',
            totalRecords: 0,
            headers: (() => {
                const commoninboundHeader = {
                    align: 'center',
                    width: '12.5%',
                    canSort: true
                };
                return [
                    {
                        label: 'Interval Start',
                        key: 'time_slice',
                        serviceKey: 'time_slice',
                        ...commoninboundHeader,
                        align: 'left'
                    },
                    { label: 'Calls', key: 'calls', serviceKey: 'calls', ...commoninboundHeader },
                    { label: 'Call Time (mins)', key: 'call_time', serviceKey: 'call_time', ...commoninboundHeader },
                    { label: 'Average Call Time (mins)', key: 'avg_call_time', serviceKey: 'avg_call_time', ...commoninboundHeader },
                    { label: 'Average Speed of Answer (secs)', key: 'asa', serviceKey: 'asa', ...commoninboundHeader },
                    { label: 'Calls Abandoned', key: 'calls_abandoned', serviceKey: 'calls_abandoned', ...commoninboundHeader },
                    { label: 'SA (secs)', key: 'avg_abandon_time', serviceKey: 'avg_abandon_time', ...commoninboundHeader },
                    { label: 'Service Level', key: 'service_level', serviceKey: 'service_level', ...commoninboundHeader }
                ];
            })(),
            dataIsLoaded: false,
            displaySearchBar: false,
            portal,
        };
    },
    computed: {
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
                params.language ? formArrayQueryParam('language', params.language) : '',
                params.brand ? formArrayQueryParam('brand', params.brand) : '',
            ].join('');
        },
        initialSearchValues() {
            const params = this.getParams();
            return {
                startDate: params.startDate,
                endDate: params.endDate,
                brand: params.brand,
                language: params.language,
            };
        },
    },
    created() {
        const defaultLang = [{
            id: 1,
            name: 'English',
        }, {
            id: 2,
            name: 'Spanish',
        }];
        this.languages = (this.languages && Array.isArray(this.languages) && this.languages.length)
            ? this.languages
            : defaultLang;

        this.$store.commit('setBrands', this.brands);
        this.$store.commit('setLanguages', this.languages);
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
            .get(
                `/reports/list_report_inbound_call_volume?${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                const res = response.data;
                this.current_time = res.current_time;

                Object.keys(res.ibcalls).forEach((ts) => {
                    this.ibcalls.push(this.getIbcall(res.ibcalls[ts]));
                });

                this.ibcalls.push(this.getTotals(res.totals));
                this.dataIsLoaded = true;
                this.totalRecords = this.ibcalls.length;
            })
            .catch(console.log);
    },
    methods: {
        getIbcall(ibcall) {
            return {
                time_slice: ibcall.time_slice,
                calls: ibcall.calls,
                call_time: this.number_format(ibcall.call_time, 2),
                avg_call_time:
          this.number_format(ibcall.avg_call_time, 2),
                asa:
          this.number_format(ibcall.asa, 2),
                calls_abandoned:
          ibcall.calls_abandoned !== 0
              ? `<span class="text-danger">${ibcall.calls_abandoned}</span>`
              : ibcall.calls_abandoned,
                avg_abandon_time:
          ibcall.avg_abandon_time !== 0
              ? `<span class="text-danger">${this.number_format(
                  ibcall.avg_abandon_time,
                  2,
              )}</span>`
              : this.number_format(ibcall.avg_abandon_time, 2),
                service_level:
          this.current_time > ibcall.time_slice || this.current_time == ''
              ? `${
                  ibcall.service_level != 1
                      ? `<span class="text-danger">${this.number_format(
                          ibcall.service_level * 100,
                          0,
                      )}%</span>`
                      : `${this.number_format(ibcall.service_level * 100, 0)}%`
              }`
              : '--',
            };
        },
        getTotals(totals) {
            const t = {
                time_slice: 'Total',
                calls: totals.calls,
                call_time: `${this.number_format(totals.call_time, 2)}`,
                avg_call_time:
          totals.call_time > 0 && totals.calls > 0
              ? this.number_format(totals.call_time / totals.calls, 2)
              : this.number_format(0, 2),
                asa:
          totals.asa > 0 && totals.calls > 0
              ? `${this.number_format(totals.asa / totals.calls, 2)}`
              : `${this.number_format(0, 2)}`,
                calls_abandoned: totals.calls_abandoned,
                avg_abandon_time:
          totals.avg_abandon_time > 0 && totals.calls_abandoned > 0
              ? `${this.number_format(
                  totals.avg_abandon_time / totals.calls_abandoned,
                  2,
              )}`
              : '0.00',
                service_level:
          totals.service_level > 0
              ? `${this.number_format(
                  totals.service_level * 100,
                  0
              )}%`
              : '100%',
            };

            Object.keys(t).forEach((key) => {
                t[key] = `<strong>${t[key]}</strong>`;
            });
            return t;
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            window.location.href = `/reports/report_inbound_call_volume?column=${serviceKey}&direction=${labelSort}${this.filterParams}`;
        },
        filterData({
            startDate, endDate, brand, language,
        }) {
            const params = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                brand ? formArrayQueryParam('brand', brand) : '',
                language ? formArrayQueryParam('language', language) : '',
            ].join('');
            window.location.href = `/reports/report_inbound_call_volume?${params}`;
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
        getParams() {
            const url = new URL(window.location.href);
            const brand = url.searchParams.getAll('brand[]').length > 0
                ? url.searchParams.getAll('brand[]')
                : null;
            const language = url.searchParams.getAll('language[]').length > 0
                ? url.searchParams.getAll('language[]')
                : null;
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';

            return {
                brand,
                language,
                startDate,
                endDate,
                column,
                direction,
            };
        },
    },
};
</script>
