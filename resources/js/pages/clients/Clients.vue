<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Clients', url: '/clients', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <brands-nav />

      <div class="tab-content mb-4">
        <div
          role="tabpanel"
          class="tab-pane active p-0"
        >
          <div class="animated fadeIn">
            <div class="card mb-0">

              <div class="card-header d-md-flex align-items-center justify-content-end">
                <a
                  href="/clients/create"
                  class="btn btn-success m-0 mr-2"
                ><i
                  class="fa fa-plus"
                  aria-hidden="true"
                /> Add Client</a> 
                            
                <div class="d-flex">
                  <custom-input
                    :value="searchValue"
                    placeholder="Search"
                    name="search"
                    class="m-0"
                    @onChange="updateSearchValue"
                    @onKeyUpEnter="searchData"
                  />

                  <button 
                    class="btn btn-primary m-0" 
                    @click="searchData"
                  >
                    <i class="fa fa-search" />
                  </button>
                </div>
              </div>

              <div class="card-body p-0">
                <div 
                  v-if="flashMessage" 
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" /> <em>{{ flashMessage }}</em>
                </div>
                <custom-table
                  :headers="headers"
                  :data-grid="clients"
                  :data-is-loaded="dataIsLoaded"
                  show-action-buttons
                  has-action-buttons
                  empty-table-message="No clients were found."
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
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import CustomInput from 'components/CustomInput';
import Breadcrumb from 'components/Breadcrumb';
import BrandsNav from './BrandsNav';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'ClientsIndex',
    components: {
        CustomTable,
        Pagination,
        BrandsNav,
        CustomInput,
        Breadcrumb,
    },
    props: {
        flashMessage: {
            type: String,
            default: null,
        },
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
    },
    data() {
        return {
            headers: [{
                label: 'Status',
                key: 'status',
                serviceKey: 'deleted_at',
                width: '25%',
                canSort: true,
            }, {
                label: 'Client Name',
                key: 'name',
                serviceKey: 'name',
                width: '25%',
                canSort: true,
            }, {
                label: 'Phone',
                key: 'phone',
                serviceKey: 'phone',
                width: '25%',
                canSort: true,
            }, {
                label: 'Email',
                key: 'email',
                serviceKey: 'email',
                width: '25%',
                canSort: true,
            }],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            searchValue: this.getParams().search,
            clients: [],
        };
    },
    mounted() {
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';
        const sortParams = !!this.columnParameter && !!this.directionParameter
            ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';

        if (!!this.columnParameter && !!this.directionParameter) {
            const sortHeaderIndex = this.headers.findIndex(header => header.serviceKey === this.columnParameter);
            this.headers[sortHeaderIndex].sorted = this.directionParameter;
        }
        
        axios.get(`/clients/list?${this.getSearchParams()}${pageParam}${sortParams}`).then((response) => {
            const res = response.data;
            this.clients = res.data.map((client) => {
                client.name += '<br>';
                client.brands.forEach((c) => {
                    client.name += `<a href="/brands/${c.id}/edit" class="ml-2">
                          ${c.name}
                        </a><br>`;
                });
                client.status = client.deleted_at
                    ? '<span class="badge badge-danger">Inactive</span>'
                    : '<span class="badge badge-success">Active</span>';
                client.buttons = [
                    {
                        type: 'edit',
                        url: `/clients/${client.id}/edit`,
                        buttonSize: 'medium',
                        showButton: !this.readOnly,
                    },
                    {
                        type: 'delete',
                        url: `/clients/${client.id}/delete`,
                        messageAlert: 'Are you sure want to delete this client?',
                        buttonSize: 'medium',
                    },
                ];
                return client;
            });
            this.dataIsLoaded = true;
            this.activePage = res.current_page;
            this.numberPages = res.last_page;
            this.total = res.total;
        }).catch(console.log);
    },
    methods: {
        getSearchParams() {
            return this.searchValue ? `&search=${this.searchValue}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
        searchData() {
            window.location.href = `/clients?${this.getSearchParams()}`;
        },
        selectPage(page) {
            window.location.href = `/clients?page=${page}${this.getSortParams()}${this.getSearchParams()}`;
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/clients?column=${serviceKey}&direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}`;
        },
        getSortParams() {
            return !!this.columnParameter && !!this.directionParameter
                ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        getParams() {
            return {
                search: this.searchValue,
                column: this.columnParameter,
                direction: this.directionParameter,
                page: this.pageParameter,
            };
        },
    },
};
</script>