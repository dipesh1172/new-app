<template>
    <div>
        <div class="row mb-2">
            <div class="col-md-4">
                <div class="c-9">
                    <span class="number">{{ACW}}</span>
                    <span class="c9-title">ACW</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="c-9">
                    <span class="number">{{Lunch}}</span>
                    <span class="c9-title">Lunch</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="c-9">
                    <span class="number">{{Meeting}}</span>
                    <span class="c9-title">Meeting</span>
                </div>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-4">
                <div class="c-9">
                    <span class="number">{{Training}}</span>
                    <span class="c9-title">Training</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="c-9">
                    <span class="number">{{Coaching}}</span>
                    <span class="c9-title">Coaching</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="c-9">
                    <span class="number">{{TotalNotReady}}</span>
                    <span class="c9-title">Total Not Ready</span>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="c-9">
                    <span class="number">{{LoggedIn}}</span>
                    <span class="c9-title">Logged In</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="c-9">
                    <span class="number">{{OnCall}}</span>
                    <span class="c9-title">On Call</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="c-9">
                    <span class="number">{{Break}}</span>
                    <span class="c9-title">Break</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'tpv-statuscodes',
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
    }
}
</script>

<style scoped>
    .c-9 {
        width: 100%;
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
</style>
