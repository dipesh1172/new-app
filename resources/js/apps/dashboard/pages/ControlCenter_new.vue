<template>
  <div class="container-fluid">
    <tab-bar :active-item="0" />
    <div class="tab-content">
      <div class="row pt-3 pr-3 pl-3">
        <div class="col-md-12">
          <h3 class="pull-left">
            Call Stats
          </h3>
          <span class="pull-right">
            Last Updated:
            <span v-if="updatedTime">{{ updatedTime | moment("MM/DD/YYYY, h:mm:ss a") }}</span>
            <span v-else><i class="fa fa-spinner fa-spin" /> Loading ...</span>
          </span>
        </div>
      </div>

      <div class="row pt-3 pr-3 pl-3">
        <div class="col-md-4">
          <h1 class="mb-2 c-9 number text-center">
            DXC
          </h1>
        </div>
        <div class="col-md-4">
          <h1 class="text-center">
            <h1 class="c-9 number">
              {{ OnCall }}
            </h1><br>
            <h3 style="margin-top: -50px">
              On Call
            </h3>
          </h1>
        </div>
        <div class="col-md-4">
          <h1 class="mb-2 c-9 number text-center">
            FOCUS
          </h1>
        </div>
      </div>

      <div class="row pt-3 pr-3 pl-3">
        <!-- headers -->
        <div class="col-md-4">
          <div class="row">
            <div class="col-md-6 text-center">
              <h3>English</h3>
            </div>
            <div class="col-md-6 text-center">
              <h3>Spanish</h3>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          &nbsp;
        </div>
        <div class="col-md-4">
          <div class="row">
            <div class="col-md-6 text-center">
              <h3>English</h3>
            </div>
            <div class="col-md-6 text-center">
              <h3>Spanish</h3>
            </div>
          </div>
        </div>
      </div>

      <div class="row pr-3 pl-3">
        <!-- In Queue -->
        <div class="col-md-4">
          <!-- In Queue DXC -->
          <div class="row">
            <div class="col-md-6 text-center">
              <div :class="{'c-9 number text-center':true,'text-danger':computedQueues.dxc.English.in_queue > 0}">
                {{ computedQueues.dxc.English.in_queue }}
              </div>
            </div>
            <div class="col-md-6 text-center">
              <div :class="{'c-9 number text-center':true,'text-danger':computedQueues.dxc.Spanish.in_queue > 0}">
                {{ computedQueues.dxc.Spanish.in_queue }}
              </div>
            </div>
          </div>
          <!-- End In Queue DXC -->
        </div>
        <div class="col-md-4 number-header">
          In Queue
        </div>
        <div class="col-md-4">
          <!-- In Queue FOCUS -->
          <div class="row">
            <div class="col-md-6 text-center">
              <div :class="{'c-9 number text-center':true,'text-danger':computedQueues.focus.English.in_queue > 0}">
                {{ computedQueues.focus.English.in_queue }}
              </div>
            </div>
            <div class="col-md-6 text-center">
              <div :class="{'c-9 number text-center':true,'text-danger':computedQueues.focus.Spanish.in_queue > 0}">
                {{ computedQueues.focus.Spanish.in_queue }}
              </div>
            </div>
          </div>
          <!-- End In Queue FOCUS -->
        </div>
      </div>

      <div class="row pr-3 pl-3">
        <!-- Ready -->
        <div class="col-md-4">
          <!-- Ready DXC -->
          <div class="row">
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ computedQueues.dxc.English.ready }}
              </div>
            </div>
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ computedQueues.dxc.Spanish.ready }}
              </div>
            </div>
          </div>
          <!-- End Ready DXC -->
        </div>
        <div class="col-md-4 number-header extra-header">
          Ready
        </div>
        <div class="col-md-4">
          <!-- Ready FOCUS -->
          <div class="row">
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ computedQueues.focus.English.ready }}
              </div>
            </div>
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ computedQueues.focus.Spanish.ready }}
              </div>
            </div>
          </div>
          <!-- End Ready FOCUS -->
        </div>
      </div>

      <div class="row pr-3 pl-3">
        <!-- Hold Time -->
        <div class="col-md-4">
          <!-- Hold Time DXC -->
          <div class="row">
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ computedQueues.dxc.English.hold_time }}
              </div>
            </div>
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ computedQueues.dxc.Spanish.hold_time }}
              </div>
            </div>
          </div>
          <!-- Hold Time Queue DXC -->
        </div>
        <div class="col-md-4 number-header">
          Hold Time
        </div>
        <div class="col-md-4">
          <!-- Hold Time FOCUS -->
          <div class="row">
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ computedQueues.focus.English.hold_time }}
              </div>
            </div>
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ computedQueues.focus.Spanish.hold_time }}
              </div>
            </div>
          </div>
          <!-- Hold Time Queue FOCUS -->
        </div>
      </div>

      <div class="row pr-3 pl-3 pb-2">
        <!-- ASA -->
        <div class="col-md-4">
          <!-- ASA DXC -->
          <div class="row">
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ Math.round(computedQueues.dxc.English.asa) }}
              </div>
            </div>
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ Math.round(computedQueues.dxc.Spanish.asa) }}
              </div>
            </div>
          </div>
          <!-- ASA Queue DXC -->
        </div>
        <div class="col-md-4 number-header extra-header">
          ASA
        </div>
        <div class="col-md-4">
          <!-- ASA FOCUS -->
          <div class="row">
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ Math.round(computedQueues.focus.English.asa) }}
              </div>
            </div>
            <div class="col-md-6 text-center">
              <div class="c-9 number text-center">
                {{ Math.round(computedQueues.focus.Spanish.asa) }}
              </div>
            </div>
          </div>
          <!-- ASA Queue FOCUS -->
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import TabBar from '../components/TabBar.vue';

export default {
    name: 'ControlCenter',
    components: {
        TabBar,
    },
    data() {
        return {
            activeItem: 0,
            timer: null,
            queues: {
                'in_queue_list': [],
                'dxc': {
                    'English': {
                        'in_queue': 0,
                        'ready': 0,
                        'on_call': 0,
                        'hold_time': 0,
                        'asa': 0,
                    },
                    'Spanish': {
                        'in_queue': 0,
                        'ready': 0,
                        'on_call': 0,
                        'hold_time': 0,
                        'asa': 0,
                    },
                },
                'focus': {
                    'English': {
                        'in_queue': 0,
                        'ready': 0,
                        'on_call': 0,
                        'hold_time': 0,
                        'asa': 0,
                    },
                    'Spanish': {
                        'in_queue': 0,
                        'ready': 0,
                        'on_call': 0,
                        'hold_time': 0,
                        'asa': 0,
                    },
                },
            },
            lastUpdate: null,
            activityStatistics: null,
            onCall: 0,
        };
    },
    computed: {
        updatedTime() {
            return this.lastUpdate;
        },
        computedQueues() {
            return this.queues;
        },
        OnCall() {
            return this.onCall;
        },
    },
    destroyed() {
        clearInterval(this.timer);
    },
    created() {
        this.updateData();
        this.timer = setInterval(this.updateData, 5000);
    },
    methods: {
        updateData() {
            axios.get('/callcenter/stats-2')
                .then((r) => {
                    if (r && 'data' in r && r.data !== null && 'document' in r.data) {
                        this.activityStatistics = r.data.document['*ActivityStatistics'];
                        const status = Object.keys(this.activityStatistics);
                        for (let i = 0; i < status.length; i++) {
                            if (this.activityStatistics[i].friendly_name === 'TPV In Progress') {
                                this.onCall = this.activityStatistics[i].workers;
                            }
                        }
                        this.queues = r.data.document.queues;
                        this.lastUpdate = r.data.document.AsOf;
                    }
                })
                .catch((e) => {
                    console.log(e);
                });
        },
        pad(t, c) {
            let ret = t;
            while (ret.length < c) {
                ret = `0${ret}`;
            }
            return ret;
        },
        formatTime(t) {
            if (t === 0) {
                return '--';
            }
            let minutes = 0;
            let seconds = t;
            while (seconds > 59) {
                minutes += 1;
                seconds -= 60;
            }
            let mstr = minutes.toString(10);
            if (mstr.length > 2) {
                mstr = mstr.slice(-2);
            }
            return `${mstr}:${this.pad(seconds.toString(10), 2)}`;
        },
        textColor(t) {
            if (t > 0) {
                return 'c-9-red';
            }
            return 'c-9';
        },
    },
};
</script>

<style scoped>
h5 {
    margin-bottom: 0;
}
.c-9 {
    width: 100%;
    display: inline-block;
    height: 130px;
    border: 1px solid #ccc;
    text-align: center;
    font-weight: bold;
    margin-bottom: 10px;
    padding-top: 10px;
}
.c-9-title {
    width: 100%;
    display: inline-block;
    height: 130px;
    text-align: center;
    font-size: 64px;
    padding-top: 20px;
}
.number {
    font-size: 72px;
    font-family:'Courier New', Courier, monospace;
}
.number-header {
  font-size: 60px;
  font-family:'Courier New', Courier, monospace;
  text-align: center;
  line-height: 60px;
  padding-top:10px;
  overflow:hidden;
}
.extra-header {
  padding-top: 30px;
}
.digits-4 {
    font-size: 30px;
    margin-top: 15px;
    display: block;
}
.c-9-red {
    width: 100%;
    display: inline-block;
    height: 130px;
    border: 1px solid #ccc;
    position: relative;
    text-align: center;
    color: red;
    margin-bottom: 10px;
    font-weight: bolder;
    padding-top: 10px;
}
</style>
