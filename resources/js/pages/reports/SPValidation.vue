<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Stats Product Validation', url: '/reports/stats_product_validation', active: true}
      ]"
    />

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Stats Product
            <div class="form-group pull-right m-0">
              <a
                :href="exportUrl"
                class="btn btn-primary m-0"
                :class="{'disabled': !sproducts.length}"
              ><i
                class="fa fa-download"
                aria-hidden="true"
              /> Export Data</a>
            </div>
          </div>
          <div class="row p-3">
            <div class="col-md-12">
              <custom-table
                :headers="headers"
                :data-grid="sproducts"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No stats product were found."
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
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'SPValidation',
    components: {
        CustomTable,
        Pagination,
        Breadcrumb,
    },
    data() {
        return {
            totalRecords: 0,
            sproducts: [],
            headers: (() => {
            const commonspvalidationHeader = {
                    width: '25%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
            };

            return [
                { label: 'Brand', key: 'brand_name', serviceKey: 'brand_name',commonspvalidationHeader },
                { label: 'ID', key: 'id', serviceKey: 'id',commonspvalidationHeader },
                { label: 'Created At', key: 'created_at', serviceKey: 'created_at', type: 'date',commonspvalidationHeader },
                { label: 'Confirmation Code', key: 'confirmation_code', serviceKey: 'confirmation_code', type: 'number',commonspvalidationHeader }
                ];
                })(),
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/list_stats_product_validation?${this.filterParams}${this.sortParams}&csv=true`;
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

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        axios
            .get(
                `/reports/list_stats_product_validation?${pageParam}${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                const res = response.data;
                this.totalRecords = res.total;

                this.sproducts = res.data.map((sp) => {
                    sp.real_confirmation_code = sp.confirmation_code;
                    sp.confirmation_code = `<a href="/events/${sp.event_id}">${sp.confirmation_code}</a>`;
                    return sp;
                });
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
                this.dataIsLoaded = true;
            })
            .catch(console.log);
    },
    methods: {
        searchData({ startDate, endDate }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
            ].join('');
            window.location.href = `/reports/stats_product_validation?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/reports/stats_product_validation?page=${page}${this.filterParams}${this.sortParams}`;
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            const sort = serviceKey == 'confirmation_code' ? 'real_confirmation_code' : serviceKey;

            this.sproducts = arraySort(this.headers, this.sproducts, sort, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        getParams() {
            const url = new URL(window.location.href);
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page');

            return {
                column,
                direction,
                page,
            };
        },
    },
};
</script>
