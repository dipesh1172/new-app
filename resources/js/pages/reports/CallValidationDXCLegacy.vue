<template>
  <div id="main-app">
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Call Validation DXC from Legacy', url: '/reports/call_validation_dxc_legacy', active: true}
      ]"
    />

    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <div class="form-inline m-0">
          <select
            id="tpvType"
            name="tpvType"
            class="form-control form-control-sm mt-1"
          >
            <option value="">
              Select a TPV Type
            </option>
            <option
              v-for="tpvType in tpvTypes"
              :key="tpvType.id"
              :value="tpvType.value"
              :selected="tpvType.value == getParams().tpvType"
            >
              {{ tpvType.value }}
            </option>
          </select>
          <select
            id="language"
            name="language"
            class="form-control form-control-sm mt-1 ml-1"
          >
            <option value="">
              Select a Language
            </option>
            <option
              v-for="language in languages"
              :key="language.id"
              :value="language.value"
              :selected="language.value == getParams().language"
            >
              {{ language.value }}
            </option>
          </select>
          <select
            id="brand"
            name="brand"
            class="form-control form-control-sm mt-1 ml-1"
          >
            <option value="">
              Select a Brand
            </option>
            <option
              v-for="(brand, index) in brands"
              :key="index"
              :value="brand.brand"
              :selected="brand.brand == getParams().brand"
            >
              {{ brand.brand }}
            </option>
          </select>
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
        </div>
      </nav>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Report: Call Validation DXC from Legacy
            <span
              class="badge badge-pill badge-info pull-right p-2"
              style="font-size:1em"
            >Updated every 30 min</span>
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
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'CallValidationDXCLegacy',
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
            brands: [],
            headers: [
                {
                    label: 'Brand',
                    key: 'brand',
                    serviceKey: 'brand',
                    width: '10%',
                    canSort: true,
                    type: 'string',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Date',
                    key: 'date',
                    serviceKey: 'insert_at',
                    align: 'center',
                    width: '10%',
                    canSort: true,
                    type: 'date',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Time',
                    key: 'time',
                    serviceKey: 'insert_at',
                    align: 'center',
                    width: '10%',
                    canSort: true,
                    type: 'date',
                    sorted: NO_SORTED,
                },
                {
                    label: 'TPV type ',
                    key: 'tpv_type',
                    serviceKey: 'tpv_type',
                    align: 'center',
                    width: '10%',
                    canSort: true,
                    type: 'string',
                    sorted: NO_SORTED,
                },

                {
                    label: 'Language',
                    key: 'language',
                    serviceKey: 'language',
                    align: 'center',
                    width: '10%',
                    canSort: true,
                    type: 'string',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Call segments',
                    key: 'call_segments',
                    serviceKey: 'call_segments',
                    align: 'center',
                    width: '10%',
                    canSort: false,
                    type: 'string',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Session Call ID',
                    key: 'cic_call_id_keys',
                    serviceKey: 'cic_call_id_keys',
                    align: 'center',
                    width: '10%',
                    canSort: false,
                    type: 'string',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Call time',
                    key: 'call_time',
                    serviceKey: 'call_time',
                    align: 'right',
                    width: '10%',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Confirmation Code',
                    key: 'confirmation_code',
                    serviceKey: 'confirmation_code',
                    align: 'center',
                    width: '10%',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
            ],
            languages: [{ id: 0, value: 'English' }, { id: 1, value: 'Spanish' }],
            tpvTypes: [{ id: 0, value: 'TPV' }, { id: 1, value: 'QC' }],
            dataIsLoaded: false,
            displaySearchBar: false,
            activePage: 1,
            numberPages: 1,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/list_call_validation_dxc_legacy?${this.filterParams}${this.sortParams}&csv=true`;
        },
        sortParams() {
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        filterParams() {
            const params = this.getParams();
            return [
                params.startDate ? `&startDate=${params.startDate}` : '',
                params.endDate ? `&endDate=${params.endDate}` : '',
                params.brand ? `&brand=${params.brand}` : '',
                params.tpvType ? `&tpvType=${params.tpvType}` : '',
                params.language ? `&language=${params.language}` : '',
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
        document.title += ' Report: Call Validation DXC from Legacy';
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        axios
            .get(
                `/reports/list_call_validation_dxc_legacy?${pageParam}${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;
                this.calls = res.data.map((element) => {
                    let callsID = '';
                    element.cic_call_id_keys.split('|').forEach((callID) => {
                        callsID += `<p>${callID}</p>`;
                    });
                    element.cic_call_id_keys = callsID;

                    let segments = '';
                    element.call_segments.split('|').forEach((segment) => {
                        segments += `<p>${segment}</p>`;
                    });

                    element.call_segments = segments;
                    element.call_time = element.call_time.toFixed(2);
                    element.time = this.$moment(element.insert_at).format('HH:mm:ss');
                    element.date = this.$moment(element.insert_at).format('YYYY-MM-DD');
                    return element;
                });

                this.totalRecords = res.total;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
            })
            .catch(console.log);

        axios
            .get('/reports/dxc_brands')
            .then((response) => {
                this.brands = response.data;
            })
            .catch(console.log);
    },
    methods: {
        searchData({ startDate, endDate }) {
            const brand = $('#brand').val();
            const tpvType = $('#tpvType').val();
            const language = $('#language').val();
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                brand ? `&brand=${brand}` : '',
                tpvType ? `&tpvType=${tpvType}` : '',
                language ? `&language=${language}` : '',
            ].join('');
            window.location.href = `/reports/call_validation_dxc_legacy?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/reports/call_validation_dxc_legacy?page=${page}${this.filterParams}`;
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.calls = arraySort(this.headers, this.calls, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        getParams() {
            const url = new URL(window.location.href);
            const startDate = url.searchParams.get('startDate')
        || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page');
            const brand = url.searchParams.get('brand');
            const tpvType = url.searchParams.get('tpvType');
            const language = url.searchParams.get('language');

            return {
                startDate,
                endDate,
                column,
                direction,
                page,
                brand,
                tpvType,
                language,
            };
        },
    },
};
</script>
