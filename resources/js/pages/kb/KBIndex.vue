<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item">
        <a href="/kb">KB</a>
      </li>
      <li class="breadcrumb-item active">
        All KB Pages
      </li>
    </ol>
    <div class="container-fluid">
      <ul class="nav nav-tabs">
        <li class="nav-item">
          <a
            class="nav-link active"
            href="/kb/AllPages"
          >Knowledge Base</a>
        </li>
        <li class="nav-item">
          <a
            class="nav-link"
            href="/kb/video"
          >Videos</a>
        </li>
      </ul>

      <div class="tab-content mb-4">
        <div
          role="tabpanel"
          class="tab-pane active p-0"
        >
          <div class="animated fadeIn">
            <div class="row page-buttons">
              <div class="col-md-6" />
              <div class="col-md-6">
                <div class="form-group pull-right ml-1 mb-0">
                  <!-- <a class="btn btn-primary btn-sm pull-right mb-2" href="/redbook">Redbook</a>  -->
                  <a
                    id="newEntryBtn"
                    class="btn btn-primary btn-sm mt-2 pull-right"
                    href="/kb/create"
                  >New Knowledge Base Entry</a>
                </div>
              </div>
            </div>
            <div class="card">
              <div class="card-body p-0 pt-1">
                <div
                  v-if="hasFlashMessage"
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" />
                  <em>{{ flashMessage }}</em>
                </div>

                <custom-table
                  :headers="headers"
                  :data-grid="articles"
                  :data-is-loaded="dataIsLoaded"
                  :total-records="totalRecords"
                  empty-table-message="No articles were found."
                  :has-action-buttons="true"
                  :show-action-buttons="true"
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
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'KBIndex',
    components: {
        CustomTable,
        Pagination,
    },
    props: {
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
            articles: [],
            headers: [
                {
                    align: 'left',
                    label: 'ID',
                    key: 'id',
                    serviceKey: 'id',
                    width: '20%',
                    canSort: true,
                    sort: NO_SORTED,
                    type: 'number',
                },
                {
                    align: 'left',
                    label: 'Title',
                    key: 'title',
                    serviceKey: 'title',
                    width: '20%',
                    canSort: true,
                    sort: NO_SORTED,
                    type: 'string',
                },
                {
                    align: 'left',
                    label: 'Views',
                    key: 'views',
                    serviceKey: 'views',
                    width: '20%',
                    canSort: true,
                    sort: NO_SORTED,
                    type: 'number',
                },
                {
                    align: 'center',
                    label: 'Created At',
                    key: 'created_at',
                    serviceKey: 'created_at',
                    width: '20%',
                    canSort: true,
                    sort: NO_SORTED,
                    type: 'date',
                },
                {
                    align: 'center',
                    label: 'Updated At',
                    key: 'updated_at',
                    serviceKey: 'updated_at',
                    width: '20%',
                    canSort: true,
                    sort: NO_SORTED,
                    type: 'date',
                },
            ],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            totalRecords: 0,
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
    },
    mounted() {
        document.title += ' Knowledge Base';

        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';
    
        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        axios
            .get(`/kb/list_kb?${pageParam}${this.sortParams}`)
            .then(({ data }) => {
                const res = data;
                this.articles = res.data.map((a) => {
                    a.buttons = [
                        {
                            type: 'edit',
                            url: `/kb/edit/${a.id}`,
                            buttonSize: 'medium',
                        },
                        {
                            type: 'delete',
                            counterName: 'Delete',
                            url: `/kb/del/${a.id}`,
                            messageAlert:
                'Are you sure you want to delete this Knowledge Base?',
                            buttonSize: 'medium',
                        },
                    ];
                    return a;
                });
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
                this.totalRecords = res.total;
                this.dataIsLoaded = true;
            })
            .catch(console.log);
    },
    methods: {
        selectPage(page) {
            window.location.href = `/kb/AllPages?page=${page}${this.sortParams}`;
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.articles = arraySort(this.headers, this.articles, serviceKey, index);

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
