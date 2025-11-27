<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'JSON Documents', active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="tab-content">
        <div
          role="tabpanel"
          class="tab-pane active p-0"
        >
          <div class="animated fadeIn">
            <div class="card mb-0">
              <div class="card-header">
                <em class="fa fa-th-large" /> JSON Documents
                <div class="form-group form-inline pull-right mb-0">
                  <select
                    v-model="cType"
                    class="form-control"
                  >
                    <option :value="null">
                      All Document Types
                    </option>
                    <option
                      v-for="(dtype, d_I) in types"
                      :key="`dtype-${d_I}`"
                      :value="dtype.value"
                    >
                      {{ dtype.title }}
                    </option>
                  </select>
                  
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
                    :style="{marginTop:'2px'}"
                    @click="searchData"
                  >
                    <em class="fa fa-search" />
                  </button>
                </div>
              </div>
              <div class="card-body p-0">
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
                  
                  empty-table-message="No results were found."
                  no-bottom-padding
                >
                  <template
                    slot="document"
                    slot-scope="slotProps"
                  >
                    <textarea
                      readonly
                      rows="10"
                      class="form-control"
                      :style="{fontFamily: 'fixed',overflow:'scroll',maxHeight:'310px','margin':0}"
                      v-html="slotProps.row.document"
                    />
                  </template>
                </custom-table>
                <br v-if="numberPages > 1">
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

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'JsonDocumentBrowser',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
        Breadcrumb,
    },
    props: {
        types: {
            type: Array,
            default() {
                return [];
            },
        },
        selectedType: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            results: [],
            headers: [{
                label: 'Created',
                key: 'created_at',
                serviceKey: 'created_at',
                width: '15%',
                sorted: NO_SORTED,
            }, {
                label: 'Document Type',
                key: 'document_type',
                serviceKey: 'document_type',
                width: '20%',
                sorted: NO_SORTED,
            }, {
                label: 'Ref ID',
                key: 'ref_id',
                serviceKey: 'ref_id',
                width: '15%',
                sorted: NO_SORTED,
            }, {
                label: 'Document',
                key: 'document',
                serviceKey: 'document',
                width: '50%',
                sorted: NO_SORTED,
                slot: 'document',
            }],
            searchValue: this.getParams().search,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            cType: null,
        };
    },
    computed: {
        realSelectedType() {
            if (this.selectedType === 'null') {
                return '';
            }
            return this.selectedType;
        },
        dataGrid() {
            return this.results;
        },
        ...mapState({
            hasFlashMessage: (state) => state.session
              && Object.keys(state.session).length
              && state.session.hasOwnProperty('flash_message')
              && state.session.flash_message,
            flashMessage: (state) => state.session.flash_message,
        }),
    },
    mounted() {
        const params = this.getParams();
        const searchParam = `&type=${this.realSelectedType}&search=${params.search != null ? params.search : ''}`;
        const pageParam = params.page ? `&page=${params.page}` : '';

        if (this.realSelectedType !== '') {
            this.cType = this.selectedType;
        }

        axios.get(`/json/docs/list?${pageParam}${searchParam}`)
            .then((response) => {
                this.results = response.data.data.map((result) => this.getObject(result));
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
            })
            .catch((error) => {
                console.log(error);
            });
    },
    methods: {
        getObject(result) {
            return {
                ref_id: result.ref_id,
                created_at: result.created_at,
                document_type: result.document_type,
                document: `${JSON.stringify(result.document, null, '\t')}`,
            };
        },
        searchData() {
            window.location.href = `/json/docs?${this.getSearchParams()}`;
        },
        selectPage(page) {
            window.location.href = `/json/docs?page=${page}${this.getSearchParams()}`;
        },
        getSearchParams() {
            return `&type=${this.cType}&search=${this.searchValue != null ? this.searchValue : ''}`;
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
        getParams() {
            const url = new URL(window.location.href);
            const search = url.searchParams.get('search');
            const page = url.searchParams.get('page') || '';

            return {
                page,
                search,
            };
        },
    },
};
</script>

<style scoped>
.cell {
  overflow: hidden;
}
</style>
