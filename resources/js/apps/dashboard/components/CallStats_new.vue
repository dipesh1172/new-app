<template>
  <div class="row">
    <div class="col-md-2 text-center">
      <div class="c-9">
        <span class="number">{{ computedInQueue }}</span>
        <span class="c9-title">In Queue</span>
      </div>
    </div>
    <div class="col-md-2 text-center">
      <div class="c-9">
        <span class="number">{{ computedReady }}</span>
        <span class="c9-title">Ready</span>
      </div>
    </div>
    <div class="col-md-2 text-center">
      <div class="c-9">
        <span class="number">{{ computedOnCall }}</span>
        <span class="c9-title">On Call</span>
      </div>
    </div>
    <div class="col-md-3 text-center">
      <div class="c-9">
        <span class="number digits-4">{{ computedHoldTime }}</span>
        <span class="c9-title">Hold Time</span>
      </div>
    </div>
    <div class="col-md-3 text-center">
      <div class="c-9">
        <span class="number">{{ computedASA }}</span>
        <span class="c9-title">ASA</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
    name: 'CallStats',
    props: {
        queue: {
            type: Object,
            required: false,
            default: function() {
                return {
                    in_queue: 0,
                    ready: 0,
                    on_call: 0,
                    hold_time: 0,
                    asa: 0,
                };
            },
        },
    },
    computed: {
        computedInQueue() {
            return this.queue.in_queue;
        },
        computedReady() {
            return this.queue.ready;
        },
        computedHoldTime() {
            return this.queue.hold_time > 0 ? this.formatTime(this.queue.hold_time) : '--';
        },
        computedASA() {
            return this.queue.asa > 0 ? Math.round(this.queue.asa) : '0';
        },
        computedOnCall() {
            return this.queue.on_call;
        },
    },
    methods: {
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
                minutes += 1;
                seconds -= 60;
            }
            let mstr = minutes.toString(10);
            if (mstr.length > 2) {
                mstr = mstr.slice(-2);
            }

            return `${mstr}:${this.pad(seconds.toString(10), 2)}`;
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
