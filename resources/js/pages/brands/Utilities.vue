<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: `${brand.name} Utilities`, active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active p-0"
    >
      <div class="card mb-0">
        <div class="card-header utility-header">
          <i class="fa fa-th-large" /> Utilities
          <a
            :href="createUrl"
            class="btn btn-success btn-sm m-0 pull-right"
          ><i
            class="fa fa-plus"
            aria-hidden="true"
          /> Add Utility</a>
        </div>
        <div class="card-body p-1">
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
            :no-bottom-padding="numberPages <= 1"
            empty-table-message="No utilities were found."
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
  </layout>
</template>

<script>
import { status, statusLabel } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import Layout from './edit/Layout';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'BrandUtilities',
    components: {
        CustomTable,
        Pagination,
        Layout,
    },
    props: {
        brand: {
            type: Object,
            required: true,
            default: () => {}, 
        },
        createUrl: {
            type: String,
            required: true,
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
            utilities: [],
            headers: [{
                label: 'Status',
                key: 'statusLabel',
                serviceKey: 'status',
                width: '15%',
                sorted: NO_SORTED,
            }, {
                label: 'Utility Name',
                key: 'utilityName',
                serviceKey: 'name',
                width: '30%',
                sorted: NO_SORTED,
            }, {
                label: 'State',
                key: 'stateName',
                serviceKey: 'state_name',
                width: '30%',
                sorted: NO_SORTED,
            }, {
                label: 'Utility Label',
                key: 'utilityLabel',
                serviceKey: 'label',
                width: '30%',
                sorted: NO_SORTED,
            }, {
                label: 'Utility External ID',
                key: 'utilityExternalID',
                serviceKey: 'label',
                width: '30%',
                sorted: NO_SORTED,
            }],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        dataGrid() {
            return this.utilities;
        },
    },
    mounted() {
        const brandIdParam = `brand_id=${this.brand.id}`;
        const sortParams = !!this.columnParameter && !!this.directionParameter
            ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';

        if (!!this.columnParameter && !!this.directionParameter) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
            this.headers[sortHeaderIndex].sorted = this.directionParameter;
        }

        axios.get(`/list/utilities_by_brand?${brandIdParam}${pageParam}${sortParams}`)
            .then((response) => {
                this.utilities = response.data.data.map((utility) => this.getUtilityObject(utility));
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
            })
            .catch((error) => {
                console.log(error);
            });
    },
    methods: {
        getUtilityObject(utility) {
            return {
                id: utility.id,
                statusLabel: statusLabel[utility.deleted_at ? 0 : 1],
                status: utility.deleted_at ? 0 : 1,
                utilityName: utility.name,
                utilityLabel: utility.utility_label,
                utilityExternalID: utility.utility_external_id,
                stateName: utility.state_name,
                buttons: [{
                    type: 'edit',
                    url: `/brands/${this.brand.id}/utilities/${utility.id}/editUtility`,
                }, {
                    type: 'status',
                    url: utility.deleted_at
                        ? `/brands/enableUtility/${this.brand.id}/${utility.brand_utility_id}`
                        : `/brands/disableUtility/${this.brand.id}/${utility.brand_utility_id}`,
                    messageAlert: `Are you sure want to ${utility.deleted_at ? 'enable' : 'disable'} this utility?`,
                }],
            };
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/brands/${this.brand.id}/utilities?
                column=${serviceKey}&direction=${labelSort}${this.getPageParams()}`;
        },
        selectPage(page) {
            window.location.href = `/brands/${this.brand.id}/utilities?page=${page}${this.getSortParams()}`;
        },
        getSortParams() {
            return !!this.columnParameter && !!this.directionParameter
                ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        },
        getPageParams() {
            return !this.activePage ? `&page=${this.activePage}` : '';
        },
    },
};
</script>

<style lang="scss" scoped>
    .utility-header {
        clear: none;
        float: left;
    }
</style>
