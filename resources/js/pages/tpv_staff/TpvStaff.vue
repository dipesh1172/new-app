<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'TPV Staff', active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header tpv-staff-header">
            <i class="fa fa-th-large" /> TPV Staff
            <a
              :href="createUrl"
              class="btn btn-success btn-sm pull-right"
              title="Add New TPV Staff"
            >
              <i class="fa fa-plus" /> 
            </a>
            <a
              href="/agent/groups"
              class="btn btn-primary btn-sm pull-right mr-2"
            >
              <i class="fa fa-users" />
              Groups
            </a>
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
              <div class="col-md-11">
                <div class="form-group form-inline p-1 mb-0">
                  <custom-input
                    :value="searchValue"
                    placeholder="Search"
                    name="search"
                    :style="{ marginRight: 0 }"
                    @onChange="updateSearchValue"
                    @onKeyUpEnter="searchData"
                  />
                  <select
                    v-model="statusValue"
                    class="form-control"
                  >
                    <option value="active">
                      Active
                    </option>
                    <option value="inactive">
                      Inactive
                    </option>
                    <option value="all">
                      All
                    </option>
                  </select>
                  <select
                    v-model="roleValue"
                    class="form-control"
                  >
                    <option :value="null">
                      Any Role
                    </option>
                    <option
                      v-for="(role, role_i) in roles"
                      :key="`role-${role_i}`"
                      :value="role.id"
                    >
                      {{ role.name }}
                    </option>
                  </select>
                  <select
                    v-model="groupValue"
                    class="form-control"
                  >
                    <option :value="null">
                      Any Group
                    </option>
                    <option value="is_empty">
                      No Group
                    </option>
                    <option
                      v-for="(group, group_i) in groups"
                      :key="`group-${group_i}`"
                      :value="group.id"
                    >
                      {{ group.group }}
                    </option>
                  </select>
                  <button
                    class="btn btn-primary"
                    @click="searchData"
                  >
                    <i class="fa fa-search" />
                  </button>
                </div>
              </div>
              <div
                v-if="totalRecords > 0"
                class="col-md-1"
              >
                <br>
                Total: {{ totalRecords }}
              </div>
            </div>
          </div>

          <custom-table
            :headers="headers"
            :data-grid="dataGrid"
            :data-is-loaded="dataIsLoaded"
            show-action-buttons
            has-action-buttons
            empty-table-message="No TPV staff were found."
            :no-bottom-padding="true"
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
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'TpvStaff',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
        Breadcrumb,
    },
    props: {
        createUrl: {
            type: String,
            required: true,
        },
        searchParameter: {
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
        roles: {
            type: Array,
            required: true,
        },
        groups: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            tpvStaff: [],
            headers: [
                {
                    label: 'Status',
                    key: 'statusLabel',
                    serviceKey: 'status',
                    width: '15%',
                    sorted: NO_SORTED,
                    type: 'number',
                    canSort: true,
                },
                {
                    label: 'Created',
                    key: 'created_at',
                    serviceKey: 'created_at',
                    width: '15%',
                    sorted: NO_SORTED,
                    type: 'date',
                    canSort: true,
                },
                {
                    label: 'Payroll ID',
                    key: 'payroll_id',
                    serviceKey: 'payroll_id',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    label: 'First Name',
                    key: 'first_name',
                    serviceKey: 'first_name',
                    width: '15%',
                    sorted: NO_SORTED,
                    type: 'string',
                    canSort: true,
                },
                {
                    label: 'Last Name',
                    key: 'last_name',
                    serviceKey: 'last_name',
                    width: '15%',
                    sorted: NO_SORTED,
                    type: 'string',
                    canSort: true,
                },
                {
                    label: 'Language',
                    key: 'language',
                    serviceKey: 'language',
                    sorted: NO_SORTED,
                    type: 'string',
                    canSort: true,
                },
                {
                    label: 'Group',
                    key: 'group',
                    serviceKey: 'group',
                    sorted: NO_SORTED,
                    type: 'string',
                    canSort: true,
                },
            ],
            statusValue: 'active',
            searchValue: this.searchParameter,
            groupValue: null,
            roleValue: null,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            totalRecords: 0,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        dataGrid() {
            return this.tpvStaff;
        },
    },
    mounted() {
        const params = this.getParams();
        
        this.statusValue = params.status;
        this.roleValue = params.role;
        this.groupValue = params.group;
        this.searchValue = params.search;
        this.activePage = params.page;

        if (this.roleValue == null) {
            this.headers.push({
                label: 'Role',
                key: 'role_name',
                serviceKey: 'role_name',
                width: '15%',
                sorted: NO_SORTED,
                type: 'string',
                canSort: true,
            });
        }

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === params.column,
            );
            if (this.headers[sortHeaderIndex]) {
                this.headers[sortHeaderIndex].sorted = params.direction;
            }
        }

        axios
            .get(`/list/tpv_staff?${this.getPageParams()}${this.getSearchParams()}${this.getSortParams()}`)
            .then((response) => {
                this.tpvStaff = response.data.data.map(this.getTPVStaffObject);
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
                this.totalRecords = response.data.total;
            })
            .catch((error) => console.log(error));
    },
    methods: {
        getTPVStaffObject(tpvStaff) {
            return {
                id: tpvStaff.id,
                status: tpvStaff.status,
                statusLabel: statusLabel[tpvStaff.status],
                created_at: tpvStaff.created_at,
                first_name: tpvStaff.first_name,
                last_name: tpvStaff.last_name,
                role_name: tpvStaff.role_name ? tpvStaff.role_name : '--',
                language: tpvStaff.language ? tpvStaff.language : 'English',
                payroll_id: tpvStaff.payroll_id || '--',
                group: tpvStaff.group || '--',
                buttons: [
                    {
                        type: 'view',
                        url: `/tpv_staff/${tpvStaff.id}/time`,
                        icon: 'clock-o',
                    },
                    {
                        type: 'edit',
                        url: `/tpv_staff/${tpvStaff.id}/edit?tpvStaff=true`,
                    },
                    {
                        type: 'status',
                        url: `/tpv_staff/${tpvStaff.id}/${
                            tpvStaff.status === status.ACTIVE ? 'delete' : 'active'
                        }`,
                        messageAlert: `Are you sure want to ${
                            tpvStaff.status === status.ACTIVE ? 'delete' : 'active'
                        } this user?`,
                    },
                ],
            };
        },
        sortData(serviceKey, index) {      
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            // this.tpvStaff = arraySort(this.headers, this.tpvStaff, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
            this.selectPage(this.activePage);
        },
        searchData() {
            window.location.href = `/tpv_staff?${this.getSearchParams()}${this.getSortParams()}`;
        },
        selectPage(page) {
            window.location.href = `/tpv_staff?page=${page}${this.getSortParams()}${this.getSearchParams()}`;
        },
        getSearchParams() {
            return `&search=${this.searchValue}&status=${this.statusValue}&role=${this.roleValue}&group=${this.groupValue}`; 
        },
        getSortParams() {
            return `&column=${this.column}&direction=${this.direction}`;
               
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
            const search = url.searchParams.get('search') || '';
            const page = url.searchParams.get('page');
            const status = url.searchParams.get('status') || 'active';
            let role = url.searchParams.get('role');
            if (role === 'null') {
                role = null;
            }
            let group = url.searchParams.get('group');
            if (group === 'null') {
                group = null;
            }

            return {
                column,
                direction,
                page,
                search,
                status,
                role,
                group,
            };
        },
    },
};
</script>

<style lang="scss" scoped>
.tpv-staff-header {
  clear: none;
  float: left;
}
.pagination li {
  padding: 0 !important;
}
</style>
