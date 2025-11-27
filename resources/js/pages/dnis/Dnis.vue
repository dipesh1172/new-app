<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'DNIS', url: '/dnis', active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="row page-buttons mb-2">
          <div class="col-md-12 d-md-flex justify-content-end">
            
            <div class="form-inline mr-2">
              <div class="input-group">
                <select id="select_brand_id" class="form-control">
                  <option
                    selected
                    value="0"
                  >
                    Select brand name
                  </option>
                  <option
                    v-for="brand in brands"
                    :key="brand.brand_id"
                    :value="brand.brand_id"
                    :selected="searchBrandParameter == brand.brand_id"
                  >
                    {{ brand.name }}
                  </option>
                </select>
                <!--<input id="input-search" placeholder="Search" name="search" type="text" class="form-control"> -->
                <div class="input-group-append">
                	<button class="btn btn-primary" @click="searchBrand">
		                <i class="fa fa-filter" aria-hidden="true"/>
		                Filter
		            </button>
                </div>
              </div>
              
            </div>

            <div class="form-group m-0">
              <a
                :href="createUrl"
                class="btn btn-success m-0"
              ><i
                data-v-f39e0d14=""
                aria-hidden="true"
                class="fa fa-plus"
              /> Add Dnis (Twilio)</a>

              <a
                :href="createUrlExt"
                class="btn btn-success m-0"
              ><i
                data-v-f39e0d15=""
                aria-hidden="true"
                class="fa fa-plus"
              /> Add Dnis (Other)</a>
            </div>

          </div>
        </div>
        <div class="card">
          <div class="card-header dni-header">
            <i class="fa fa-th-large" /> Dnis
          </div>
          <div class="card-body p-0">
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
              show-action-buttons
              has-action-buttons
              empty-table-message="No DNIS were found."
              :total-records="totalRecords"
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
import Breadcrumb from 'components/Breadcrumb';
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default
{
    name: 'Dnis',
    components:
        {
            CustomTable,
            Pagination,
            Breadcrumb,
        },
    props:
        {
            createUrl:
            {
                type: String,
                required: true,
            },
            createUrlExt:
            {
                type: String,
                required: true,
            },
            columnParameter:
            {
                type: String,
                default: '',
            },
            directionParameter:
            {
                type: String,
                default: '',
            },
            pageParameter:
            {
                type: String,
                default: '',
            },
            hasFlashMessage:
            {
                type: Boolean,
                default: false,
            },
            flashMessage:
            {
                type: String,
                default: null,
            },
            searchBrandParameter:
            {
                type: String,
                default: '',
            },
        },
    data() {
        return {
            totalRecords: 0,
            dnis: [],
            brands: [],
            headers: [
                {
                    label: 'Type',
                    key: 'type',
                    serviceKey: 'dnis_type',
                    width: '20%',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Dnis',
                    key: 'dnis',
                    serviceKey: 'dnis',
                    width: '20%',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Channel',
                    key: 'channel',
                    serviceKey: 'channel',
                    width: '20%',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Brand',
                    key: 'brand',
                    serviceKey: 'name',
                    width: '20%',
                    sorted: NO_SORTED,
                },
                {
                    label: 'States',
                    key: 'states',
                    serviceKey: 'states',
                    innerHtml: true,
                    width: '20%',
                    sorted: NO_SORTED,
                },
                {
                    label: 'Script',
                    key: 'script',
                    serviceKey: 'script',
                    width: '20%',
                    sorted: NO_SORTED,
                }],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed:
        {
            dataGrid() {
                return this.dnis;
            },
        },
    mounted() {
        console.log(this.searchBrandParameter);

        const sortParams = !!this.columnParameter && !!this.directionParameter
            ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';
        const searchParams = this.searchBrandParameter ? `&brand_id=${this.searchBrandParameter}` : '';

        if (!!this.columnParameter && !!this.directionParameter) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
            this.headers[sortHeaderIndex].sorted = this.directionParameter;
        }

        const listURL = `/list/dnis?${pageParam}${sortParams}${searchParams}`;
        console.log(listURL);

        axios.get(listURL)
            .then((response) => {
                this.dnis = response.data.data.map((dni) => this.getDniObject(dni));
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
                this.totalRecords = response.data.total;
            })
            .catch((error) => {
                console.log(error);
            });

        axios.get('/dnis/list_brands')
            .then((response) => {

                response.data.forEach((brand) => this.brands.push(brand));
            })
            .catch((error) => {
                console.log(error);
            });
    },
    methods:
        {
            renderStates(states) {
                let html = '';
                const array = states.state;
                for (const key in array) {
                    let hours = '';

                    if (array[key].length > 0) {
                        for (const day in array[key][0]) {
                            if (day != 'Exceptions') {
                                hours += `${day} - ${array[key][0][day].open} to ${array[key][0][day].close}<br />`;
                            }
                        }
                    }

                    if (hours.trim()
                        .length == 0) {
                        hours = 'No Hours of Operation are configured.';
                    }

                    html += `<a href="#" data-toggle="tooltip" data-html="true" title="${hours}">${key}</a><br />`;
                }

                return html;
            },
            getDniObject(dni) {
                const phoneArray = !!dni.dnis && dni.dnis.replace('+1', '').split('');
                phoneArray && phoneArray.splice(3, 0, '-') && phoneArray.splice(7, 0, '-');
                const phoneNumber = phoneArray ? phoneArray.join('') : '--';
                const states = dni.config && dni.config
                    ? this.renderStates(dni.config)
                    : '--';
                const obj = {
                    id: dni.id,
                    brand_id: dni.brand_id,
                    type: dni.dnis_type,
                    dnis: phoneNumber + (dni.deleted_at !== null ? '<br><span class="badge badge-danger">** DISABLED**</span>' : ''),
                    brand: dni.name,
                    states: states,
                    channel: (dni.channel) ? dni.channel : '--',
                    script: ((dni.title) ? dni.title : '--'),
                    buttons: [{
                        type: 'edit',
                        url: `/dnis/${dni.id}/edit`,
                    }],
                };
                if (dni.deleted_at == null) {
                    obj.buttons.push({type: 'disable', url: `/dnis/${dni.id}/disable`});
                }
                else {
                    obj.buttons.push({
                        type: 'enable', label: 'Enable', url: `/dnis/${dni.id}/enableIt`,
                    });
                }
                return obj;
            },
            sortData(serviceKey, index) {
                const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
                window.location.href = `/dnis?column=${serviceKey}&direction=${labelSort}${this.getPageParams()}`;
            },
            selectPage(page) {
                const searchParams = this.searchBrandParameter ? `&brand_id=${this.searchBrandParameter}` : '';
                window.location.href = `/dnis?page=${page}${this.getSortParams()}${searchParams}`;
            },
            getSortParams() {
                return !!this.columnParameter && !!this.directionParameter
                    ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
            },
            getPageParams() {
                return this.activePage ? `&page=${this.activePage}` : '';
            },
            searchBrand() {
                const select_v = document.getElementById('select_brand_id').value;
                if (select_v != 0) { window.location.href = `/dnis?brand_id=${select_v}`; }
            },
        },
};

</script>

<style lang="scss" scoped>
    .dni-header {
        clear: none;
        float: left;
    }

</style>
