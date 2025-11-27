<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'TPV Staff', url: '/tpv_staff'},
        {name: `${tpvStaff.first_name} ${tpvStaff.last_name}`, url: `/tpv_staff/${tpvStaff.id}/edit`},
        {name: `Time Clock: ${activeDate}`, active: true}
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="card">
        <div class="card-header">
          <span class="fa fa-th-large" /> Time Clock: 
          <datepicker
            v-model="dateValue"
            format="yyyy-MM-dd"
            wrapper-class="form-inline inline-flex"
            input-class="inline"
            bootstrap-styling
            :highlighted="highlightedDates"
            :disabled-dates="disabledDates"
            @selected="updateDate"
          />

          <span class="ml-2">
            Time Worked: {{ timeWorked }}
          </span>
          
          <form
            method="POST"
            :action="`/tpv_staff/${tpvStaff.id}/punch/${activeDate}`"
            class="pull-right"
          >
            <input
              type="hidden"
              name="_token"
              :value="csrfToken"
            >
            <input
              type="hidden"
              name="type"
              value="normal"
            >
            <button
              class="btn btn-success btn-sm ml-1 pull-right"
              type="submit"
              title="Add Normal Punch"
            >
              <span class="fa fa-clock-o" />
            </button>
          </form>

          <form
            method="POST"
            :action="`/tpv_staff/${tpvStaff.id}/punch/${activeDate}`"
            class="pull-right"
          >
            <input
              type="hidden"
              name="_token"
              :value="csrfToken"
            >
            <input
              type="hidden"
              name="type"
              value="meal"
            >
            <button
              class="btn btn-success btn-sm pull-right"
              type="submit"
              :title="`Add Meal Punch${clockedOut ? ' (disabled because not clocked in)' : ''}`"
              :disabled="clockedOut"
            >
              <span class="fa fa-cutlery" />
            </button>
          </form>

          <span :class="{'pull-right badge mr-2': true, 'badge-success': punches.length === 0 || punches.length % 2 === 0, 'badge-danger': punches.length > 0 && punches.length % 2 === 1}">
            <template v-if="punches.length === 0 || punches.length % 2 === 0">
              Clocked Out
            </template>
            <template v-else>
              Clocked In
            </template>
          </span>
        </div>
        <div class="card-body p-1">
          <div
            v-if="punches.length === 0"
            class="alert alert-warning"
          >
            {{ tpvStaff.first_name }} does not have any time punches on {{ activeDate }}
          </div>
          <template v-else>
            <div class="row">
              <div
                v-for="(punch, punch_i) in punches"
                :key="`punch-${punch.id}-${punch_i}`"
                class="col-md-3"
              >
                <div class="card">
                  <div class="card-header">
                    <span
                      :title="`Sync Status: ${punch.synced == 1 ? 'Synced' : 'Not Synced'}`"
                      :class="{' fa fa-bolt': true, ' text-success': punch.synced == 1, ' text-danger': punch.synced == 0}"
                    />
                    {{ punch.agent_status_type_id == 41 ? 'Normal Punch' : 'Meal Punch' }}
                  </div>
                  <div :class="{'card-body':true, 'bg-warning text-dark': punch.comment === 'AUTO'}">
                    <pre
                      v-if="editingPunch == null || editingPunch !== punch.id"
                      class="fa-2x text-center"
                    >{{ formatPunch(punch.time_punch) }}</pre>
                    <template v-else>
                      <form
                        method="POST"
                        :action="`/time_clock/${punch.id}`"
                      >
                        <input
                          type="hidden"
                          name="_token"
                          :value="csrfToken"
                        >
                        <input
                          type="hidden"
                          name="active_date"
                          :value="activeDate"
                        >
                        <div class="row">
                          <div class="col-6">
                            <input
                              class="form-control"
                              type="number"
                              min="0"
                              max="23"
                              step="1"
                              :value="getPunchHour(punch.time_punch)"
                              name="hour"
                            >
                          </div>
                          <div class="col-6">
                            <input
                              class="form-control"
                              type="number"
                              min="0"
                              max="59"
                              step="1"
                              :value="getPunchMinutes(punch.time_punch)"
                              name="minutes"
                            >
                          </div>
                        </div>
                        <button
                          type="submit"
                          class="btn btn-primary pull-right"
                        >
                          <span class="fa fa-save" />
                        </button>
                        <button
                          type="button"
                          class="btn btn-danger"
                          title="Cancel Editing"
                          @click="editPunch(null)"
                        >
                          <span class="fa fa-remove" />
                        </button>
                      </form>
                    </template>
                    <template v-if="punch.comment === 'AUTO'">
                      <hr>
                      <p class="text-center">
                        Automatically Generated because Agent did not clock out
                      </p>
                    </template>
                    <template v-else-if="punch.comment != null">
                      <hr>
                      <p class="text-center">
                        {{ punch.comment }}
                      </p>
                    </template>
                    <hr>
                    <button
                      type="button"
                      class="btn btn-warning pull-left"
                      title="Edit Punch"
                      @click="editPunch(punch.id)"
                    >
                      <span class="fa fa-pencil" />
                    </button>
                    <form
                      :action="`/time_clock/${punch.id}`"
                      method="POST"
                      @submit="confirmDelete"
                    >
                      <input
                        type="hidden"
                        name="_method"
                        value="DELETE"
                      >
                      <input
                        type="hidden"
                        name="_token"
                        :value="csrfToken"
                      >
                      <input
                        type="hidden"
                        name="active_date"
                        :value="activeDate"
                      >
                      <button
                        type="submit"
                        class="btn btn-danger pull-right"
                        title="Remove Punch"
                      >
                        <span class="fa fa-ban" />
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Datepicker from 'vuejs-datepicker';
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'TimeClock',
    components: {
        Datepicker,
        Breadcrumb,
    },
    props: {
        punches: {
            type: Array,
            required: true,
        },
        activeDate: {
            type: String,
            required: true,
        },
        tpvStaff: {
            type: Object,
            required: true,
        },
        error: {
            type: Number | null,
            required: true,
        },
    },
    data() {
        return {
            dateValue: null,
            highlightedDates: {
                dates: [
                    new Date(`${this.activeDate} 00:00:00`),
                    new Date(),
                ],
            },
            disabledDates: {
                from: new Date(), // only show today and prior dates
            },
            stype: 'normal',
            editingPunch: null,
        };
    },
    computed: {
        csrfToken() {
            return window.csrf_token;
        },
        clockedOut() {
            return this.punches.length === 0 || this.punches.length % 2 === 0;
        },
        activeDateAsDate() {
            return new Date(`${this.activeDate} 00:00:00`);
        },
        timePunches() {
            return this.punches.map((i) => Date.parse(i.time_punch));
        },
        timeWorked() {
            if (this.timePunches.length == 0) {
                return 0;
            }
            let real_timeworked = 0;
            for (let n = 0, len = this.timePunches.length; n < len; n++) {
                if (len % 2 == 1) {
                    if (n > 0 && n < len - 1) {
                        real_timeworked += parseFloat(((((this.timePunches[n] - this.timePunches[n - 1]) / 1000) / 60) / 60).toFixed(2));
                    }
                }
                else if (n % 2 == 1) {
                    real_timeworked += parseFloat(((((this.timePunches[n] - this.timePunches[n - 1]) / 1000) / 60) / 60).toFixed(2));
                }

            }
            if (this.timePunches.length % 2 == 1) {
                real_timeworked += parseFloat(((((Date.now() - this.timePunches[this.timePunches.length - 1]) / 1000) / 60) / 60).toFixed(2));
            }
            let ender = '';
            if (this.timePunches.length % 2 == 1) {
                ender = '+';
            }
            let ftime = parseFloat(real_timeworked);
            let hours = 0;
            let minutes = 0;
            while (ftime > 1) {
                hours += 1;
                ftime -= 1;
            }
            minutes = (60 * ftime).toFixed(0);
            return `${hours.toString()}h, ${minutes.toString()}m${ender}`;
            
        },
    },
    mounted() {
        this.dateValue = `${this.activeDate} 00:00:00`;

        if (this.error === 1) {
            setTimeout(() => {
                window.alert('Only one punch is allowed per minute');
            }, 250);
        }
    },
    methods: {
        getPunchHour(p) {
            const punch = new Date(p);
            return punch.getHours();
        },
        getPunchMinutes(p) {
            const punch = new Date(p);
            return punch.getMinutes();
        },
        editPunch(id) {
            this.editingPunch = id;
        },
        updateDate(v) {
            try {
                window.location.href = `/tpv_staff/${this.tpvStaff.id}/time?date=${v.getFullYear()}-${this.pad(v.getMonth() + 1)}-${this.pad(v.getDate())}`;
            }
            catch (e) {
                alert(`Unable to change date: ${e}`);
            }
        },
        formatPunch(p) {
            try {
                const pd = new Date(p);
                console.log('pd', pd);
                return `${this.pad(pd.getHours())}:${this.pad(pd.getMinutes())}:${this.pad(pd.getSeconds())}`;
            }
            catch (e) {
                console.log(e);
                return p;
            }
        },
        pad(x) {
            const ret = `${x}`;
            if (ret.length === 1) {
                return `0${ret}`;
            }
            return ret;
        },
        confirmDelete(e) {
            if (confirm('Are you sure you with to PERMANENTLY remove this time punch?')) {
                return true;
            }
            e.preventDefault();
            return false;
        },
        normalOrMeal() {
            if (!this.clockedOut) {
                if (confirm('For a Normal punch click OK, for a Meal punch click Cancel')) {
                    this.stype = 'normal';
                }
                else {
                    this.stype = 'meal';
                }
            }
            else {
                this.stype = 'normal';
            }
            return true;
        },
    },
};
</script>

<style scoped>
.inline-flex {
    display: inline-flex;
}
</style>
