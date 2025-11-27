<template>
  <div class="card mb-0">
    <div class="card-body p-0">
      <div
        v-if="isLoading"
        class="disabled"
      />
      <table :class="{table: true, 'table-bordered': true, 'mb-0': true}">
        <thead>
          <tr>
            <th
              data-col="name"
              :class="classFor('name')"
              @click="clickedHeader"
            >
              Skill
            </th>
            <th
              data-col="avail_p"
              :class="classFor('avail_p')"
              @click="clickedHeader"
            >
              Availability %
            </th>
            <th
              data-col="logged_in"
              :class="classFor('logged_in')"
              @click="clickedHeader"
            >
              Logged In
            </th>
            <th
              data-col="avail"
              :class="classFor('avail')"
              @click="clickedHeader"
            >
              Available
            </th>
            <th
              data-col="on_call"
              :class="classFor('on_call')"
              @click="clickedHeader"
            >
              On Call
            </th>
            <th
              data-col="not_ready"
              :class="classFor('not_ready')"
              @click="clickedHeader"
            >
              Not Ready
            </th>
            <th
              data-col="in_queue"
              :class="classFor('in_queue')"
              @click="clickedHeader"
            >
              In Queue
            </th>
            <th
              data-col="current_hold"
              :class="classFor('current_hold')"
              @click="clickedHeader"
            >
              Current Hold
            </th>
            <th
              data-col="longest_hold"
              :class="classFor('longest_hold')"
              @click="clickedHeader"
            >
              Longest Hold Today
            </th>
            <th
              data-col="service_level"
              :class="classFor('service_level')"
              @click="clickedHeader"
            >
              Service Level
            </th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="(queue, index) in qdata" 
            :key="index"
          >
            <td v-text="queue.name" />
            <td :class="{'text-center':true,'bg-success': queue.avail_p > 25, 'bg-warning': queue.avail_p <= 25, 'bg-danger': queue.avail_p < 10}">
              {{ queue.avail_p }}
            </td>
            <td>{{ queue.logged_in }}</td>
            <td>{{ queue.avail }}</td>
            <td>{{ queue.on_call }}</td>
            <td>{{ queue.not_ready }}</td>
            <td>{{ queue.in_queue }}</td>
            <td>{{ queue.current_hold }}</td>
            <td>{{ queue.longest_hold }}</td>
            <td>{{ queue.service_level }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="card-footer">
      <div class="row">
        <div class="col-md-10">
          Last Updated: 

          <span v-if="updatedTime">{{ updatedTime | moment("MM/DD/YYYY, h:mm:ss a") }}</span>
          <span v-else><i class="fa fa-spinner fa-spin" /> Loading ...</span>
        </div>
        <div class="col-md-2">
          <i
            v-if="isLoading"
            class="fa fa-spinner fa-spin pull-right" 
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.disabled {
  position: absolute;
  top: 0;
  left: 0;
  z-index: 10;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.25);
}
#loading {
  position: absolute;
  top: 0;
  right: 0;
  padding: 0.5rem;
}

th:after {
  content: "\f0dc";
  font-family: FontAwesome;
  float: right;
  cursor: pointer;
}

th.headerSortUp:after {
  cursor: pointer;
  content: "\f0de";
  float: right;
  font-family: FontAwesome;
}

th.headerSortDown:after {
  cursor: pointer;
  content: "\f0dd";
  float: right;
  font-family: FontAwesome;
}
</style>

<script>
export default {
    name: 'Queues',
    data() {
        return {
            isLoading: false,
            qdata: [],
            sortBy: 'name',
            sortDir: 'desc',
        };
    },
    computed: {
        queues() {
            return this.$store.state.queues;
        },
        updatedTime() {
            return this.$store.state.AsOf;
        },
    },
    created() {
        this.$store.subscribe((mutation, state) => {
            if (mutation.type == 'updateTime') {
                this.updateData();
            }
        });

        this.updateData();
    },
    methods: {
        classFor(e) {
            if (e == this.sortBy) {
                if (this.sortDir == 'desc') {
                    return 'headerSortUp';
                } 
                return 'headerSortDown';
              
            }
        },
        clickedHeader(e) {
            const target = $(e.target);
            const column = target.data('col');

            const orig = this.sortBy;
            this.sortBy = column;
            if (orig == column) {
                if (this.sortDir == 'desc') {
                    this.sortDir = 'asc';
                }
                else {
                    this.sortDir = 'desc';
                }
            }
            this.sort();
        },
        updateData() {
            this.isLoading = true;
            this.qdata = [];
            for (let i = 0, len = this.queues.length; i < len; i++) {
                this.qdata.push(this.createDataFor(i));
            }
            this.sort();
            this.isLoading = false;
        },
        sort() {
            this.qdata.sort(
                (a, b) => {
                    if (a[this.sortBy] < b[this.sortBy]) { return this.sortDir == 'desc' ? -1 : 1; }
                    if (a[this.sortBy] > b[this.sortBy]) { return this.sortDir == 'desc' ? 1 : -1; }
                    return 0;
                }
            );
        },
        createDataFor(i) {
            return {
                name: this.qi(i).name,
                avail_p:
          this.avail(i) > 0
              ? (this.avail(i) / this.loggedIn(i) * 100).toFixed(0)
              : 0,
                logged_in: this.loggedIn(i),
                avail: this.avail(i),
                on_call: this.inCall(i),
                not_ready: (this.loggedIn(i) - this.avail(i)) - this.inCall(i),
                in_queue: this.qi(i).stats['!Tasks'],
                current_hold: this.qi(i).stats['*LongestTaskWaitingAge'],
                longest_hold: this.qi(i).stats.WaitDurationUntilAccepted.max,
                service_level: 'svcLevel' in this.qi(i).stats ? this.qi(i).stats.svcLevel['15'] : '--',
            };
        },
        loggedIn(i) {
            const item = this.qi(i);
            const activityStats = item.stats['*ActivityStatistics'];
            for (let n = 0, len = activityStats.length; n < len; n++) {

                const aitem = activityStats[n];
                const name = Object.keys(aitem)[0];
                const value = aitem[name];

                if (name == 'Logged Out') {
                    return item.stats['*TotalEligibleWorkers'] - value;
                }
            }
            return 0;
        },
        inCall(i) {
            const item = this.qi(i);
            const activityStats = item.stats['*ActivityStatistics'];
            for (let n = 0, len = activityStats.length; n < len; n++) {
                const aitem = activityStats[n];
                const name = Object.keys(aitem)[0];
                const value = aitem[name];
                if (name == 'TPV In Progress') {
                    return value;
                }
            }
            return 0;
        },
        avail(i) {
            const item = this.qi(i);

            const activityStats = item.stats['*ActivityStatistics'];
            for (let n = 0, len = activityStats.length; n < len; n++) {
                const aitem = activityStats[n];
                const name = Object.keys(aitem)[0];
                const value = aitem[name];
                if (name == 'Available') {
                    return value;
                }
            }
            return 0;
        },
        qi(i) {
            const item = this.queues[i];
            const name = Object.keys(item)[0];
            return {
                name,
                stats: item[name],
            };
        },
    },
};
</script>
