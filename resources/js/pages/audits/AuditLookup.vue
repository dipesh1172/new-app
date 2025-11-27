<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item active">
        Audit Lookup
      </li>
    </ol>
    <div class="container-fluid">
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

            <div class="row">
              <div class="col-md-6">
                <h2 class="pull-left">
                  Audit Lookup
                </h2>
              </div>
              <div class="col-md-6">
                <div>
                  Confirmation Code
                  <input
                    v-model="searchConfirmationCode"
                    class="form-control"
                    type="text"
                    name="confirmationCode"
                  >
                  <button
                    class="btn btn-success"
                    @click="updateUrl()"
                  >
                    Lookup
                  </button>
                </div>
              </div>
            </div>

            <br><hr><br>

            <div
              v-if="dataIsLoaded"
              class="table-responsive"
            >
              <h3>Event Audits</h3>
              <table class="table table-striped">
                <thead class="thead-dark">
                  <tr>
                    <th>Date</th>
                    <th>Event</th>
                    <th>User</th>
                    <th>Old Values</th>
                    <th>New Values</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="result in eaudits"
                    :key="result.id"
                  >
                    <td>{{ result.created_at }}</td>
                    <td>{{ result.event }}</td>
                    <td>
                      <span v-if="result.first_name">
                        {{ result.first_name }} {{ result.last_name }}
                      </span>
                      <span v-else>
                        --
                      </span>
                    </td>
                    <td><pre>{{ result.old_values }}</pre></td>
                    <td><pre>{{ result.new_values }}</pre></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <br><hr><br>

            <div
              v-if="dataIsLoaded"
              class="table-responsive"
            >
              <h3>Office Config Audits</h3>
              <table class="table table-striped">
                <thead class="thead-dark">
                  <tr>
                    <th>Date</th>
                    <th>Event</th>
                    <th>User</th>
                    <th>Old Values</th>
                    <th>New Values</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="result in ezconfigAudits"
                    :key="result.id"
                  >
                    <td>{{ result.created_at }}</td>
                    <td>{{ result.event }}</td>
                    <td>
                      <span v-if="result.first_name">
                        {{ result.first_name }} {{ result.last_name }}
                      </span>
                      <span v-else>
                        --
                      </span>
                    </td>
                    <td><pre>{{ result.old_values }}</pre></td>
                    <td><pre>{{ result.new_values }}</pre></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <br><hr><br>

            <div
              v-if="dataIsLoaded"
              class="table-responsive"
            >
              <h3>Interaction Audits</h3>
              <table class="table table-striped">
                <thead class="thead-dark">
                  <tr>
                    <th>Date</th>
                    <th>Interaction Type</th>
                    <th>Audits</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="result in interactions"
                    :key="result.id"
                  >
                    <td>{{ result.created_at }}</td>
                    <td>{{ result.interaction_type.name }}</td>
                    <td>
                      <table class="table table-striped">
                        <thead class="thead-dark">
                          <tr>
                            <th>Date</th>
                            <th>Event</th>
                            <th>User</th>
                            <th>Old Values</th>
                            <th>New Values</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr
                            v-for="audit in result.audits"
                            :key="audit.id"
                          >
                            <td>{{ audit.created_at }}</td>
                            <td>{{ audit.event }}</td>
                            <td>
                              <span v-if="result.first_name">
                                {{ result.first_name }} {{ result.last_name }}
                              </span>
                              <span v-else>
                                --
                              </span>
                            </td>
                            <td><pre>{{ audit.old_values }}</pre></td>
                            <td><pre>{{ audit.new_values }}</pre></td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
    name: 'AuditLookup',
    props: {
        confirmationCode: {
            type: String,
            default: null,
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
            results: [],
            eaudits: [],
            iaudits: [],
            searchConfirmationCode: null,
            ezconfigAudits: [],
            dataIsLoaded: false,
        };
    },
    mounted() {
        if (this.confirmationCode !== null && this.confirmationCode !== undefined && !this.dataIsLoaded) {
            console.log('loading ', this.confirmationCode);

            this.searchConfirmationCode = this.confirmationCode;
            this.lookup(this.confirmationCode);
        }
    },
    methods: {
        lookup(confirmationCode) {
            if (confirmationCode !== undefined) {
                this.dataIsLoaded = false;

                const url = `/audits/lookupData/${confirmationCode}`;
                window.axios.get(url)
                    .then((response) => {
                        this.results = response.data;

                        if (this.results) {
                            console.log(this.results);

                            this.eaudits = this.results.eaudits;
                            this.ezconfigAudits = this.results.ezconfig_audits;
                            this.interactions = this.results.interactions;

                            this.dataIsLoaded = true;
                        }

                        return null;
                    })
                    .catch((error) => {
                        console.log(error);
                    });
            }
        },
        updateUrl() {
            document.location.href = `/audits/lookup?confirmation_code=${this.searchConfirmationCode}`;
        },
    },
};
</script>
