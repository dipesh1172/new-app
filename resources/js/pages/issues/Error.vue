<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item">
        <a href="/errors">Site Errors</a>
      </li>
      <li class="breadcrumb-item active">
        Error
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
            <div
              v-if="!dataIsLoaded"
              class="d-flex justify-content-center"
            >
              <div
                class="spinner-border"
                role="status"
              >
                <span class="fa fa-spinner fa-spin fa-2x" />
              </div>
            </div>
            <div
              v-if="dataIsLoaded && error == null"
              class="d-flex justify-content-center"
            >
              <p>No error were found</p>
            </div>
            <div
              v-else-if="error !== null"
              class=""
            >
              <button
                type="button"
                class="btn btn-primary pull-right"
                @click="markResolved"
              >
                <i class="fa fa-paw" /> Mark Resolved
              </button>
              <table class="clearfix table table-bordered">
                <tr>
                  <td><strong>URI:</strong> <span class="preformatted">{{ error.document.url }}</span></td>
                  <td><strong>HTTP Method:</strong> {{ error.document.method }}</td>
                  <td><strong>Occured:</strong> {{ error.created_at }}</td>
                </tr>
                <tr>
                  <td><strong>File:</strong> <span class="preformatted">{{ error.document.file }}</span></td>
                  <td><strong>Line:</strong> {{ error.document.line }}</td>
                  <td><strong>Code:</strong> {{ error.document.code }}</td>
                </tr>
                <tr>
                  <td colspan="3">
                    <strong>Message:</strong> <span class="bg-light p-2 preformatted">{{ error.document.message }}</span>
                  </td>
                </tr>
              </table>
              
              <h3>Request Data</h3>
              <template v-if="error.document.ips != undefined && error.document.ips != null">
                <div class="card">
                  <div class="card-header">
                    <h4 class="mb-0">
                      Source IP(s)
                    </h4>
                  </div>
                  <div class="card-body p-0">
                    <pre class="p-2 mb-0">{{ error.document.ips.join(', ') }}</pre>
                  </div>
                </div>
              </template>
              <template v-if="error.document.headers != undefined && error.document.headers != null && (Object.keys(error.document.headers)).length > 0">
                <div class="card">
                  <div class="card-header">
                    <h4 class="mb-0">
                      Headers
                    </h4>
                  </div>
                  <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0">
                      <thead>
                        <tr class="bg-dark text-light">
                          <th>Header</th>
                          <th>Value</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(key, key_i) in Object.keys(error.document.headers)"
                          :key="`header_${key_i}`"
                        >
                          <td
                            width="20%"
                            style="vertical-align: middle;"
                          >
                            <strong>{{ key }}</strong>
                          </td>
                          <td
                            width="80%"
                            class="p-0 bg-light"
                            style="vertical-align: middle;"
                          >
                            <pre class=" p-2 mb-0">{{ error.document.headers[key] != null ? (error.document.headers[key] instanceof Array && error.document.headers[key].length === 1 ? error.document.headers[key][0] : error.document.headers[key]) : 'null' }}</pre>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </template>
              <template v-if="error.document.input != undefined && error.document.input != null && (Object.keys(error.document.input)).length > 0">
                <div class="card">
                  <div class="card-header">
                    <h4 class="mb-0">
                      Input
                    </h4>
                  </div>
                  <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0">
                      <thead>
                        <tr class="bg-dark text-light">
                          <th>Parameter</th>
                          <th>Value</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(key, key_i) in Object.keys(error.document.input)"
                          :key="`input_${key_i}`"
                        >
                          <td
                            width="20%"
                            style="vertical-align: middle;"
                          >
                            <strong>{{ key }}</strong>
                          </td>
                          <td
                            width="80%"
                            class="p-0 bg-light"
                            style="vertical-align: middle;"
                          >
                            <pre class=" p-2 mb-0">{{ error.document.input[key] != null ? error.document.input[key] : 'null' }}</pre>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <hr>
              </template>
              <template v-if="error.document.user != undefined">
                <h3>User</h3>
                <span v-if="error.document.user == null">Not Logged In</span>
                <span v-else>
                  {{ error.document.user }}
                </span>
              </template>
              <template v-if="error.document.session != undefined && error.document.session != null && (Object.keys(error.document.session)).length > 0">
                <h3>Session</h3>
                <ul class="list-unstyled">
                  <li
                    v-for="(key, key_i) in Object.keys(error.document.session)"
                    :key="`session_${key_i}`"
                  >
                    <strong>{{ key }}</strong>
                    <pre class="bg-light p-2">{{ error.document.session[key] != null ? error.document.session[key] : 'null' }}</pre>
                  </li>
                </ul>
                <hr>
              </template>
              <hr>
              <h3>Exception Stacktrace</h3>
              <pre class="bg-light p-2">{{ error.document.trace }}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.preformatted {
  font-family:'Courier New', Courier, monospace
}
</style>

<script>
export default {
    name: 'Error',
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
            error: null,
            dataIsLoaded: false,
        };
    },
    mounted() {
        document.title += ' Detected Site Errors';

        const params = this.getParams();
        const ref = params.ref ? `&ref=${params.ref}` : '';
        axios
            .get(`/errors?${ref}`)
            .then((response) => {
                const res = response.data;

                this.error = res; // JSON.stringify(res, null, 2);
                this.dataIsLoaded = true;
            })
            .catch((e) => console.log(e));
    },
    methods: {
        getParams() {
            const url = new URL(window.location.href);
            const ref = url.searchParams.get('ref');

            return {
                ref,
            };
        },
        markResolved() {
            this.dataIsLoaded = false;
            axios.post('/errors/resolve', {
                ref: this.error.ref_id,
            }).then((res) => {
                if (res.data.error !== false) {
                    throw new Error(res.data.error);
                }
                window.location.href = '/errors';
            }).catch((e) => {
                this.dataIsLoaded = true;
                alert(e);
            });
        },
    },
};
</script>
