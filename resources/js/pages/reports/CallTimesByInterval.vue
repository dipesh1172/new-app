<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Call Times By Interval', url: '/reports/call_times_by_interval', active: true}
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
              :class="{'disabled': !tableDataset.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Data Export</a>
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Call Times By Interval
          </div>
          <div class="row mt-5 p-3">
            <div class="col-md-12">
              <custom-table
                :headers="headers"
                :data-grid="tableDataset"
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
import { replaceFilterBar } from 'utils/domManipulation';
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';
export default {
    name: 'CallTimesByInterval',
    components: {
        CustomTable,
        SearchForm,
        Pagination,
        Breadcrumb,
    },
    data() {
        return {
            totalRecords: 0,
            tableDataset: [],
            exportUrl: '/reports/call_times_by_interval/list_call_times_by_interval?&csv=true',
            headers: [],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
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
        document.addEventListener(
            'scroll',
            replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced'),
        );
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        this.exportUrl = `/reports/call_times_by_interval/list_call_times_by_interval?${this.filterParams}${this.sortParams}&csv=true`;
        axios
            .get(
                `/reports/call_times_by_interval/list_call_times_by_interval?${pageParam}${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.dataIsLoaded = true;
                const h = [];
                const res = response.data;        
        
                const keys = res.data[0] ? Object.keys(res.data[0]) : [];

                keys.forEach((obj) => {
                    let type = 'string';
                    switch (obj) {
                        case 'interval':
                        case 'interval_date':
                        case 'interval_time':
                            type = 'date';
                            break;
                        case 'confirmation_code':
                        case 'interaction_time':
                        case 'btn':
                            type = 'number';
                            break;
                        default:
                            type = 'string';
                            break;
                    }
                    h.push({
                        align: 'left',
                        label: obj.charAt(0).toUpperCase() + obj.slice(1),
                        key: obj,
                        serviceKey: obj,
                        width: '20%',
                        sorted: NO_SORTED,
                        canSort: true,
                        type: type,
                    });
                });

                this.totalRecords = res.total;
                this.headers = h;
        
                res.data.forEach((element) => {
                    element.sortResult = element.result;
                    element.result = element.result == 'No Sale'
                        ? '<span class="badge badge-danger">No Sale</span>'
                        : element.result;
                });

                const params = this.getParams();        

                if (!!params.column && !!params.direction) {
                    const sortHeaderIndex = this.headers.findIndex(
                        (header) => header.serviceKey === params.column,
                    );
                    this.headers[sortHeaderIndex].sorted = params.direction;
                }
                this.tableDataset = res.data;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
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
        searchData({ startDate, endDate }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
            ].join('');
            window.location.href = `/reports/call_times_by_interval?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/reports/call_times_by_interval?page=${page}${this.filterParams}${this.sortParams}`;
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            let sortFlag = null;
            if (serviceKey == 'result') {
                sortFlag = 'sortResult';
            }

            this.tableDataset = arraySort(this.headers, this.tableDataset, sortFlag || serviceKey, index);

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

            return {
                startDate,
                endDate,
                column,
                direction,
                page,
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
.text-info {
  font-size: 2em;
}
</style>
