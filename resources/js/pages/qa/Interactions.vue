<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        Home
      </li>
      <li class="breadcrumb-item active">
        Interactions
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
          <div class="card-header interactions-header">
            <i class="fa fa-th-large" /> Interactions
          </div>
          <div class="card-body horizontal-scroll">
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
              empty-table-message="No interactions were found."
              show-action-buttons
              has-action-buttons
              :total-records="totalRecords"
              class="mb-3"
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
import { result } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import Pagination from 'components/Pagination';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'Interactions',
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
            totalRecords: 0,
            interactions: [],
            headers: [{
                label: 'Monitored',
                key: 'monitored',
                serviceKey: 'monitored',
                width: '100px',
                sorted: NO_SORTED,
            }, {
                label: 'TPV Agent Name',
                key: 'tpvAgentName',
                serviceKey: 'first_name',
                width: '155px',
                sorted: NO_SORTED,
            }, {
                label: 'TPV ID',
                key: 'tpvId',
                serviceKey: 'username',
                width: '86px',
                sorted: NO_SORTED,
            }, {
                label: 'Center',
                key: 'center',
                serviceKey: 'call_center',
                width: '90px',
                sorted: NO_SORTED,
            }, {
                label: 'Confirmation',
                key: 'confirmation',
                serviceKey: 'confirmation_code',
                width: '165px',
                sorted: NO_SORTED,
            }, {
                label: 'Date',
                key: 'date',
                serviceKey: 'created_at',
                width: '96px',
                sorted: NO_SORTED,
            }, {
                label: 'Brand',
                key: 'brand',
                serviceKey: 'brand_name',
                width: '85px',
                sorted: NO_SORTED,
            }, {
                label: 'Channel',
                key: 'channel',
                serviceKey: 'channel',
                width: '97px',
                sorted: NO_SORTED,
            }, {
                label: 'Disposition',
                key: 'lastResult',
                serviceKey: 'result',
                width: '118px',
                align: 'center',
                sorted: NO_SORTED,
            }, {
                label: '',
                key: 'button',
                sorted: NO_SORTED,
                hideSort: true,
                innerHtml: true,
            }],
            searchValue: this.searchParameter,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        dataGrid() {
            return this.interactions;
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

        axios.get(`/list/interactions?${pageParam}${searchParam}${sortParams}`)
            .then((response) => {
                const keys = {};
                this.interactions = response.data.data.filter((i) => {
                    if (keys[i.id]) {
                        return false;
                    }
 
                    keys[i.id] = true;
                    return true;
                    
                }).map(this.getInteractionObject);
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
                this.totalRecords = response.data.total;
            })
            .catch(console.log);
    },
    methods: {
        getViewButton(interaction) {
            return `<button 
                    class="btn btn-sm btn-info" 
                    onClick="openTranscript('${interaction.id}', '${interaction.event_id}');">
                    View
                    </button>`;
        },
        getInteractionObject(interaction) {
            const firstName = interaction.first_name ? interaction.first_name : '';
            const lastName = interaction.last_name ? interaction.last_name : '';

            const agentName = !!firstName && !!lastName
                ? `${firstName} ${lastName}` : '--';
            const monitored = interaction.monitored ? 'Y' : 'N';
                
            return {
                id: interaction.id,
                monitored: monitored,
                tpvAgentName: agentName,
                tpvId: interaction.username
                    ? interaction.username : '--',
                supervisor: '--',
                center: interaction.call_center
                    ? interaction.call_center : '--',
                station: interaction.station_id
                    ? interaction.station_id : '--',
                confirmation: interaction.confirmation_code
                    ? interaction.confirmation_code : '--',
                date: interaction.created_at,
                brand: interaction.brand_name
                    ? interaction.brand_name : '--',
                state: '--',
                channel: interaction.channel,
                tpvInProgress: '--',
                reserved: '--',
                acw: '--',
                aht: '--',
                lastResult: interaction.result
                    ? interaction.result : '--',
                button: this.getViewButton(interaction),
                buttons: [{
                    type: 'monitor',
                    url: `/interactions/monitor/${interaction.id}`,
                    messageAlert: 'Are you sure want to mark this interaction as monitored?',
                    disabled: monitored,
                }],
            };
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/interactions?column=${serviceKey}
                    &direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}`;
        },
        searchData() {
            window.location.href = `/interactions?${this.getSearchParams()}${this.getSortParams()}`;
        },
        selectPage(page) {
            window.location.href = `/interactions?page=${page}${this.getSortParams()}${this.getSearchParams()}`;
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
.interactions-header {
    clear: none;
    float: left;
}

.horizontal-scroll {
    overflow-y: auto;
    overflow-x: scroll;
}
</style>
