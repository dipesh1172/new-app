<template>
  <div class="container-fluid">
    <tab-bar :active-item="1" />
    <div class="tab-content">
      <div class="card mb-0">
        <div class="card-header">
          <div class="form-group mb-1 pull-right">
            <input
              name="_token"
              type="hidden"
              :value="csrfToken"
            >
            <div class="form-group mr-3">
              <form
                id="agent-dashboard-search"
                class="form-inline"
              >
                <div class="form-group">
                  <label
                    for="location_filter"
                    class="mr-2"
                  >Location</label>
                  <select
                    id="location_filter"
                    v-model="locationFilter"
                    class="form-control mr-4"
                    name="location_filter"
                  >
                    <option
                      selected="selected"
                      value
                    >
                      Select a Location...
                    </option>
                    <option
                      v-for="l in locations"
                      :key="l.name"
                      :value="l.id"
                      :selected="l.name == locationFilter"
                    >
                      {{ l.name }}
                    </option>
                  </select>
                </div><div class="form-group">
                  <label
                    for="agent_name_filter"
                    class="mr-2"
                  >Agent Name</label>
                  <input
                    id="agent_name_filter"
                    v-model="agentNameFilter"
                    type="text"
                    class="form-control mr-4"
                  >
                </div>
                <div class="form-group">
                  <label
                    for="status_name_filter"
                    class="mr-2"
                  >Status</label>
                  <select
                    id="status_name_filter"
                    v-model="statusNameFilter"
                    class="form-control mr-4"
                  >
                    <option
                      selected="selected"
                      value
                    >
                      Select a Status...
                    </option>

                    <option
                      v-for="(status, i) in computedStatuses"
                      :key="i"
                      :value="status"
                    >
                      {{ status }}
                    </option>
                  </select>
                </div>
                <div class="form-group">
                  <label
                    for="skill_filter"
                    class="mr-2"
                  >Agent Group</label>
                  <select
                    id="skill_filter"
                    v-model="skillFilter"
                    class="form-control mr-4"
                  >
                    <option
                      selected="selected"
                      value
                    >
                      Select an Agent Group...
                    </option>

                    <option
                      v-for="skill in computedSkills"
                      :key="skill"
                      :value="skill"
                    >
                      {{ skill }}
                    </option>
                  </select>
                </div>
                <div class="form-group agent-dashboard-search-btn">
                  <button
                    class="btn btn-warning mt-1"
                    @click="clearForm"
                  >
                    Reset
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div
            id="message"
            class="alert d-none"
          >
            <span class="fa fa-check-circle" />
            <em>
              <span id="message_text" />
            </em>
          </div>
          <div class="card">
            <div class="card-header">
              <h5 class="card-title mt-0 mb-3 pull-left">
                Live Agent Dashboard
              </h5>
          <div class="table-responsive">
          <table
            v-if="computedAgents.length > 0"
            class="table"
          >
            <thead>
              <tr>
                <th scope="col">
                  <div
                    class="large-3 medium-3 cell sortable-header"
                    @click="resortAgents('name')"
                  >
                    Agent Name
                    <span
                      v-show="sortBy == 'name' && sortDirection == 'DESC'"
                      class="sort-icon-big"
                    ><strong>↑</strong></span>
                    <span
                      v-show="sortBy == 'name' && sortDirection == 'ASC'"
                      class="sort-icon-big"
                    ><strong>↓</strong></span>
                    <span
                      v-show="sortBy != 'name'"
                      class="sort-icon"
                    >↕</span>
                  </div>
                </th>
                <th scope="col">
                  Location
                </th>
                <th scope="col">
                  <div
                    class="large-3 medium-3 cell sortable-header"
                    @click="resortAgents('status_name')"
                  >
                    Status
                    <span
                      v-show="sortBy == 'status_name' && sortDirection == 'DESC'"
                      class="sort-icon-big"
                    ><strong>↑</strong></span>
                    <span
                      v-show="sortBy == 'status_name' && sortDirection == 'ASC'"
                      class="sort-icon-big"
                    ><strong>↓</strong></span>
                    <span
                      v-show="sortBy != 'status_name'"
                      class="sort-icon"
                    >↕</span>
                  </div>
                </th>
                <th scope="col">
                  <div
                    class="large-3 medium-3 cell sortable-header"
                    @click="resortAgents('status_duration')"
                  >
                    Status Duration
                    <span
                      v-show="sortBy == 'status_duration' && sortDirection == 'ASC'"
                      class="sort-icon-big"
                    ><strong>↑</strong></span>
                    <span
                      v-show="sortBy == 'status_duration' && sortDirection == 'DESC'"
                      class="sort-icon-big"
                    ><strong>↓</strong></span>
                    <span
                      v-show="sortBy != 'status_duration'"
                      class="sort-icon"
                    >↕</span>
                  </div>
                </th>
                <th scope="col">
                  Worked Hours
                </th>
                <th scope="col">
                  Calls
                </th>
                <th scope="col">
                  CPH
                </th>
                <th scope="col">
                  Agent Groups
                </th>
                <th scope="col">
                  Current Call
                </th>
                <th scope="col">
                  Log
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(agent, i) in computedAgents"
                :key="i"
                :class="statusColor(agent)"
              >
                <td>{{ agent.name }}</td>
                <td>{{ agent.location }}</td>
                <td>
                  <select
                    :id="`status_` + agent.id"
                    @change="changeStatus(agent)"
                  >
                    <option
                      v-for="(status, j) in computedStatuses"
                      :key="j"
                      :selected="j == agent.status"
                      :value="j"
                    >
                      {{ status }}
                    </option>
                  </select>
                </td>
                <td
                  :class="statusColor(agent)"
                >
                  {{ displayTimeSince(agent.status_changed_at) }}
                </td>
                <td>{{ agent.hours_worked > 0 ? agent.hours_worked.toFixed(2) : 0 }}</td>
                <td>{{ agent.calls }}</td>
                <td>{{ Math.round(agent.CPH, 2) }}</td>
                <td>
                  <select
                    :id="`skill_` + agent.id"
                    @change="changeSkill(agent)"
                  >
                    <option
                      v-for="(skill, k) in computedSkills"
                      :key="k"
                      :selected="k == agent.tpv_staff_group_id"
                      :value="k"
                    >
                      {{ skill }}
                    </option>
                  </select>
                </td>
                <td>{{ agent.current_call }}</td>
                <td>
                  <a :href="`/agent-activity-log/` + agent.tpv_id">Log</a>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else>
            No agents are currently logged in.
          </div>
        </div>
        </div>
        </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import moment from 'moment';
import TabBar from '../components/TabBar.vue';

export default {
    name: 'LiveAgentDashboard',
    components: {
        TabBar,
    },
    props: {
        callCenters: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            now: moment(),
            agents: [],
            rawAgents: [],
            statuses: [],
            skills: [],
            updateDataTimer: null,
            sortBy: 'name',
            sortDirection: 'ASC',
            locationFilter: '',
            /*locations: [
                { id: 'Tulsa', name: 'Tulsa' },
                { id: 'Tahlequah', name: 'Tahlequah' },
                { id: 'Las Vegas', name: 'Las Vegas' },
                { id: 'Voxcentrix', name: 'Voxcentrix'},
                { id: 'Work at Home', name: 'Work at Home' },
            ],*/
            agentNameFilter: '',
            statusNameFilter: '',
            skillFilter: '',
            csrfToken: window.csrf_token,
            alertColor: '#000000',
            alertMessage: '',
        };
    },
    computed: {
        computedAgents() {
            this.sortAndFilter();
            return this.agents;
        },
        computedStatuses() {
            return this.statuses;
        },
        computedSkills() {
            return this.skills;
        },
        updatedTime() {
            return this.$store.state.AsOf;
        },
    },
    watch: {
        agentNameFilter: function(_val) {
            this.sortAndFilter();
        },
        locationFilter: function(_val) {
            this.sortAndFilter();
        },
        statusNameFilter: function(_val) {
            this.sortAndFilter();
        },
        skillFilter: function(_val) {
            this.sortAndFilter();
        },
    },
    mounted() {
        this.locations = this.callCenters.map((item) => ({id: item.call_center, name: item.call_center}));
        setTimeout(this.loadStatuses, 500);
        setTimeout(this.loadSkills, 1000);
        this.updateDataTimer = setTimeout(this.updateData, 100);
        setInterval(this.updateTime, 1000);
    },
    destroyed() {
        clearTimeout(this.updateDataTimer);
    },
    methods: {
        clearForm() {
            this.agentNameFilter = '';
            this.locationFilter = '';
            this.statusNameFilter = '';
            this.skillFilter = '';
            this.sortAndFilter();
        },
        sortAndFilter() {
            const self = this;

            if (!Array.isArray(self.rawAgents)) {
                self.rawAgents = [];
            }

            self.agents = self.rawAgents.filter((agent) => agent.name.toUpperCase().indexOf(self.agentNameFilter.toUpperCase()) !== -1);
            self.agents = self.agents.filter((agent) => (self.locationFilter === '' || agent.location === self.locationFilter));
            self.agents = self.agents.filter((agent) => (self.statusNameFilter === '' || agent.status_name === self.statusNameFilter));
            self.agents = self.agents.filter((agent) => (self.skillFilter === '' || agent.skill_name === self.skillFilter));

            switch (self.sortBy) {
                case 'name':
                    self.sortByAgentName();
                    break;

                case 'status_name':
                    self.sortByStatusName();
                    break;

                case 'status_duration':
                    self.sortByStatusDuration();
                    break;
            }
        },
        displayTimeSince(t) {
            if (t) {
                const diff = this.now.diff(t);
                const duration = moment.duration(diff);
                const duration_ms = duration.asMilliseconds();
                return moment.utc(duration_ms).format('HH:mm:ss');
            }
            return '';
        },
        statusColor(user) {
            const minutes = moment
                .duration(this.now.diff(user.status_changed_at))
                .asMinutes();
            if (user.status_name === 'Meal') {
                if (minutes >= 30) {
                    return 'over_lunch';
                }
            }
            if (user.status_name === 'Break') {
                if ((user.location === 'Las Vegas' && minutes >= 10) || minutes >= 15) {
                    return 'over_break';
                }
            }
            return 'on_time';
        },
        displayTime(t) {
            return t ? moment(t).format('hh:mm:ss A') : '';
        },
        updateTime() {
            this.now = moment();
        },
        updateData() {
            let timeout = 5000;
            const self = this;
            axios
                .get('/api/live-agent')
                .then((response) => {
                    self.rawAgents = response.data;
                    self.showAlertMessage('Updated data successfully', 'success');
                })
                .catch((error) => {
                    self.showAlertMessage('There was an error trying to get the list of agents.\r\nPlease try again shortly.\r\n'.error, 'warning');
                    timeout = 20000;
                });
            this.updateDataTimer = setTimeout(this.updateData, timeout);
        },
        showAlertMessage(message, color = 'success') {
            const self = this;
            self.alertColor = color;
            self.alertMessage = message;
            setTimeout(() => {
                self.alertMessage = '';
                self.alertColor = '#000000';
            }, 2000);
        },
        loadStatuses() {
            let timeout = 30000;
            const self = this;
            axios
                .get('/api/status-list')
                .then((response) => {
                    self.statuses = response.data;
                })
                .catch((error) => {
                    console.log(error);
                    timeout = 60000;
                });
            setTimeout(self.loadStatuses, timeout);
        },
        loadSkills() {
            let timeout = 30000;
            const self = this;
            axios
                .get('/api/skill-list')
                .then((response) => {
                    self.skills = response.data;
                })
                .catch((error) => {
                    console.log(error);
                    timeout = 60000;
                });
            setTimeout(self.loadSkills, timeout);
        },
        changeSkill(agent) {
            if (agent) {
                const self = this;
                const data = {
                    worker_id: agent.status_id,
                    tpv_staff_id: agent.tpv_id,
                    tpv_staff_group_id: document.getElementById(`skill_${agent.id}`).value,
                };
                axios
                    .post('/api/update-agent-skills', data)
                    .then((response) => {
                        self.showAlertMessage('Agent\'s skill was successfully updated.');
                    })
                    .catch((e) => {
                        self.showAlertMessage(`There was an error trying to update the agent's skill.<br />${e}`, 'warning');
                    });
            }
        },
        async changeStatus(agent) {
            const self = this;
            const res = await axios.get(`/api/get-call-active-status/${agent.status_id}`);
            const data = {
                activity_sid: document.getElementById(`status_${agent.id}`).value,
                worker_id: agent.status_id,
                on_call: res.data || false,
            };
            axios
                .post('/api/update-agent-status', data)
                .then((response) => {
                    console.log('Response: ', response);
                    if (res.data == 'true') {
                        self.showAlertMessage('Agent is currently on a call. The status will be updated when they are done.', 'success');
                    }
                    else {
                        self.showAlertMessage('Agent\'s status was successfully updated.');
                    }
                })
                .catch((e) => {
                    self.showAlertMessage(`There was an error trying to update the agent's status.<br />${e}`, 'warning');
                });

        },
        resortAgents(by) {
            if (by === this.sortBy) {
                if (this.sortDirection === 'ASC') {
                    this.sortDirection = 'DESC';
                }
                else {
                    this.sortDirection = 'ASC';
                }
            }

            if (by !== this.sortBy) {
                this.sortDirection = 'ASC';
                this.sortBy = by;
            }
        },
        sortByAgentName() {
            const self = this;
            if (self.agents) {
                self.agents.sort((a, b) => {
                    if (self.sortDirection === 'DESC') {
                        return ((a.name === b.name) ? 0 : ((a.name < b.name) ? 1 : -1));
                    }
                    return ((a.name === b.name) ? 0 : ((a.name > b.name) ? 1 : -1));
                });
            }
        },
        sortByStatusName() {
            const self = this;
            if (self.agents) {
                self.agents.sort((a, b) => {
                    if (self.sortDirection === 'DESC') {
                        return ((a.status_name === b.status_name) ? 0 : ((a.status_name < b.status_name) ? 1 : -1));
                    }
                    return ((a.status_name === b.status_name) ? 0 : ((a.status_name > b.status_name) ? 1 : -1));
                });
            }
        },
        sortByStatusDuration() {
            const self = this;
            if (self.agents) {
                self.agents.sort((a, b) => {
                    if (self.sortDirection === 'DESC') {
                        return ((a.status_changed_at === b.status_changed_at) ? 0 : ((a.status_changed_at < b.status_changed_at) ? 1 : -1));
                    }
                    return ((a.status_changed_at === b.status_changed_at) ? 0 : ((a.status_changed_at > b.status_changed_at) ? 1 : -1));
                });
            }
        },
    },
};
</script>

<style scoped>
.over_lunch {
  color: orange;
  font-weight:700;
}

.over_break {
  color: darkgoldenrod;
  font-weight: 700;
}

.on_time {
  color: black;
}

.bottomleft {
  position: absolute;
  bottom: 8px;
  left: 16px;
  font-size: 18px;
}

.sortable-header {
  cursor: default;
}

.sort-icon {
  font-size: 18px;
}

.sort-icon-big {
  font-size: 20px;
  font-weight: 700;
}

@media screen and (max-width: 900px) and (min-width: 600px) {
  #agent-dashboard-search div.form-group {
    display: block;
    margin: 10px 0px;
  }
  #agent-dashboard-search div.form-group label.mr-2 {
    justify-content: left;
    margin-bottom: 5px;
  }
  #agent-dashboard-search div.form-group select {
    min-width: 200px;
  }
  #agent-dashboard-search div.form-group input[type=text] {
    min-width: 200px;
  }

  #agent-dashboard-search div.form-group.agent-dashboard-search-btn {
    margin: 0px;
    margin-bottom: -20px;
  }
}

@media screen and (max-width: 599px) {
  #agent-dashboard-search div.form-group {
    display: block;
    margin: 10px 0px;
    width: 100%;
  }

  #agent-dashboard-search div.form-group label.mr-2 {
    justify-content: left;
    margin-bottom: 5px;
  }

  #agent-dashboard-search div.form-group select {
    min-width: 100%;
  }

  #agent-dashboard-search div.form-group input[type=text] {
    min-width: 100%;
  }
}
</style>
