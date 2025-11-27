<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Agents', active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <nav-agents />
      <div class="tab-content">
        <div
          role="tabpanel"
          class="tab-pane active"
        >
          <div class="animated fadeIn">
            <div class="row page-buttons">

                <div class="col-md-12 d-md-flex justify-content-end">
                    <div class="d-flex mr-2">
                        <custom-input
                          :value="searchValue"
                          placeholder="Search"
                          class="m-0"
                          name="search"
                          @onChange="updateSearchValue"
                          @onKeyUpEnter="searchData"
                        />
                        <div class="input-group-append">
                         <button
                           class="btn btn-primary"
                           @click="searchData"
                         >
                           <i class="fa fa-search" />
                         </button>
                        </div>
                    </div>
                    <a
                      :href="`${createUrl}?agents=true`"
                      class="btn btn-success m-0"
                    >
                      <i class="fa fa-plus" /> Add Agent
                    </a>
                </div>

            </div>
            <div class="card">
              <div class="card-header agents-header">
                <i class="fa fa-th-large" /> Agents
              </div>
              <div class="card-body">
                <div
                  v-if="hasFlashMessage"
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" />
                  <em>{{ flashMessage }}</em>
                </div>
                <custom-table
                  :headers="headers"
                  :data-grid="dataGrid"
                  :data-is-loaded="dataIsLoaded"
                  show-action-buttons
                  has-action-buttons
                  empty-table-message="No agents were found."
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
  </div>
</template>

<script>
import { status, statusLabel } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';
import { mapState } from 'vuex';
import NavAgents from './NavAgents';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'Agents',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
        Breadcrumb,
        NavAgents,
    },
    props: {
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
    },
    data() {
        return {
            agents: [],
            headers: [{
                label: 'Status',
                key: 'statusLabel',
                serviceKey: 'status',
                width: '15%',
                canSort: true,
                type: 'string',
            }, {
                label: 'Created',
                key: 'created',
                serviceKey: 'created_at',
                width: '15%',
                canSort: true,
                type: 'date',
            }, {
                label: 'First Name',
                key: 'firstName',
                serviceKey: 'first_name',
                width: '15%',
                canSort: true,
                type: 'string',
            }, {
                label: 'Last Name',
                key: 'lastName',
                serviceKey: 'last_name',
                width: '15%',
                canSort: true,
                type: 'string',
            }, {
                label: 'Group',
                key: 'group',
                serviceKey: 'group',
                width: '15%',
                canSort: true,
                type: 'string',
            }, {
                label: 'Payroll ID',
                key: 'payroll_id',
                serviceKey: 'payroll_id',
                canSort: true,
                type: 'string',
            }],
            searchValue: this.getParams().search,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        dataGrid() {
            return this.agents;
        },
        ...mapState({
            hasFlashMessage: (state) => state.session && Object.keys(state.session).length && state.session.hasOwnProperty('flash_message') && state.session.flash_message,
            flashMessage: (state) => state.session.flash_message,
        }),
    },
    mounted() {
        const params = this.getParams();
        const searchParam = params.search ? `&search=${params.search}` : '';
        const pageParam = params.page ? `&page=${params.page}` : '';
        const sortParams = !!params.column && !!params.direction
            ? `&column=${params.column}&direction=${params.direction}`
            : '';

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === params.column);
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        axios.get(`/list/agents?${pageParam}${searchParam}${sortParams}`)
            .then((response) => {
                this.agents = response.data.data.map((agent) => this.getAgentObject(agent));
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
            })
            .catch((error) => {
                console.log(error);
            });
    },
    methods: {
        getSortParams() {
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/agents?column=${serviceKey}&direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}`;
        },
        getAgentObject(agent) {
            return {
                id: agent.id,
                status: agent.status,
                statusLabel: statusLabel[agent.status],
                created: agent.created_at,
                firstName: agent.first_name,
                lastName: agent.last_name,
                group: agent.group || '--',
                payroll_id: agent.payroll_id || '--',
                buttons: [{
                    type: 'edit',
                    url: `/tpv_staff/${agent.id}/edit?agent=true`,
                }, {
                    type: 'status',
                    url: `/tpv_staff/agents/${agent.id}/${agent.status === status.ACTIVE
                        ? 'delete'
                        : 'active'}`,
                    messageAlert: `Are you sure want to ${agent.status === status.ACTIVE
                        ? 'delete'
                        : 'active'} this agent?`,
                }],
            };
        },
        searchData() {
            window.location.href = `/agents?${this.getSearchParams()}`;
        },
        selectPage(page) {
            window.location.href = `/agents?page=${page}${this.getSearchParams()}`;
        },
        getSearchParams() {
            return this.searchValue ? `&search=${this.searchValue}` : '';
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
        getParams() {
            const url = new URL(window.location.href);
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const search = url.searchParams.get('search');
            const page = url.searchParams.get('page') || '';

            return {
                column,
                direction,
                page,
                search,
            };
        },
    },
};
</script>

<style lang="scss" scoped>
.agents-header {
  clear: none;
  float: left;
}
</style>
