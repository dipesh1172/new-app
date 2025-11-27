<template>
    <table class="table-sm borderless">
        <tr>
            <td>
                <span class="language">English</span>
            </td>
            <td>
                <span class="title">Queue</span>
            </td>
            <td>
                <span class="number">{{english_hold}}</span>
            </td>
            <td>
                <span class="title">Ready</span>
            </td>
            <td>
                <span class="number">{{english_ready}}</span>
            </td>
            <td>
                <span class="title">Current Hold Time</span>
            </td>
            <td>
                <span class="number">{{english_holdtime}}</span>
            </td>
            <td>
                <span class="title">ASA</span>
            </td>
            <td>
                <span class="number">{{english_asa}}</span>
            </td>
            <td>&nbsp;&nbsp;&nbsp;</td>
            <td class="border-left border-top">
                <span class="title">After Call Work</span>
            </td>
            <td class="border-top">
                <span class="number">{{ACW}}</span>
            </td>
            <td class="border-top">
                <span class="title">Lunch</span>
            </td>
            <td class="border-top">
                <span class="number">{{Lunch}}</span>
            </td>
            <td class="border-top">
                <span class="title">Meeting</span>
            </td>
            <td class="border-top">
                <span class="number">{{Meeting}}</span>
            </td>
            <td class="border-top">
                <span class="title">Training</span>
            </td>
            <td class="border-top">
                <span class="number">{{Training}}</span>
            </td>
            <td class="border-top">
                <span class="title">Coaching: </span>
            </td>
            <td class="border-right border-top">
                <span class="number">{{Coaching}}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="language">Spanish</span>
            </td>
            <td>
                <span class="title">Queue</span>
            </td>
            <td>
                <span class="number">{{spanish_hold}}</span>
            </td>
            <td>
                <span class="title">Ready</span>
            </td>
            <td>
                <span class="number">{{spanish_ready}}</span>
            </td>
            <td>
                <span class="title">Current Hold Time</span>
            </td>
            <td>
                <span class="number">{{spanish_holdtime}}</span>
            </td>
            <td>
                <span class="title">ASA</span>
            </td>
            <td>
                <span class="number">{{spanish_asa}}</span>
            </td>
            <td>&nbsp;&nbsp;&nbsp;</td>
            <td class="border-left border-bottom">
                <span class="title">Total Not Ready</span>
            </td>
            <td class="border-bottom">
                <span class="number">{{TotalNotReady}}</span>
            </td>
            <td class="border-bottom">
                <span class="title">Logged In</span>
            </td>
            <td class="border-bottom">
                <span class="number">{{LoggedIn}}</span>
            </td>
            <td class="border-bottom">
                <span class="title">On Call</span>
            </td>
            <td class="border-bottom">
                <span class="number">{{OnCall}}</span>
            </td>
            <td class="border-bottom">
                <span class="title">Break</span>
            </td>
            <td class="border-bottom">
                <span class="number">{{Break}}</span>
            </td>
            <td class="border-bottom">&nbsp;</td>
            <td class="border-right border-bottom">&nbsp;</td>
        </tr>
    </table>
</template>

<script>
export default {
    name: 'call-stats',
    data() {
        return {
            english_hold: 0,
            english_holdtime: '-',
            english_ready: 0,
            english_asa: 0,
            spanish_hold: 0,
            spanish_holdtime: '-',
            spanish_ready: 0,
            spanish_asa: 0
        }
    },
    created() {
        this.$store.subscribe((mutation, state) => {
            if(mutation.type == 'updateTime') {
                this.updateData();
            }
        });

        this.updateData();
    },
    computed: {
        ActivityStatistics() {
            return this.$store.state.ActivityStatistics;
        },
        LoggedIn() {
            const statuses = Object.keys(this.ActivityStatistics);
            let total = 0;
            for(let i = 0, len = statuses.length; i < len; i++) {
                switch(statuses[i]) {
                    case 'LoggedOut':
                    break;

                    default:
                    total += this.ActivityStatistics[statuses[i]];
                }
            }
            return total;
        },
        OnCall() {
            return this.ActivityStatistics.TPVInProgress;
        },
        Break() {
            return this.ActivityStatistics.Break;
        },
        ACW() {
            return this.ActivityStatistics.AfterCallWork;
        },
        Lunch() {
            return this.ActivityStatistics.Meal;
        },
        Meeting() {
            return this.ActivityStatistics.Meeting;
        },
        Training() {
            return this.ActivityStatistics.Training;
        },
        Coaching() {
            return this.ActivityStatistics.Coaching;
        },
        TotalNotReady() {
            const statuses = Object.keys(this.ActivityStatistics);
            let total = 0;
            for(let i = 0, len = statuses.length; i < len; i++) {
                switch(statuses[i]) {
                    case 'Available':
                    case 'Reserved':
                    case 'TPVInProgress':
                    case 'LoggedOut':
                    break;

                    default:
                    total += this.ActivityStatistics[statuses[i]];
                }
            }
            return total;
        }
    },
    methods: {
        updateData() {
            if(this.$store.state.queues.length > 0) {
                const qd = Object.assign.apply(null, this.$store.state.queues);
                const queues = Object.keys(qd);

                this.resetData();

                let english_holdTime = 0;
                let spanish_holdTime = 0;

                for(let i = 0, len = queues.length; i < len; i++) {
                    if (!queues[i].includes("Surveys")) {
                        const isQSpanish = queues[i].includes('panish');
                        const availability = Object.assign.apply(null, qd[queues[i]]['*ActivityStatistics']);
                        const inCallCount = availability['TPV In Progress'] + availability['Reserved'];
                        
                        let tasksInQueue = qd[queues[i]]['!Tasks'];
                        if ((isQSpanish))
                        {
                            this.spanish_hold += tasksInQueue;
                            if(qd[queues[i]]['*TotalAvailableWorkers'] > this.spanish_ready) {
                                this.spanish_ready = qd[queues[i]]['*TotalAvailableWorkers'];
                            }
                            if(qd[queues[i]].WaitDurationUntilAccepted.avg > this.spanish_asa) {

                                this.spanish_asa = qd[queues[i]].WaitDurationUntilAccepted.avg;
                            }
                            if(qd[queues[i]]['*LongestTaskWaitingAge'] > this.spanish_holdTime) {
                                this.spanish_holdTime = qd[queues[i]]['*LongestTaskWaitingAge'];
                            }
                        } 
                        else 
                        {
                            this.english_hold += tasksInQueue;
                            if(qd[queues[i]]['*TotalAvailableWorkers'] > this.english_ready) {
                                this.english_ready = qd[queues[i]]['*TotalAvailableWorkers'];
                            }
                            if(qd[queues[i]].WaitDurationUntilAccepted.avg > this.english_asa) {

                                this.english_asa = qd[queues[i]].WaitDurationUntilAccepted.avg;
                            }
                            if(qd[queues[i]]['*LongestTaskWaitingAge'] > this.english_holdTime) {
                                this.english_holdTime = qd[queues[i]]['*LongestTaskWaitingAge'];
                            }
                        }
                    }
                }

                if(this.spanish_holdTime > 0) {
                    this.spanish_holdtime = this.formatTime(this.spanish_holdTime);
                }
                if (this.english_holdTime > 0) {
                    this.english_holdTime = this.formatTime(this.english_holdTime);
                }
            }
        },
        pad(t,c) {
            let ret = t;
            while(ret.length < c) {
                ret = '0' + ret;
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
            if(mstr.length > 2) {
                mstr = mstr.slice(-2);
            }

            return mstr + ':' + this.pad(seconds.toString(10),2);
        },
        resetData() {
            this.spanish_hold = 0;
            this.spanish_ready = 0;
            this.spanish_holdtime = '-';
            this.spanish_asa = 0;

            this.english_hold = 0;
            this.english_ready = 0;
            this.english_holdtime = '-';
            this.english_asa = 0;
        },
    }
}
</script>

<style scoped>
.language {
    font-size: 16px;
    font-weight: bolder;
}
.title {
    font-size: 14px;
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.number {
    font-size: 14px;
    font-weight: bolder;
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.border-left {
    border-left: 2px solid darkgrey !important;
}

.border-right {
    border-right: 2px solid darkgrey !important;
}

.border-top {
    border-top: 2px solid darkgrey !important;
}

.border-bottom {
    border-bottom: 2px solid darkgrey !important;
}
</style>