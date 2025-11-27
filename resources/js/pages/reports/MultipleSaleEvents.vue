<template>
  <div id="main-app">
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Multiple Sale Events', active: true}
      ]"
    />
    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <div class="search-form form-inline pull-left">
          <search-form
            :on-submit="searchData"
            :search-label="'Search by confirmation code'"
            :initial-values="initialSearchValues"
            :hide-search-box="false"
            include-date-range
            include-brand
          >
            <div class="form-group pull-right m-0">
              <a
                :href="exportUrl"
                class="btn btn-primary m-0"
                :class="{'disabled': !events.length}"
              ><i
              class="fa fa-download"
              aria-hidden="true"
              /> Export Data</a>
            </div>
          </search-form>
        </div>
      </nav>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <div class="row" style="margin-top:7%;">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <span class="pull-left">
                  <i class="fa fa-th-large" /> Report: Multiple Sale Events
                </span>
              </div>
              <div class="row card-body">
                <div class="col-md-12">
                  <custom-table
                    :headers="headers"
                    :data-grid="events"
                    :data-is-loaded="dataIsLoaded"
                    :total-records="totalRecords"
                    :empty-table-message="`No multiple sale events were found.`"
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
    </div>
  </div>
</template>

<script>
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';
import SearchForm from 'components/SearchForm';
import { formArrayQueryParam } from 'utils/stringHelpers';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'ReportContract',
    components: {
        CustomTable,
        Pagination,
        Breadcrumb,
        SearchForm,
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
            events: [],
            headers: [
                {
                    label: 'Date',
                    align: 'left',
                    key: 'created_at',
                    serviceKey: 'created_at',
                    width: '20%',
                    canSort: false,
                    type: 'date',
                },
                {
                    label: 'Brand',
                    key: 'brand_name',
                    serviceKey: 'brand_name',
                    type: 'string',
                },
                {
                    label: 'Confirmation Code',
                    key: 'confirmation_code',
                    align: 'center',
                    serviceKey: 'confirmation_code',
                    type: 'string',
                },
            ],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/list_multiple_sale_events?csv=true${this.filterParams}`;
        },
        filterParams() {
            const params = this.getParams();
            return [
                params.startDate ? `&startDate=${params.startDate}` : '',
                params.endDate ? `&endDate=${params.endDate}` : '',
                params.search ? `&search=${params.search}` : '',
                params.brand ? formArrayQueryParam('brand', params.brand) : '',
            ].join('');
        },
        initialSearchValues() {
            const params = this.getParams();
            return {
                startDate: params.startDate,
                endDate: params.endDate,
                brand: params.brand,
                search: params.search,
            };
        },
        csrfToken() {
            return window.csrf_token;
        },
    },
    created() {
        this.$store.commit('setBrands', this.brands);
    },
    mounted() {
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        axios
            .get(`/reports/list_multiple_sale_events?${pageParam}${this.filterParams}`)
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;
                this.events = res.data.map((e) => {
                    e.confirmation_code = `<a href="/events/${e.id}">${e.confirmation_code}</a>`;
                    return e;
                });

                this.totalRecords = res.total;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
            })
            .catch(console.log);
    },
    methods: {
        searchData({
            startDate, endDate, brand, search, 
        }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                search ? `&search=${search}` : '',
                brand ? formArrayQueryParam('brand', brand) : '',
            ].join('');
            window.location.href = `/reports/multiple_sale_events?${filterParams}`;
        },
        selectPage(page) {
            window.location.href = `/reports/multiple_sale_events?page=${page}${this.filterParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const page = url.searchParams.get('page');
            const search = url.searchParams.get('search');
            const brand = url.searchParams.getAll('brand[]');
            const startDate = url.searchParams.get('startDate') || this.$moment().format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            return {
                page,
                brand,
                startDate,
                endDate,
                search,
            };
        },
    },
};
</script>
