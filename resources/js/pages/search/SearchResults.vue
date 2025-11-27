<template>
  <div>
    <ol class="breadcrumb">
      <li
        v-if="!dataIsLoading"
        class="breadcrumb-item active"
      >
        Searching
        <span class="fa fa-spinner fa-spin" />
      </li>
      <li
        v-else
        class="breadcrumb-item active"
      >
        {{ query ? `Search Results for &#8220;${query}&#8221` : `Search` }}
      </li>
    </ol>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-body">
            <div
              v-if="hasFlashMessage"
              class="alert alert-success"
            >
              <span class="fa fa-check-circle" />
              <em>{{ flashMessage }}</em>
            </div>

            <form
              method="GET"
              action="/search"
            >
              <div class="input-group mb-3">
                <input
                  type="text"
                  class="form-control"
                  name="query"
                  placeholder="Search"
                  :value="query"
                  aria-label="Search"
                >
                <div class="input-group-append">
                  <button
                    class="btn btn-outline-primary"
                    type="submit"
                  >
                    <i class="fa fa-search" />
                  </button>
                </div>
              </div>
            </form>

            <template v-if="query && !results.length">
              <div class="alert alert-info">
                There are no results to display.
              </div>
            </template>
            <template v-else-if="query">
              Showing {{ (( perPage * page) - perPage) + 1 }} to {{ ( perPage * page) > total ? total : perPage * page }} of {{ total }} Results
              <br>
              <hr>

              <template v-if="type == 'StatsProduct'">
                <div
                  v-for="result in results"
                  :key="result['event_id']"
                >
                  <a
                    :href="`/events/${result['event_id']}`"
                    class="lead"
                  >Event #{{ result['confirmation_code'] }}</a>
                  <br>
                  <p>
                    <strong>Created:</strong>
                    {{ result['event_created_at'] }}
                    <strong>Brand:</strong>
                    {{ result['brand_name'] }}
                    <strong>Sales Agent:</strong>
                    {{ result['sales_agent_name'] }}
                    <br>
                    <template
                      v-if="result.hasOwnProperty('company_name') && result['company_name'] !== null && result['company_name'].trim() !== ''"
                    >
                      <div>
                        <strong>Company Name:</strong>
                        {{ result['company_name'] }}
                      </div>
                    </template>
                    <template v-else>
                      <strong>Bill Name:</strong>
                      {{ result['bill_first_name'] }} {{ result['bill_middle_name'] }} {{ result['bill_last_name'] }}
                    </template>
                    <strong>Auth Name:</strong>
                    {{ result['auth_first_name'] }} {{ result['auth_middle_name'] }} {{ result['auth_last_name'] }} ({{ result['auth_relationship'] }})
                    <br>
                    <strong>BTN:</strong>
                    {{ result['btn'] }}
                    <strong>Email:</strong>
                    <template
                      v-if="result.hasOwnProperty('email_address')"
                    >
                      {{ result['email_address'] }}
                    </template>
                    <template v-else>
                      <i>None Entered</i>
                    </template>
                    <br>
                    <strong>Service Address:</strong>
                    {{ result['service_address1'] }} {{ result['service_address2'] }}
                    <br>
                    <strong>Service City:</strong>
                    {{ result['service_city'] }}
                    <strong>Service State:</strong>
                    {{ result['service_state'] }}
                    <strong>Service Zip:</strong>
                    {{ result['service_zip'] }}
                    <br>
                    <strong>Utility:</strong>
                    {{ result['product_utility_name'] }}
                    <strong>Account Number:</strong>
                    {{ result['account_number1'] }}
                  </p>
                  <hr>
                </div>
              </template>
              <template v-if="(perPage * page) < total">
                Showing {{ ((perPage * page) - perPage) + 1 }} to {{ (perPage * page) > total ? total : perPage * page }} of {{ total }} Results
                <br>
                <a
                  v-if="page > 1"
                  :href="`/search?query=${query}&page=${page-1}`"
                  class="btn btn-primary mr-4"
                >
                  <i class="fa fa-angle-double-left" /> Previous Page
                </a>
                <a
                  v-else
                  :href="`/search?query=${query}&page=${page+1}`"
                  class="btn btn-primary"
                >
                  Next Page
                  <i class="fa fa-angle-double-right" />
                </a>
              </template>
              <template v-else-if="page > 1">
                Showing {{ ((perPage * page) - perPage) + 1 }} to {{ (perPage * page) > total ? total : perPage * page }} of {{ total }} Results
                <br>
                <a
                  :href="`/search?query=${query}&page=${page-1}`"
                  class="btn btn-primary mr-4"
                >
                  <i class="fa fa-angle-double-left" /> Previous Page
                </a>
              </template>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
export default {
    name: 'SearchResults',
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
            results: [],
            query: '',
            page: 1,
            perPage: 15,
            total: 0,
            type: '',
            dataIsLoading: false,
        };
    },
    mounted() {
        axios
            .get(window.location.href)
            .then((response) => {
                const res = response.data;
                document.title += res.query
                    ? ` Search Results for ${res.query}`
                    : ' Search';
                this.results = res.results;
                this.query = res.query;
                this.page = res.page;
                this.perPage = res.perPage;
                this.total = res.total;
                this.type = res.type;
                this.dataIsLoading = true;
                if (this.results.length == 1) {
                    setTimeout(() => {
                        window.$('.lead')[0].click();
                    }, 5);
                }
            })
            .catch(console.log);
    },
};
</script>
