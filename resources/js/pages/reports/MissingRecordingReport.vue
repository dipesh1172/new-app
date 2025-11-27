<template>
  <div id="main-app">
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Missing Recording Report', active: true}
      ]"
    />

    <div class="container-fluid">
      <div class="animated fadeIn">
        <div class="row mt-5">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <span class="pull-left">
                  <i class="fa fa-th-large" /> Report: Missing Recordings
                </span>
                <form
                  method="POST"
                  :action="exportUrl"
                >
                  <input
                    type="hidden"
                    name="_token"
                    :value="csrf_token"
                  >
                  <button
                    class="btn btn-info btn-sm pull-right"
                    type="submit"
                    style="color: white;"
                  >
                    <i class="fa fa-download" />  Data Export
                  </button>
                </form>
              </div>
              <div class="row card-body">
                <div class="col-md-12">
                  <custom-table
                    :headers="headers"
                    :data-grid="missing"
                    :data-is-loaded="dataIsLoaded"
                    :total-records="totalRecords"
                    :has-action-buttons="true"
                    :show-action-buttons="true"
                    :empty-table-message="`No missing recordings were found.`"
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
  </div>
</template>

<script>
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'ReportContract',
    components: {
        CustomTable,
        Pagination,
        Breadcrumb,
    },
    data() {
        return {
            totalRecords: 0,
            missing: [],
            headers: [
                {
                    label: 'Date',
                    align: 'left',
                    key: 'created_at',
                    serviceKey: 'created_at',
                    width: '20%',
                    canSort: false,
                    type: 'date',
                },
                {
                    label: 'Brand',
                    key: 'brand',
                },
                {
                    label: 'Confirmation Code',
                    key: 'confirmation_code',
                },
                {
                    label: 'Missing',
                    key: 'count_missing',
                },
                {
                    label: 'Result',
                    key: 'result',
                },
            ],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        exportUrl() {
            return `/reports/list_missing_recordings?export=true`;
        },
        csrf_token() {
            return window.csrf_token;
        },
    },
    mounted() {
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        axios
            .post(`/reports/list_missing_recordings?${pageParam}`)
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;
                this.missing = res.data.map((i) => ({
                    created_at: i.created_at,
                    event_id: i.event_id,
                    confirmation_code: i.confirmation_code,
                    result: i.result,
                    brand: i.brand_name,
                    count_missing: i.count_missing,
                    buttons: [
                        {
                            type: 'view',
                            label: 'View',
                            url: `/events/${i.event_id}`,
                            buttonSize: 'medium',
                        },
                    ],
                }));

                this.totalRecords = res.total;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
            })
            .catch(console.log);
    },
    methods: {
        selectPage(page) {
            window.location.href = `/reports/missing_recordings?page=${page}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const page = url.searchParams.get('page');

            return {
                page,
            };
        },
    },
};
</script>
