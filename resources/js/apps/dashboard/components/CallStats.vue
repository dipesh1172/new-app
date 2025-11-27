<template>
  <div>
    <h5 class="mb-3">
      {{ title }}
    </h5>
    <div class="row">
      <div class="col-md-3 text-center">
        <div class="c-9">
          <span class="number">{{ hold }}</span>
          <span class="c9-title">In Queue</span>
        </div>
      </div>
      <div class="col-md-3 text-center">
        <div class="c-9">
          <span class="number">{{ ready }}</span>
          <span class="c9-title">Ready</span>
        </div>
      </div>
      <div class="col-md-3 text-center">
        <div class="c-9">
          <span class="number digits-4">{{ holdtime }}</span>
          <span class="c9-title">Current Hold Time</span>
        </div>
      </div>
      <div class="col-md-3 text-center">
        <div class="c-9">
          <span class="number">{{ asa }}</span>
          <span class="c9-title">ASA</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
    name: 'CallStats',
    props: {
        title: {
            type: String,
            required: true,
        },
        spanish: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            hold: 0,
            holdtime: '--',
            ready: 0,
            asa: 0,
        };
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
        updateData() {
            if (this.$store.state.queues.length > 0) {
                const qd = Object.assign.apply(null, this.$store.state.queues);
                const queues = Object.keys(qd);
                this.resetData();
                let holdTime = 0;
                for (let i = 0, len = queues.length; i < len; i++) {
                    if (!queues[i].includes('Surveys')) {
                        const isQSpanish = queues[i].includes('panish');
                        const availability = Object.assign.apply(null, qd[queues[i]]['*ActivityStatistics']);
                        const inCallCount = availability['TPV In Progress'] + availability.Reserved;
                        if ((isQSpanish && this.spanish) || (!isQSpanish && !this.spanish)) {
                            const tasksInQueue = qd[queues[i]]['!Tasks'];
                            // if(tasksInQueue >= inCallCount) {
                            //     tasksInQueue -= inCallCount;
                            // }
                            this.hold += tasksInQueue;
                            if (qd[queues[i]]['*TotalAvailableWorkers'] > this.ready) {
                                this.ready = qd[queues[i]]['*TotalAvailableWorkers'];
                            }
                            if (qd[queues[i]].WaitDurationUntilAccepted.avg > this.asa) {
                                this.asa = qd[queues[i]].WaitDurationUntilAccepted.avg;
                            }
                            if (qd[queues[i]]['*LongestTaskWaitingAge'] > holdTime) {
                                holdTime = qd[queues[i]]['*LongestTaskWaitingAge'];
                            }
                        }
                    }
                }
                if (holdTime > 0) {
                    this.holdtime = this.formatTime(holdTime);
                }
            }
        },
        pad(t, c) {
            let ret = t;
            while (ret.length < c) {
                ret = `0${ret}`;
            }
            return ret;
        },
        formatTime(t) {
            let minutes = 0;
            let seconds = t;
            while (seconds > 60) {
                minutes++;
                seconds -= 60;
            }
            let mstr = minutes.toString(10);
            if (mstr.length > 2) {
                mstr = mstr.slice(-2);
            }
            return `${mstr}:${this.pad(seconds.toString(10), 2)}`;
        },
        resetData() {
            this.hold = 0;
            this.ready = 0;
            this.holdtime = '--';
            this.asa = 0;
        },
    },
};
</script>

<style scoped>
.c-9 {
    width: 100%;
    display: inline-block;
    height: 120px;
    border: 1px solid #ccc;
    position: relative;
    text-align: center;
}
.c9-title {
    position: absolute;
    bottom: 0;
    left: 0;
    font-size: 20px;
    line-height: 22px;
    text-align: center;
    width: 100%;
    font-weight: bolder;
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 10px;
    padding-top: 0;
}
.number {
    font-size: 56px;
    font-family:'Courier New', Courier, monospace;
}
.digits-4 {
    font-size: 30px;
    margin-top: 15px;
    display: block;
}
</style>
