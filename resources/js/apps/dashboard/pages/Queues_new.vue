<template>
  <div class="container-fluid">
    <tab-bar :active-item="2" />
    <div class="tab-content">
      <div class="container-fluid p-0">
        <div class="card p-0 mb-0">
          <div class="card-header">
            <h5 class="card-title mt-3 mb-0 pull-left">
              In Queue
            </h5>
            <span class="pull-right">
              Last Updated: 
              <span v-if="updatedTime">{{ updatedTime | moment("MM/DD/YYYY, h:mm:ss a") }}</span>
              <span v-else><i class="fa fa-spinner fa-spin" /> Loading ...</span>
            </span>
          </div>
          <div class="card-body table-responsive p-0">
            <table class="table table-bordered mb-0">
              <thead>
                <tr>
                  <th>
                    Platform
                  </th>
                  <th>
                    Task Queue
                  </th>
                  <th>
                    Calls
                  </th>
                  <th>
                    Longest Wait
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="(queue, index) in inQueueList" 
                  :key="index"
                >
                  <td>{{ queue.platform }}</td>
                  <td>{{ formattedTaskQueue(queue.task_queue) }}</td>
                  <td>{{ queue.count }}</td>
                  <td>{{ formatTime(queue.task_age) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import TabBar from '../components/TabBar.vue';

export default {
    name: 'Queues',
    components: {
        TabBar,
    },    
    data() {
        return {
            timer: null,
            isLoading: false,
            inQueueList: [],
            lastUpdate: null,
        };
    },
    computed: {
        updatedTime() {
            return this.lastUpdate;
        },
    },
    created() {
        this.updateData();
        this.timer = setInterval(this.updateData, 5000);
    },
    destroyed() {
        clearInterval(this.timer);
    },
    methods: {
        formattedTaskQueue(t) {
            let localT = t;
            if (localT.includes('Outbound') !== false) {
                return localT;
            }
            localT = localT.split('z_').join('');
            localT = localT.split('_').join(' ');
            localT = localT.split('DXC_').join(' ');
            localT = localT.split('DXC').join('');
            return localT;
        },
        updateData() {
            this.isLoading = true;
            axios.get('/callcenter/stats-2')
                .then((r) => {
                    this.inQueueList = r.data.document.queues.in_queue_list;
                    this.lastUpdate = r.data.document.AsOf;
                })
                .catch((e) => {
                    console.log('catch');
                    console.log(e.message);
                });
            this.isLoading = false;
        },
        formatTime(t) {
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
        pad(t, c) {
            let ret = t;
            while (ret.length < c) {
                ret = `0${ret}`;
            }
            return ret;
        },
        queueColor(platform) {
            if (platform === 'dxc') {
                return 'queue_dxc';
            }
            return 'queue_focus';
        },
    },
};
</script>

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

td {
  font-size: 30px;
}

.queue_dxc {
  color: black;
  background-color: #FED8B1;
}

.queue_focus {
  color: black;
  background-color: #ADD8E6;
}
</style>
