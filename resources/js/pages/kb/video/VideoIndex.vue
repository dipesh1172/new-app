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
        Video Knowledge Base
      </li>
    </ol>
    <div class="container-fluid">
      <ul class="nav nav-tabs">
        <li class="nav-item">
          <a
            class="nav-link"
            href="/kb/AllPages"
          >Knowledge Base</a>
        </li>
        <li class="nav-item">
          <a
            class="nav-link active"
            href="/kb/video"
          >Videos</a>
        </li>
      </ul>
      <div class="tab-content">
        <div
          role="tabpanel"
          class="tab-pane active"
        >
          <div class="animated fadeIn">
            <div class="row page-buttons">
              <div class="col-md-6" />
              <div class="col-md-6">
                <a
                  v-if="createPermit"
                  id="newEntryBtn"
                  class="btn btn-primary btn-sm float-right mb-2"
                  href="/kb/video/upload"
                >New Video Knowledge Base Entry</a>
              </div>
            </div>
            <div class="card">
              <div class="card-body p-0">
                <custom-table
                  :headers="headers"
                  :data-grid="videos"
                  :data-is-loaded="dataIsLoaded"
                  :total-records="totalRecords"
                  empty-table-message="No videos were found."
                  :has-action-buttons="true"
                  :show-action-buttons="createPermit && modifyPermit"
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
import Pagination from 'components/Pagination';
import CustomTable from 'components/CustomTable';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'VideoIndex',
    components: {
        CustomTable,
        Pagination,
    },
    props: {
        createPermit: { type: Boolean, default: false },
        modifyPermit: { type: Boolean, default: false },
        loggedUserId: { type: String, default: '' },
    },
    data() {
        return {
            videos: [],
            headers: [
                {
                    align: 'left',
                    label: 'Title',
                    key: 'title',
                    serviceKey: 'title',
                    width: '25%',
                    canSort: true,
                    sort: NO_SORTED,
                    type: 'string',
                },
                {
                    align: 'left',
                    label: 'Status',
                    key: 'status',
                    serviceKey: 'status',
                    width: '25%',
                    canSort: true,
                    sort: NO_SORTED,
                    type: 'string',
                },
                {
                    align: 'left',
                    label: 'Created At',
                    key: 'created_at',
                    serviceKey: 'created_at',
                    width: '25%',
                    canSort: true,
                    sort: NO_SORTED,
                    type: 'date',
                },
                {
                    align: 'left',
                    label: 'Updated At',
                    key: 'updated_at',
                    serviceKey: 'updated_at',
                    width: '25%',
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
        document.title += ' Video Knowledge Base';

        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        axios
            .get(`/kb/video/list_videos_home?${pageParam}${this.sortParams}`)
            .then(({ data }) => {
                const res = data;
                this.videos = res.data.map((video) => {
                    video.title = video.status == 'Conversion Complete'
                        ? `<a href="/kb/video/play/${video.slug}">${video.title}</a>`
                        : video.title;

                    video.sortTitle = video.title;
                    video.sortStatus = video.status;

                    switch (video.status) {
                        case 'Conversion Complete':
                            video.status = 'Ready';
                            break;
                        case 'Conversion in Progress':
                            video.status = 'Converting';
                            break;
                        default:
                            video.status = 'Unknown';
                            break;
                    }
          
                    if (video.author_id == this.loggedUserId) {
                        video.buttons = [
                            {
                                type: 'edit',
                                url: `/kb/video/edit/${video.id}`,
                                buttonSize: 'medium',
                            },
                            {
                                type: 'delete',
                                counterName: 'Delete',
                                url: `/kb/video/delete/${video.id}`,
                                messageAlert: 'Are you sure you want to delete this video?',
                                buttonSize: 'medium',
                            },
                        ];
                    }

                    return video;
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
            window.location.href = `/kb/video?page=${page}${this.sortParams}`;
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            let sortkey = serviceKey;
            if (serviceKey == 'status') {
                sortkey = 'sortStatus';
            }
            else if (serviceKey == 'title') {
                sortkey = 'sortTitle';
            }

            this.videos = arraySort(this.headers, this.videos, sortkey, index);

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
