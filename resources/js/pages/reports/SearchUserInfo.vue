<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Search User Info', url: '/reports/search_user_info_from_audits', active: true}
      ]"
    />
    <div class="col-4">
      <button
        v-if="$mq === 'sm'"
        class="navbar-toggler sidebar-minimizer"
        type="button"
        @click="displaySearchBar = !displaySearchBar"
      >
        <i class="fa fa-bars" />
      </button>
    </div>
    <div
      v-if="displaySearchBar"
      class="page-buttons filter-bar-row"
    >
      <nav class="navbar navbar-light bg-light filter-navbar w-100">
        <div class="search-form form-inline pull-left">
          <div class="form-group">
            <div class="form-group">
              <label for="tpv_agent_name_search">User ID</label>
              <input
                id="userId"
                ref="userId"
                class="form-control ml-1"
                placeholder="Enter the User ID"
                autocomplete="off"
                name="userId"
                type="text"
                :value="getParams().userId"
              >
            </div>
            <button
              type="button"
              class="btn btn-primary"
              @click="search"
            >
              <i
                class="fa fa-search"
                aria-hidden="true"
              />
              Search
            </button>
          </div>
        </div>
      </nav>
    </div>
    <div class="container-fluid">
          <div class="animated fadeIn">
            <div class="row page-buttons">
              <div class="col-md-12" />
            </div>
            <div class="card" style="margin-top:6%;">
              <div class="card-header">
                <i class="fa fa-th-large" /> Report: Search user info
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th scope="col">
                          Created At
                        </th>
                        <th scope="col">
                          Responsible
                        </th>
                        <th scope="col">
                          IP Address
                        </th>
                        <th scope="col">
                          Event
                        </th>
                        <th scope="col">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <template v-for="audit in audits">
                        <tr :key="audit.id + '-data'">
                          <td>{{ audit.created_at }}</td>
                          <td>{{ audit.responsable }}</td>
                          <td>{{ audit.ip_address }}</td>
                          <td v-html="audit.event" />
                          <td>
                            <button
                              type="button"
                              class="btn btn-primary"
                              @click="viewFriendlyFormat(audit.id)"
                            >
                              <i
                                class="fa fa-eye"
                                aria-hidden="true"
                              /> View
                            </button>
                          </td>
                        </tr>
                        <tr
                          :id="audit.id"
                          :key="audit.id + 'meta-data'"
                          :style="{display: 'none'}"
                          class="audit_info"
                        >
                          <td
                            colspan="5"
                            style="width:100%"
                          >
                            <div class="row">
                              <div class="col-12">
                                <ul class="bg-light pt-4 pb-4">
                                  <li
                                    v-for="(msg, index) in friendlyFormat(audit)"
                                    :key="index"
                                    v-html="msg"
                                  />
                                </ul>
                              </div>
                            </div>
                            <div class="row">
                              <div
                                v-if="Object.keys(audit.old_values).length"
                                :class="classObject(audit)"
                              >
                                <h4>Old Values:</h4>
                                <table
                                  class="table table-bg-white table-bordered"
                                >
                                  <thead>
                                    <tr>
                                      <th scope="col">
                                        Column Name
                                      </th>
                                      <th scope="col">
                                        Value
                                      </th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr
                                      v-for="(oldKey, index) in Object.keys(audit.old_values)"
                                      :key="index"
                                    >
                                      <td>
                                        {{ oldKey }}
                                      </td>
                                      <td>{{ audit.old_values[oldKey] }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                              <div
                                v-if="Object.keys(audit.new_values).length"
                                :class="classObject(audit)"
                              >
                                <h4>New Values:</h4>
                                <table
                                  class="table table-bg-white table-bordered"
                                >
                                  <thead>
                                    <tr>
                                      <th scope="col">
                                        Column Name
                                      </th>
                                      <th scope="col">
                                        Value
                                      </th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr
                                      v-for="(newKey, index) in Object.keys(audit.new_values)"
                                      :key="index"
                                    >
                                      <td>
                                        {{ newKey }}
                                      </td>
                                      <td>{{ audit.new_values[newKey] }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </td>
                        </tr>
                      </template>
                      <tr v-if="!dataIsLoaded">
                        <td
                          colspan="5"
                          class="text-center"
                        >
                          <span class="fa fa-spinner fa-spin fa-2x" />
                        </td>
                      </tr>
                      <tr v-if="dataIsLoaded && !audits.length">
                        <td
                          colspan="5"
                          class="text-center"
                        >
                          No audits were found.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <simple-pagination
                  :page-nav="pageNav"
                  :filter-params="filterParams"
                />
              </div>
            </div>
          </div>
    </div>
  </div>
</template>
<script>
import SimplePagination from 'components/SimplePagination';
import { mapState } from 'vuex';
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'SearchUserInfo',
    components: {
        SimplePagination,
        Breadcrumb,
    },
    data() {
        return {
            audits: [],
            dataIsLoaded: false,
            pageNav: { next: null, last: null },
            displaySearchBar: true,
        };
    },
    computed: {
        ...mapState({
            currentPortal: 'portal',
        }),
        filterParams() {
            const params = this.getParams();
            return [
                params.userId ? `&userId=${params.userId}` : '',
            ].join('');
        },

    },
    mounted() {
        const params = this.getParams();
        const pageParam = params.page ? `&page=${params.page}` : '';
        this.displaySearchBar = !this.isMobile();
        axios.get(`/audits/search_user_info?${pageParam}${this.filterParams}`).then((response) => {
            const res = response.data;
            this.audits = res.data.map((audit) => this.transformAudit(audit));
            this.dataIsLoaded = true;
            this.pageNav.next = res.next_page_url;
            this.pageNav.last = res.prev_page_url;

        }).catch(console.log);
    },
    methods: {
        transformAudit(audit) {
            const auditTransformed = {
                responsable: `${audit.first_name} ${audit.last_name}`,
                receptor: `${audit.user.first_name} ${audit.user.last_name}`,
                event_original: audit.event,
                event: this.setBadges(audit.event),
            };
            return {...audit, ...auditTransformed};
        },
        friendlyFormat(audit) {
            const fieldsInvolved = (Object.keys(audit.new_values).length)
                ? Object.keys(audit.new_values).join()
                : Object.keys(audit.old_values).join();
            const msg = [
                `The user <i>${audit.receptor}</i> was ${audit.event_original} by ${audit.responsable}.`,
                `Fields involved in the action: <strong>${fieldsInvolved}</strong>.`,
            ];
            return msg;
        },
        classObject(audit) {
            return {
                'col-12': audit.event_original !== 'updated',
                'col-6': audit.event_original === 'updated',
            };
        },
        setBadges(event) {
            switch (event) {
                case 'created':
                    event = `<span class="badge badge-success">${event}</span>`;
                    break;
                case 'deleted':
                    event = `<span class="badge badge-danger">${event}</span>`;
                    break;
                default:
                    event = `<span class="badge badge-secondary">${event}</span>`;
                    break;
            }
            return event;
        },
        search() {
            const userId = this.$refs.userId.value
                ? `&userId=${this.$refs.userId.value}`
                : '';
            window.location.href = `/reports/search_user_info_from_audits?${userId}`;
        },
        viewFriendlyFormat(id) {
            $('.audit_info').hide();

            $(`#${id}`).show();
            return id;
        },
        getParams() {
            const url = new URL(window.location.href);
            const page = url.searchParams.get('page');
            const userId = url.searchParams.get('userId');

            return {
                page,
                userId,
            };
        },
        isMobile() {
            return this.$mq === 'sm';
        },
    },
};
</script>
<style scoped>
.table .table, .table .table tr:hover, .table tr.audit_info:hover{
    background-color: white !important;
}
</style>
