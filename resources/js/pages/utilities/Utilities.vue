<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Utilities', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="card">
        <div class="card-header utility-header">
          <i class="fa fa-th-large" /> Utilities
          <a
            :href="createUrl"
            class="btn btn-success btn-sm pull-right"
            title="Add Utility"
          ><i
            class="fa fa-plus"
            aria-hidden="true"
          /><span class="sr-only">Add Utility</span></a>
        </div>
        <div class="card-body p-0">
          <div
            v-if="hasFlashMessage"
            class="alert alert-success"
          >
            <span class="fa fa-check-circle" />
            <em>{{ flashMessage }}</em>
          </div>
          <div class="row">
            <!--
              <div class="col-3">
              
                <select
                v-model="filterStatus"
                class="form-control"
              >
                <option value="active">
                  Active
                </option>
                <option value="inactive">
                  Inactive
                </option>
              </select>
            </div>
            -->
            <div class="col-3">
              <select
                v-model="filterState"
                class="form-control"
              >
                <option :value="null">
                  Select State
                </option>
                <optgroup label="United States">
                  <option
                    v-for="(state, state_i) in states['1']"
                    :key="`us_state_${state_i}`"
                    :value="state.id"
                    :disabled="state.status == 0"
                  >
                    {{ state.name }}
                  </option>
                </optgroup>
                <optgroup label="Canada">
                  <option
                    v-for="(state, state_i) in states['2']"
                    :key="`ca_state_${state_i}`"
                    :value="state.id"
                    :disabled="state.status == 0"
                  >
                    {{ state.name }}
                  </option>
                </optgroup>
              </select>
            </div>
            <div class="col-3">
              <input
                v-model="filterSearch"
                type="text"
                class="form-control"
                placeholder="Search"
                @keyup.enter="selectPage(activePage)"
              >
            </div>
            <div class="col-3">
              <button
                type="button"
                class="btn btn-primary"
                title="Filter Results"
                @click="selectPage(activePage)"
              >
                <span class="fa fa-search" />
              </button>
            </div>
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
  </div>
</template>

<script>
import { status, statusLabel } from 'utils/constants';
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'Utilities',
    components: {
        CustomTable,
        Pagination,
        Breadcrumb,
    },
    props: {
        createUrl: {
            type: String,
            required: true,
        },
        hasFlashMessage: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
        states: {
            type: Object,
            default() {
                return {
                    '1': [],
                    '2': [],
                };
            },
        },
    },
    data() {
        return {
            filterState: null,
            filterStatus: 'active',
            filterSearch: '',
            utilities: [],
            headers: [
                {
                    label: 'Status',
                    key: 'statusLabel',
                    serviceKey: 'status',
                    width: '15%',
                    sorted: NO_SORTED,
                    type: 'string',
                    canSort: true,
                },
                {
                    label: 'Utility Name',
                    key: 'name',
                    serviceKey: 'name',
                    width: '30%',
                    sorted: NO_SORTED,
                    type: 'string',
                    canSort: true,
                },
                {
                    label: 'State',
                    key: 'state_name',
                    serviceKey: 'state_name',
                    width: '30%',
                    sorted: NO_SORTED,
                    type: 'string',
                    canSort: true,
                },
            ],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        dataGrid() {
            return this.utilities;
        },
    },
    mounted() {
        document.title += ' Utilities';
        const params = this.getParams();
        if (params.state != null) {
            this.filterState = params.state;
        }
        if (params.status != null) {
            this.filterStatus = params.status;
        }
        if (params.search != null) {
            this.filterSearch = params.search;
        }

        const sortParams = `&column=${params.column}&direction=${params.direction}&status=${params.status}&state=${params.state}&search=${params.search}`;
            
        const pageParam = params.page ? `&page=${params.page}` : '';

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        axios
            .get(`/list/utilities?${pageParam}${sortParams}`)
            .then((response) => {
                const res = response.data;
                this.utilities = res.data.map((utility) => this.getUtilityObject(utility));
                this.dataIsLoaded = true;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
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
                name: utility.name,
                state_name: utility.state_name,
                buttons: [
                    {
                        type: 'edit',
                        url: `/utilities/${utility.id}/editUtility`,
                    },
                ],
            };
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            /* this.utilities = arraySort(
                this.headers,
                this.utilities,
                serviceKey,
                index,
            );*/

            // Updating values for page render and export
            this.column = serviceKey == 'status' ? 'deleted_at' : serviceKey;
            this.direction = this.headers[index].sorted;

            this.selectPage(this.activePage);
        },
        selectPage(page) {
            window.location.href = `/utilities?page=${page}${this.getSortParams()}`;
        },
        getSortParams() {
            
            return `&column=${this.column}&direction=${this.direction}&status=${this.filterStatus}&state=${this.filterState}&search=${this.filterSearch}`;
                
        },
        getPageParams() {
            return !this.activePage ? `&page=${this.activePage}` : '';
        },
        getParams() {
            const url = new URL(window.location.href);
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page');
            const state = url.searchParams.get('state') === 'null' ? null : url.searchParams.get('state');
            const search = url.searchParams.get('search') === 'null' ? '' : url.searchParams.get('search');
            const status = url.searchParams.get('status') === 'null' ? null : url.searchParams.get('status');

            return {
                column,
                direction,
                page,
                state,
                status,
                search,
            };
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
