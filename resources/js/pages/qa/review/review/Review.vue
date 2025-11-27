<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Quality Assurance', active: true},
        {name: 'Call Followups', active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <span class="mt-1 pull-left"><i class="fa fa-th-large" /> Call Followups</span>
            <div class="pull-right">
              <div class="form-inline">
                <div class="form-group">
                  <select
                    v-model="filter"
                    name="filter"
                    class="form-control form-control-sm align-text-bottom"
                  >
                    <option value="">
                      ALL
                    </option>
                    <option
                      v-for="f in filters"
                      :key="f.id"
                      :value="f.id"
                    >
                      {{ f.name }}
                    </option>
                  </select>
                  &nbsp;&nbsp;
                  <select
                    v-model="sort"
                    name="sorts"
                    class="form-control form-control-sm align-text-bottom"
                  >
                    <option
                      v-for="s in sorts"
                      :key="s.id"
                      :value="s.id"
                    >
                      {{ s.name }}
                    </option>
                  </select>
                </div>

                &nbsp;&nbsp;

                <div class="form-group">
                  <input
                    v-model="searchParam"
                    type="search"
                    name="search"
                    class="form-control"
                    placeholder="Confirmation Code"
                    @keyup.enter="search"
                  >
                </div>

                  &nbsp;&nbsp;
                  
                <div class="form-group">
                  <button
                    type="submit"
                    class="btn btn-success btn-sm mt-0"
                    @click="search"
                  >
                    <i
                      class="fa fa-filter"
                      aria-hidden="true"
                    /> 
                    Filter
                  </button>

                  &nbsp;&nbsp;
                  <button
                    class="btn btn-warning btn-sm mt-0"
                    @click="clearForm"
                  >
                    Reset
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body p-1">
            <div class="row">
              <div
                v-if="flashMessage"
                class="col-12 alert alert-success"
              >
                <span class="fa fa-check-circle" />
                <em> {{ flashMessage }}</em>
              </div>
            </div>
            <div class="row">
              <div class="col-12">
                <custom-table
                  :headers="headers"
                  :data-grid="flags"
                  :data-is-loaded="dataIsLoaded"
                  :total-records="totalRecords"
                >
                  <template
                    slot="type"
                    slot-scope="slotProps"
                  >
                    <div
                      v-if="slotProps.row.flag_reason_id === '00000000000000000000000000000000'"
                      class="badge badge-success"
                      title="Final Disposition Missing"
                    >
                      <i                  
                        class="fa fa-flag-checkered"
                        aria-hidden="true"
                      />
                      Final Disposition
                    </div>
                    <div
                      v-else-if="slotProps.row.flag_reason_id === '5dcdd6fb-faa2-4f3a-8e87-cc98db16b8b0'"
                      class="badge badge-danger"
                      title="Closed Call"
                    >
                      <i                  
                        class="fa fa-microphone-slash"
                        aria-hidden="true"
                      />
                      Closed Call
                    </div>
                    <div
                      v-else-if="slotProps.row.flag_reason_id === '0afb2c0a-ffd1-4488-a258-eb628679e228'"
                      class="badge badge-primary"
                      title="High Call Time"
                    >
                      <i                  
                        class="fa fa-clock-o"
                        aria-hidden="true"
                      />
                      High Call Time
                    </div>
                    <div
                      v-else
                      class="badge badge-warning"
                      title="Call Review"
                    >
                      <i                  
                        class="fa fa-exclamation-triangle"
                        aria-hidden="true"
                      />
                      Call Review
                    </div>
                  </template>

                  <template
                    slot="reason"
                    slot-scope="slotProps"
                  >
                    <span v-if="slotProps.row.flag_reason_id === '5dcdd6fb-faa2-4f3a-8e87-cc98db16b8b0'">
                      Call was Closed by TPV
                    </span>
                    <span v-else-if="slotProps.row.flag_reason_id === '00000000000000000000000000000000'">
                      No Disposition set by TPV
                    </span>
                    <span v-else-if="slotProps.row.description !== null && slotProps.row.description !== ''">
                      {{ slotProps.row.description }}
                    </span>
                    <span v-else>
                      --
                    </span>
                  </template>

                  <template
                    slot="buttons"
                    slot-scope="slotProps"
                  >
                    <a
                      class="btn btn-sm btn-primary"
                      target="qa_events"
                      :href="`/events/${slotProps.row.event_id}?qa_review=true&interaction=${slotProps.row.interaction_id}`"
                    >
                      <i
                        :class="`fa fa-eye`"
                      /> View
                    </a>
                  </template>
                </custom-table>
              </div>
            </div>
            <div class="row">
              <div class="col-12">
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
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'QaReview',
    components: {
        CustomTable,
        Pagination,
        Breadcrumb,
    },
    props: {
        flashMessage: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            dataIsLoaded: false,
            totalRecords: 0,
            fromItem: 1,
            fetchedFlags: [],
            filter: this.getParams().filter,
            sort: this.getParams().sorts,
            searchParam: this.getParams().search,
            activePage: 1,
            numberPages: 1,
            headers: [
                /* {
                    label: '#',
                    key: 'index',
                    
                },*/
                {
                    label: 'Type',
                    slot: 'type',
                    
                },
                {
                    label: 'Confirmation Code',
                    key: 'confirmation_code',
                    serviceKey: 'confirmation_code',
                    
                },
                {
                    label: 'Date',
                    key: 'created_at',
                    serviceKey: 'created_at',
                    
                },
                {
                    label: 'Brand',
                    key: 'brand_name',
                    serviceKey: 'brand_name',
                    
                },
                {
                    label: 'TPV Agent',
                    key: 'tpvAgent',
                    serviceKey: 'tpvAgent',
                    
                },
                {
                    label: 'Reason (if applicable)',
                    slot: 'reason',
                    key: 'reason',
                    serviceKey: 'reason',
                    
                },
                {
                    label: '',
                    slot: 'buttons',
                    
                },
            ],
            filters: [
                {
                    id: 'cr',
                    name: 'Call Reviews',
                },
                {
                    id: 'iv',
                    name: 'IVR Call Reviews',
                },
                {
                    id: 'fd',
                    name: 'Final Dispositions',
                },
                {
                    id: 'cc',
                    name: 'Closed Calls',
                },
                {
                    id: 'ch',
                    name: 'Calls Unusually long',
                },
            ],
            sorts: [
                {
                    id: 'created_at|desc',
                    name: 'by newest date',
                },
                {
                    id: 'created_at|asc',
                    name: 'by oldest date',
                },
                {
                    id: 'brand_name|asc',
                    name: 'brand name ascendant',
                },
                {
                    id: 'brand_name|desc',
                    name: 'brand name descendant',
                },
                {
                    id: 'confirmation_code|asc',
                    name: 'confirmation code ascendant',
                },
                {
                    id: 'confirmation_code|desc',
                    name: 'confirmation code descendant',
                },
                {
                    id: 'last_name|asc',
                    name: 'tpv last name ascdenant',
                },
                {
                    id: 'last_name|desc',
                    name: 'tpv last name descendant',
                },
                {
                    id: 'description|asc',
                    name: 'reason ascendant',
                },
                {
                    id: 'description|desc',
                    name: 'reason descendant',
                },
            ],
        };
    },
    computed: {
        filterParams() {
            return [
                this.getParams().filter ? `&filter=${this.getParams().filter}` : '',
                this.getParams().search ? `&search=${this.getParams().search}` : '',
            ].join('');
        },
        sortParam() {
            return this.getParams().sorts ? `&sorts=${this.getParams().sorts}` : '';
        },
        flags() {
            return this.fetchedFlags.map((flag, i) => ({
                ...flag,
                index: i + this.fromItem,
                tpvAgent: `${flag.first_name || ''} ${flag.last_name || ''}`,
            }));
        },
    },
    mounted() {
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';

        axios.get(`/qa_review/list?${pageParam}${this.filterParams}${this.sortParam}`)
            .then(({data}) => {
                this.fetchedFlags = data.data;
                this.dataIsLoaded = true;
                this.activePage = data.current_page;
                this.numberPages = data.last_page;
                this.totalRecords = data.total;
                this.fromItem = data.from;
            }).catch(console.log);
    },
    methods: {
      clearForm() {
            this.filter = '';
            this.sort = 'created_at|desc';
            this.searchParam = '';
            window.location.href = `/qa_review`;
        },
        selectPage(page) {
            window.location.href = `/qa_review?page=${page}${this.filterParams}${this.sortParam}`;
        },
        search() {
            const filters = [
                this.filter ? `&filter=${this.filter}` : '',
                this.sort ? `&sorts=${this.sort}` : '',
                this.searchParam ? `&search=${this.searchParam}` : '',
            ].join('');
            window.location.href = `/qa_review?${filters}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const page = url.searchParams.get('page') || '';
            const sorts = url.searchParams.get('sorts') || 'created_at|desc';
            const filter = url.searchParams.get('filter') || '';
            const search = url.searchParams.get('search') || '';

            return {
                sorts,
                page,
                filter,
                search,
            };
        },
    },
};
</script>
