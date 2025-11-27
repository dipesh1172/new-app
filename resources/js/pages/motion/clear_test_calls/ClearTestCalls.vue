<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Motion', active: true},
        {name: 'Clear Test Calls', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="card">
        <div class="card-header">
          <i class="fa fa-th-large" /> Clear Test Calls
        </div>        
        <div class="card-body">
          <div class="row">
            <div class="col-md-12">
              <div
                v-if="hasFlashMessage"
                class="alert alert-success"
              >
                <span class="fa fa-check-circle" /><em> {{ flashMessage }}</em>
              </div>
            </div>
          </div>
          
          <form
            class="container"
            method="POST"
            @submit.prevent="requestCC"
          >
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="company_nm">Company: </label>                  
                  <select
                    ref="company_nm"
                    v-model="company_nm"
                    class="form-control ml-2"
                    name="company_nm"
                    type="text"
                  >
                  <option
                      selected="selected"
                      value
                    >
                      Select A Company...
                    </option>
                    <option
                      value="focusCompany"
                    >
                      **Use For Focus Only Companies **
                    </option>
                    <option
                      v-for="company in companies"
                      :value= "company.company_prefix"
                    >
                      {{ company.company_name }}
                    </option>
                  </select>
                </div>
                <label for="confirmation_codes">Confirmation Codes (separate with commas)</label>                  
                <input
                  ref="confirmation_codes"
                  v-model="confirmation_codes"
                  class="form-control ml-2"
                  name="confirmation_codes"
                  type="text"
                >

                <label for="unique_ids">Unique IDs (separate with commas)</label>                  
                <input
                  ref="unique_ids"
                  v-model="unique_ids"
                  class="form-control ml-2"
                  name="unique_ids"
                  type="text"
                >

                <label for="ani_numbers">ANI Numbers (separate with commas)</label>                  
                <input
                  ref="ani_numbers"
                  v-model="ani_numbers"
                  class="form-control ml-2"
                  name="ani_numbers"
                  type="text"
                >

                <button
                  ref="search_button"
                  name="search_button"
                  value="search"
                  class="btn btn-lg btn-success ml-2"
                  type="submit"
                  :class="{'disabled': unique_ids.length === 0 && ani_numbers.length === 0 && !confirmation_codes.trim()}"
                >

                  <span
                    class="fa fa-search"
                    aria-hidden="true"
                  /> 
                  Search
                </button>
                <button
                  ref="reset_button"
                  name="reset_button"
                  value="reset"
                  class="btn btn-lg btn-success ml-2"
                  type="reset"
                >
                <span
                    class="fa fa-undo"
                    aria-hidden="true"
                  /> 
                  Reset
                </button>
              </div>
                
              <div class="col-md-6">
                <a
                  href="/motion/clear_test_calls?mode=testvendor"
                  class="btn btn-lg btn-warning pull-right"
                >
                  <span
                    class="fa fa-search"
                    aria-hidden="true"
                  /> 
                  All Test Vendor Records
                </a>
              </div>
            </div>
          </form>
        
          <div
            v-if="loading"
            class="row"
          >
            <div class="col-md-12">
              <span class="fa fa-5x fa-spinner fa-spin" /> Loading...
            </div>
          </div>
          <div class="row mt-4">
            <div class="col-md-12">
              <div
                v-if="events.length"
                class="table-responsive"
              >
                <custom-table
                  :headers="headers"
                  :data-grid="events"
                  :data-is-loaded="dataIsLoaded"
                  :total-records="totalRecords"
                  empty-table-message="No calls were found."
                />
                <form
                  action="/motion/clear_test_calls/delete_test_calls"
                  method="POST"
                >
                  <input
                    type="hidden"
                    name="_token"
                    :value="csrfToken"
                  >
                  <input
                    :value="confirmation_codes"
                    type="hidden"
                    class="form-control ml-2"
                    name="confirmation_codes"
                  >                  
                  <input
                    :value="unique_id"
                    type="hidden"
                    class="form-control ml-2"
                    name="unique_id"
                  >                  
                  <input
                    :value="company_nm"
                    type="hidden"
                    class="form-control ml-2"
                    name="company_nm"
                  >                  
                  <button
                    name="delete_button"
                    value="delete"
                    type="submit"
                    class="btn btn-lg btn-danger delete_button"
                  >
                    <i
                      class="fa fa-trash-o"
                      aria-hidden="true"
                    /> 
                    Delete All
                  </button>   
                </form>
              </div>
            </div>
          </div>
          <div v-if="!events.length && dataIsLoaded">
            <div class="text-center">
              <p>No calls were found.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import CustomTable from 'components/CustomTable';
import Breadcrumb from 'components/Breadcrumb';
import { mapState } from 'vuex';

export default {
    name: 'ClearTestCalls',
    components: {
        CustomTable,
        Breadcrumb,
    },
    data() {
        return {
            loading: false,
            mode: 'search',
            csrfToken: window.csrf_token,
            events: [],
            dataIsLoaded: false,
            totalRecords: 0,
            headers: [{
                align: 'left',
                label: 'Date',
                key: 'created_at',
                serviceKey: 'created_at',
                width: '20%',
            }, {
                align: 'left',
                label: 'Confirmation Code',
                key: 'confirmation_code',
                serviceKey: 'confirmation_code',
                width: '20%',
            }, {
                align: 'left',
                label: 'Brand',
                key: 'brand_name',
                serviceKey: 'brand_name',
                width: '20%',
            }, {
                align: 'left',
                label: 'Unique ID',
                key: 'unique_id',
                serviceKey: 'unique_id',
                width: '20%',
            }, {
                align: 'left',
                label: 'ANI Number',
                key: 'ani_number',
                serviceKey: 'ani_number',
                width: '20%',
            }],
            confirmation_codes: '',
            unique_ids: '',
            ani_numbers: '',
            company_nm: '',
            companies: [],
            messageRecording: '',
        };
    },
    computed: mapState({
        session: 'session',
        hasFlashMessage: (state) => Object.keys(state.session).length && state.session.hasOwnProperty('flash_message') && state.session.flash_message,
        flashMessage: (state) => state.session.flash_message,
    }),
    mounted() {
        const params = this.getParams();
        this.getCompanies();
        this.mode = params.mode;

        if (this.mode === 'testvendor') {
            this.requestCC(false);
        }
    },
    methods: {
        getParams() {
            const url = new URL(window.location.href);
            const mode = url.searchParams.get('mode') || 'search';
            return {mode};
        },
        requestCC(bb) {
            if (bb !== false) {
                this.mode = 'search';
            }

            let searchV = this.$refs.confirmation_codes.value;
            let searchU = this.$refs.unique_ids.value;
            let searchA = this.$refs.ani_numbers.value;
            let searchC = this.$refs.company_nm.value;

            if (this.mode === 'search') {
                $(this.$refs.search_button)
                    .find('i')
                    .first()
                    .hide();

                $(this.$refs.search_button)
                    .prepend('<span class="fa fa-spinner fa-spin" />');
            }
            else {
                searchV = 'testvendor';
            }

            if ((searchC.length === 0) && (this.mode !== 'testvendor')) {
              alert("Please Select A Company");
              return; 
            }

            searchC = this.$refs.company_nm.value+"TPV";

            this.loading = true;
            axios.post('/motion/clear_test_calls/list_clear_test_calls', {
              confirmation_codes: searchV,
              unique_ids: searchU,
              ani_numbers: searchA,
              company_nm: searchC,
              _token: this.csrfToken,
            }).then((response) => {//console.log(response);
                const codes = [];
                this.events = response.data.map((event) => {
                    codes.push(event.confirmation_code);
                    
                    return {
                        created_at: this.$moment(event.created_at).format('MM-DD-YYYY'),
                        confirmation_code: `${event.confirmation_code}`,
                        brand_name: event.brand_name,
                        unique_id: `${event.unique_id ? event.unique_id : '--'}`,
                        ani_number: `${event.ani_number ? event.ani_number : '--'}`,
                    };
                });
                this.confirmation_codes = codes.join(',');
                this.dataIsLoaded = true;
                this.totalRecords = this.events.length;
                this.loading = false;
                if (this.mode === 'search') {
                    $(this.$refs.search_button)
                        .find('i')
                        .first()
                        .show();
                    $(this.$refs.search_button)
                        .find('span')
                        .first()
                        .remove();
                }
            }).catch((e) => {
                console.log(e);
                this.loading = false;
            });
        },
        getCompanies() {
          axios.post('/motion/clear_test_calls/client_test_calls', {
              _token: this.csrfToken,
            }).then((response) => {//console.log(response.data);
                this.companies = response.data.map((company) => {
                    return {
                        company_name: `${company.name ? company.name : ''}`,
                        company_prefix: `${company.answernet_api_prefix ? company.answernet_api_prefix : ''}`,
                    };
                });
            }).catch((e) => {
                console.log(e);
                this.loading = false;
            });          
        },
    },
};
</script>
