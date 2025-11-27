<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item active">
        Issues
      </li>
    </ol>

    <div class="container-fluid">
      <div class="row" />
      <div class="row">
        <div class="card col-12">
          <div class="card-body">
            <div
              v-if="hasFlashMessage"
              class="alert alert-success"
            >
              <span class="fa fa-check-circle" />
              <em>{{ flashMessage }}</em>
            </div>
            <form class="form-inline pull-right">
              <button
                v-if="refs.length > 0"
                type="button"
                class="btn btn-primary pull-left mr-2"
                @click="markResolved()"
              >
                <i class="fa fa-paw" /> Mark Selected Resolved
              </button>
              <input
                name="_token"
                type="hidden"
                :value="csrf_token"
              >
              <select
                name="type"
                class="form-control"
                title="Error Type"
              >
                <option
                  value="all"
                  :selected="errorType === 'all'"
                >
                  All Errors
                </option>
                <option
                  value="404"
                  :selected="errorType == '404'"
                >
                  Page Not Found Errors
                </option>
                <option
                  value="500"
                  :selected="errorType == '500'"
                >
                  Server Errors
                </option>
              </select>
              <select
                name="perPage"
                class="form-control"
                title="Results Per Page"
              >
                <option
                  value="15"
                  :selected="perPage == 15"
                >
                  15
                </option>
                <option
                  value="30"
                  :selected="perPage == 30"
                >
                  30
                </option>
                <option
                  value="50"
                  :selected="perPage == 50"
                >
                  50
                </option>
                <option
                  value="100"
                  :selected="perPage == 100"
                >
                  100
                </option>
              </select>
              <input
                type="text"
                class="form-control"
                placeholder="Reference ID"
                name="ref"
              >
              <button
                type="submit"
                class="btn btn-primary"
              >
                <i
                  class="fa fa-search"
                  aria-hidden="true"
                />
              </button>
            </form>
            <h2 class="pull-left">
              Site Errors
            </h2>
            <div class="table-responsive">
              <p
                v-if="totalRecords"
                align="right"
              >
                Total Records: {{ totalRecords }}
              </p>
              
              <table
                class="table table-sm"
                style="overflow-x: scroll;"
              >
                <thead>
                  <tr>
                    <th>
                      <template v-if="errors.length > 0">
                        <a
                          href="#"
                          @click="selectAll"
                        >
                          <i class="fa fa-check-square" />
                        </a>
                      </template>
                    </th>
                    <th>ID</th>
                    <th style="width: 60%;max-width:60%;">
                      Details
                    </th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!dataIsLoaded">
                    <td
                      colspan="5"
                      class="text-center"
                    >
                      <span class="fa fa-spinner fa-spin fa-2x" />
                    </td>
                  </tr>
                  <tr v-if="dataIsLoaded && !errors.length">
                    <td
                      colspan="5"
                      class="text-center"
                    >
                      No errors were found
                    </td>
                  </tr>
                  <tr
                    v-for="error in errors"
                    :key="error.ref_id"
                  >
                    <td>
                      <input
                        v-model="refs"
                        type="checkbox"
                        :value="error.ref_id"
                      >
                    </td>
                    <td>
                      {{ formatTime(error.created_at) }}
                      <a :href="`?ref=${error.ref_id}`">{{ error.ref_id }}</a>
                    </td>
                    <td class="can-have-long-text">
                      <strong>URL:</strong>
                      {{ error.document['url'] }}
                      <br>
                      <strong>File:</strong>
                      {{ error.document['file'] }}
                      <br>
                      <strong>Line:</strong>
                      {{ error.document['line'] }}
                      <strong>Code:</strong>
                      {{ error.document['code'] }}
                      <br>
                      <strong>Message:</strong>
                      {{ error.document['message'] }}
                      <br>
                      <strong>Trace:</strong>
                      <textarea
                        :id="error.id"
                        class="collapse form-control"
                        :value="error.document['trace']"
                      />
                    </td>
                    <td />
                    <td>
                      <!-- <button
                        class="btn btn-primary"
                        type="button"
                        data-toggle="collapse"
                        :data-target="`#${error.id}`"
                        aria-expanded="false"
                        :aria-controls="`#${error.id}`"
                      >
                        <i
                          class="fa fa-paw"
                          aria-hidden="true"
                        /> 
                        Trace
                      </button>-->
                      <a
                        class="btn btn-primary"
                        :href="`?ref=${error.ref_id}`"
                      ><i class="fa fa-eye" /> View</a>
                      <button
                        class="btn btn-warning"
                        type="button"
                        @click="markResolved(error.ref_id)"
                      >
                        <i class="fa fa-paw" /> Mark Resolved
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <pagination
              v-if="dataIsLoaded"
              :active-page="activePage"
              :number-pages="numberPages"
              :displayed-pages="displayedPages"
              @onSelectPage="selectPage"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import Pagination from 'components/Pagination';

export default {
    name: 'Errors',
    components: {
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
            errors: [],
            dataIsLoaded: false,
            totalRecords: 0,
            activePage: 1,
            numberPages: 1,
            displayedPages: 10,
            csrf_token: window.csrf_token,
            refs: [],
        };
    },
    computed: {
        errorType() {
            const p = this.getParams();
            if (p.type == null || p.type === undefined) {
                return 'all';
            }
            return p.type;
        },
        perPage() {
            const p = this.getParams();
            if (p.perPage == null || p.perPage === undefined) {
                return 15;
            }
            return p.perPage;
        },
    },
    mounted() {
        document.title += ' Detected Site Errors';

        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';
        const perPageParam = params.perPage ? `&perPage=${params.perPage}` : '';
        
        this.displayedPages = this.$mq == 'sm' ? 5 : 10;

        axios
            .get(`/errors?${pageParam}${perPageParam}&type=${this.errorType}`)
            .then((response) => {
                const res = response.data;

                this.dataIsLoaded = true;
                this.errors = res.data;
                this.totalRecords = res.total;
                this.activePage = res.current_page;
                this.numberPages = res.last_page;
            })
            .catch((e) => console.log(e));
    },
    methods: {
        selectPage(page) {
            const params = this.getParams();
            const perPageParam = params.perPage ? `&perPage=${params.perPage}` : '';
            window.location.href = `/errors?page=${page}${perPageParam}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const page = url.searchParams.get('page');
            const perPage = url.searchParams.get('perPage');
            const type = url.searchParams.get('type');
            return {
                page,
                perPage,
                type,
            };
        },
        formatTime(time) {
            return moment(time).format('dddd, MMMM Do YYYY, h:mm:ss a');
        },
        selectAll() {
            this.refs = [];
            for (let i = 0, len = this.errors.length; i < len; i += 1) {
                this.refs.push(this.errors[i].ref_id);
            }
        },
        markResolved(id) {
            this.dataIsLoaded = false;
            axios.post('/errors/resolve', {
                ref: id === undefined ? this.refs : id,
            }).then((res) => {
                if (res.data.error !== false) {
                    throw new Error(res.data.error);
                }
                window.location.reload();
            }).catch((e) => {
                this.dataIsLoaded = true;
                alert(e);
            });
        },
    },
};
</script>

<style scoped>
.can-have-long-text {
  overflow-x: hidden;
  max-width: 400px;
}
</style>
