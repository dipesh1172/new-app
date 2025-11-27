<template>
  <div id="main-app">
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Product-Disassociated Contracts', url: '/reports/product_disassociated_contracts', active: true}
      ]"
    />

    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form
          :on-submit="filterData"
          :initial-values="initialSearchValues"
          :hide-search-box="true"
          :include-date-range="false"
          include-brand
        >
          <div class="form-group pull-right m-0">
            <a
              :href="exportUrl"
              class="btn btn-primary m-0"
              :class="{'disabled': !contracts.length}"
            >
              <i
                class="fa fa-download"
                aria-hidden="true"
              /> Export Data
            </a>
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid mt-3">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card mt-4">
          <div class="card-header">
            <i class="fa fa-th-large" /> Report: Product-Disassociated Contracts
          </div>
          <div class="row card-body">
            <div class="col-md-12">
              <custom-table
                :headers="headers"
                :data-grid="contracts"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No disassociated contracts were found."
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
import { formArrayQueryParam } from 'utils/stringHelpers';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'ProductDisassociatedContracts',
    components: {
        CustomTable,
        SearchForm,
        Pagination,
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
            contracts: [],
            headers: (() => {
            const commonHeaderforproductdis = {
                width: '10%',
                canSort: true,
                type: 'string',
                sorted: NO_SORTED
            };
                return [
                 {
                label: 'Brand',
                key: 'brand_name',
                serviceKey: 'brand_name',
                ...commonHeaderforproductdis
            },
            {
                label: 'Product',
                key: 'product_name',
                serviceKey: 'product_name',
                ...commonHeaderforproductdis
            },
            {
                label: 'Disassociation Date',
                key: 'product_deleted_at',
                serviceKey: 'product_deleted_at',
                align: 'center',
                type: 'date',
                ...commonHeaderforproductdis
            },
            {
                label: 'Contract Edit URL',
                key: 'contract_edit_url',
                serviceKey: 'contract_edit_url',
                align: 'center',
                width: '60%',
                canSort: false
            },
            {
                label: 'Actions',
                key: 'product_reprocess_url',
                serviceKey: 'product_reprocess_url',
                align: 'center',
                canSort: false
            },
                ];
             })(),
            dataIsLoaded: false,
            displaySearchBar: false,
            activePage: 1,
            numberPages: 1,
            totalRecords: 0,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/list_product_disassociated_contracts?${this.filterParams}${this.sortParams}&csv=true`;
        },
        sortParams() {
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        filterParams() {
            const params = this.getParams();
            return [
                params.brand ? formArrayQueryParam('brand', params.brand) : '',
            ].join('');
        },
        initialSearchValues() {
            const params = this.getParams();
            return {
                brand: params.brand,
            };
        },
    },
    created() {
        this.$store.commit('setBrands', this.brands);
    },
    mounted() {
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        axios
            .get(
                `/reports/list_product_disassociated_contracts?${pageParam}${this.filterParams}${this.sortParams}`,
            )
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;
                this.contracts = res.data.map((element) => {
                    element.contract_edit_url = `<a href="/brand_contracts/${element.contract_id}/edit" target="_blank">${element.contract_file}</a>`;
                    element.product_reprocess_url = `<a class="btn btn-primary" href="/reports/reprocessEztpvs/${element.product_id}">Reprocess affected EzTPVs</a>`;
                    return element;
                });
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
                this.totalRecords = res.total;
            })
            .catch(console.log);
    },
    methods: {
        filterData({ brand, state }) {
            const filters = [brand ? formArrayQueryParam('brand', brand) : ''].join(
                '',
            );
            window.location.href = `/reports/product_disassociated_contracts?${filters}`;
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.contracts = arraySort(
                this.headers,
                this.contracts,
                serviceKey,
                index,
            );

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        selectPage(page) {
            window.location.href = `/reports/product_disassociated_contracts?page=${page}${this.sortParams}${this.filterParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const brand = url.searchParams.getAll('brand[]');
            const page = url.searchParams.get('page');
            const column = url.searchParams.get('column');
            const direction = url.searchParams.get('direction');
            return {
                brand,
                column,
                direction,
                page,
            };
        },
    },
};
</script>
