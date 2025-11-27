<template>
  <div id="main-app">
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Call Validation DXC', url: '/reports/call_validation_dxc', active: true}
      ]"
    />

    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form
          :on-submit="searchData"
          :initial-values="initialSearchValues"
          :hide-search-box="true"
          include-date-range
        >
          <div class="form-group pull-right m-0">
            <a
              :href="exportUrl"
              class="btn btn-primary m-0"
              :class="{'disabled': !calls.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Data Export</a>
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Report: Call Validation DXC
          </div>
          <div class="row mt-5 p-3">
            <div class="col-md-12">
              <!-- <a class="btn btn-success pull-right mt-2 mb-2" :href="exportUrl"> Export Table</a> -->
              <custom-table
                :headers="headers"
                :data-grid="calls"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No calls were found."
                @sortedByColumn="sortData"
              />
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
</template>

<script>
import { formArrayQueryParam } from 'utils/stringHelpers';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import { mapState } from 'vuex';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';
export default {
    name: 'CallValidationDXC',
    components: {
        CustomTable,
        SearchForm,
        Pagination,
        Breadcrumb,
    },
    data() {
        return {
            totalRecords: 0,
            calls: [],
            exportUrl: '/reports/call_validation_dxc?&csv=true',
            headers: [
                {
                    label: 'SID',
                    key: 'sid',
                    serviceKey: 'sid',
                    width: '10%',
                    canSort: false,
                },
                {
                    label: 'Duration',
                    align: 'right',
                    key: 'duration',
                    serviceKey: 'duration',
                    width: '10%',
                    canSort: true,
                },
                {
                    label: 'StartTime',
                    key: 'startTime',
                    serviceKey: 'startTime',
                    width: '10%',
                    canSort: true,
                },
                {
                    label: 'EndTime',
                    key: 'endTime',
                    serviceKey: 'endTime',
                    width: '10%',
                    canSort: true,
                },
                {
                    label: 'Direction',
                    key: 'direction',
                    serviceKey: 'direction',
                    width: '10%',
                    canSort: true,
                },
                {
                    label: 'To',
                    key: 'toFormatted',
                    serviceKey: 'toFormatted',
                    width: '10%',
                    canSort: true,
                },
                {
                    label: 'From',
                    key: 'fromFormatted',
                    serviceKey: 'fromFormatted',
                    width: '10%',
                    canSort: true,
                },
                {
                    label: 'ForwardedFrom',
                    key: 'forwardedFrom',
                    serviceKey: 'forwardedFrom',
                    width: '10%',
                    canSort: true,
                },
                {
                    label: 'CallerName',
                    key: 'callerName',
                    serviceKey: 'callerName',
                    width: '10%',
                    canSort: true,
                },
                {
                    label: 'AnsweredBy',
                    key: 'answeredBy',
                    serviceKey: 'answeredBy',
                    width: '10%',
                    canSort: true,
                },
            ],
            dataIsLoaded: false,
            displaySearchBar: false,
            activePage: 1,
            numberPages: 1,
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
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        this.exportUrl = `/reports/call_validation_dxc?${this.filterParams}${this.sortParams}&csv=true`;
        axios
            .get(
                `/reports/call_validation_dxc?get_json=1${pageParam}${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;
                this.calls = res.data;

                this.calls.forEach((element) => {
                    // Converting seconds to format '00:00:00'
                    // element.duration = new Date(element.duration * 1000)
                    //   .toISOString()
                    //   .substr(11, 8);
                    element.duration = `${(element.duration / 60).toFixed(2)} (mins)`;
                });

                this.totalRecords = res.total;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
            })
            .catch(console.log);
    },
    methods: {
        searchData({ startDate, endDate }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
            ].join('');
            window.location.href = `/reports/call_validation_dxc?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/reports/call_validation_dxc?page=${page}${this.filterParams}`;
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            window.location.href = `/reports/call_validation_dxc?column=${serviceKey}&direction=${labelSort}${this.filterParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page');

            return {
                startDate,
                endDate,
                column,
                direction,
                page,
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
