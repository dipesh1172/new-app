<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        Home
      </li>
      <li class="breadcrumb-item active">
        Contract Generations
      </li>
    </ol>
    <div class="container-fluid">
      <div class="animated fadeIn">
        <div class="row page-buttons mb-2">
          <div class="col-md-12">
            <div class=" form-group form-inline pull-right">
              <custom-input
                :value="searchValue"
                placeholder="Search"
                name="search"
                :style="{ marginRight: 0 }"
                @onChange="updateSearchValue"
                @onKeyUpEnter="searchData"
              />
              <button
                class="btn btn-primary"
                @click="searchData"
              >
                <i class="fa fa-search" />
              </button>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Unprocessed Contracts
          </div>
          <div class="card-body">
            <div
              v-if="hasFlashMessage"
              class="alert alert-success"
            >
              <span class="fa fa-check-circle" /> <em>{{ flashMessage }}</em>
            </div>
            <custom-table
              :headers="headers"
              :data-grid="dataGrid"
              :data-is-loaded="dataIsLoaded"
              show-action-buttons
              has-action-buttons
              empty-table-message="No contracts were found."
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
</template>

<script>
import { status, statusLabel } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import Pagination from 'components/Pagination';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'ContractGenerations',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
    },
    props: {
        searchParameter: {
            type: String,
            default: '',
        },
        columnParameter: {
            type: String,
            default: '',
        },
        directionParameter: {
            type: String,
            default: '',
        },
        pageParameter: {
            type: String,
            default: '',
        },
        hasFlashMessage: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            dataObject: [],
            headers: [{
                label: 'Confirmation Code',
                key: 'confirmation_code',
                serviceKey: 'confirmation_code',
                width: '15%',
                sorted: NO_SORTED,
            }, {
                label: 'Customer Signature',
                key: 'customer_signature',
                serviceKey: 'customer_signature',
                width: '15%',
                sorted: NO_SORTED,
            }, {
                label: 'Sales Agent Signature',
                key: 'sales_agent_signature',
                serviceKey: 'sales_agent_signature',
                width: '15%',
                sorted: NO_SORTED,
            }],
            searchValue: this.searchParameter,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        dataGrid() {
            return this.dataObject;
        },
    },
    mounted() {
        const searchParam = this.searchParameter ? `&search=${this.searchParameter}` : '';
        const sortParams = !!this.columnParameter && !!this.directionParameter
            ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';

        if (!!this.columnParameter && !!this.directionParameter) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
            this.headers[sortHeaderIndex].sorted = this.directionParameter;
        }

        axios.get(
            `/contract-generations/list?${pageParam}${searchParam}${sortParams}`
        ).then((response) => {
            this.dataObject = response.data.data.map((data) => this.getObject(data));
            this.dataIsLoaded = true;
            this.activePage = response.data.current_page;
            this.numberPages = response.data.last_page;
        })
            .catch((error) => {
                console.log(error);
            });
    },
    methods: {
        getObject(data) {
            return {
                event_id: data.event_id,
                eztpv_id: data.eztpv_id,
                confirmation_code: data.confirmation_code,
                customer_signature: (data.customer_signature === 1)
                    ? 'Yes' : 'No',
                sales_agent_signature: (data.sales_agent_signature === 1)
                    ? 'Yes' : 'No',
            };
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/contract-generations?column=${serviceKey}&direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}`;
        },
        searchData() {
            window.location.href = `/contract-generations?${this.getSearchParams()}${this.getSortParams()}`;
        },
        selectPage(page) {
            window.location.href = `/contract-generations?page=${page}${this.getSortParams()}${this.getSearchParams()}`;
        },
        getSearchParams() {
            return this.searchValue ? `&search=${this.searchValue}` : '';
        },
        getSortParams() {
            return !!this.columnParameter && !!this.directionParameter
                ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
    },
};
</script>

<style lang="scss" scoped>

</style>
