<template>
  <div style="margin: 15px;">
    <tab-bar :active-item="2" />
    <div class="tab-content">
      <div class="row">
        <div class="col-md-12">
          <div class="card">
            <div slot="header">
              <h3 class="pull-left">
                Not Ready Agents
              </h3>
              <span class="pull-right">
                Last Updated:
                <span v-if="updatedTime">{{ updatedTime | moment("MM/DD/YYYY, h:mm:ss a") }}</span>
                <span v-else><i class="fa fa-spinner fa-spin" /> Loading ...</span>
              </span>
            </div>

            <div class="content">
              <table
                :data="computedAgents"
                class="mb-5"
              >
                <template slot="thead">
                  <th>
                    Agent Name
                  </th>
                  <th>
                    Location
                  </th>
                  <th>
                    Status
                  </th>
                  <th>
                    Duration
                  </th>
                  <th>
                    Logged In
                  </th>
                </template>
                <template slot-scope="{data}">
                  <tr
                    v-for="(tr, indextr) in data"
                    :key="indextr"
                  >
                    <td :data="data[indextr].name">
                      {{ data[indextr].name }}
                    </td>
                    <td :data="data[indextr].location">
                      {{ data[indextr].location }}
                    </td>
                    <td :data="data[indextr].status">
                      {{ data[indextr].status }}
                    </td>
                    <td
                      :data="data[indextr].status_changed_at.date"
                      :class="statusColor(data[indextr])"
                    >
                      {{ displayTimeSince(data[indextr].status_changed_at.date) }}
                    </td>
                    <td :data="data[indextr].logged_in_at.date">
                      {{ displayTime(data[indextr].logged_in_at.date) }}
                    </td>
                  </tr>
                </template>
              </table>
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
    name: 'NotReadyDashboard',
    components: {
        TabBar,
    },
    data() {
        return {
            agents: [],
            now: moment(),
            updateDataTimer: null,
        };
    },
    computed: {
        computedAgents() {
            return this.agents;
        },
        updatedTime() {
            return this.$store.state.AsOf;
        },
    },
    mounted() {
        this.updateDataTimer = setTimeout(this.updateData, 500);
        setTimeout(this.updateTime, 500);
    },
    destroyed() {
        clearTimeout(this.updateDataTimer);
    },
    methods: {
        displayTimeSince(t) {
            if (t) {
                const diff = this.now.diff(t);
                const duration = moment.duration(diff);
                const durationMs = duration.asMilliseconds();
                return moment.utc(durationMs).format('HH:mm:ss');
            }
            return '';

        },
        statusColor(user) {
            const minutes = moment.duration(this.now.diff(user.status_changed_at.date)).asMinutes();
            if (user.status === 'Meal') {
                if (minutes >= 30) {
                    return 'over_lunch';
                }
            }
            if (user.status === 'Break') {
                if ((user.location === 'Las Vegas' && minutes >= 10) || (minutes >= 15)) {
                    return 'over_break';
                }
            }
            return '';
        },
        displayTime(t) {
            return (t ? moment(t).format('hh:mm:ss A') : '');
        },
        updateTime() {
            this.now = moment();
            setTimeout(this.updateTime, 1000);
        },
        updateData() {
            axios.get('/api/not-ready').then((response) => {
                this.agents = response.data;
            }).catch((error) => {
                console.log(error);
            });
            this.updateDataTimer = setTimeout(this.updateData, 5000);
        },
        flashMessage(message) {
            $('#message_text').html(message);
            $('#message').removeClass('d-none');
            setTimeout(() => {
                $('#message_text').html('');
                $('#message').addClass('d-none');
            }, 5000);
        },
        flashSuccess(message) {
            $('#message_text').removeClass('alert-error');
            $('#message_text').addClass('alert-success');
            this.flashMessage(message);
        },
        flashError(message) {
            $('#message_text').removeClass('alert-success');
            $('#message_text').addClass('alert-error');
            this.flashMessage(message);
        },
    },
};
</script>

<style scoped>
.over_lunch {
    color: orange;
}

.over_break {
    color: darkgoldenrod;
}
</style>
