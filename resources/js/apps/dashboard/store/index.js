export default
{
    strict: false,
    state:
    {
        AsOf: null,
        queues: [],
        AvgTaskAcceptanceTime: 0,
        ReservationsAccepted: 0,
        ReservationsRejected: 0,
        ReservationsTimedOut: 0,
        ReservationsCanceled: 0,
        ReservationsRescinded: 0,
        TasksCreated: 0,
        TasksCanceled: 0,
        TasksCompleted: 0,
        TasksDeleted: 0,
        TasksMoved: 0,
        TasksTimedOutInWorkflow: 0,
        WaitDurationUntilAccepted:
        {
            avg: 0,
            min: 0,
            max: 0,
            total: 0,
        },
        ActivityStatistics:
        {
            Break: 0,
            TPVInProgress: 0,
            LoggedOut: 0,
            Reserved: 0,
            Available: 0,
            UnscheduledBreak: 0,
            Meeting: 0,
            Training: 0,
            Meal: 0,
            Coaching: 0,
            System: 0,
            NotReady: 0,
            AfterCallWork: 0,
        },
    },
    mutations:
    {
        clearQueues(state) {
            state.queues = [];
        },
        addQueue(state, x) {
            state.queues.push(x);
        },
        addQueues(state, q) {
            state.queues = [];
            for (let i = 0, len = q.length; i < len; i++) {
                state.queues.push(q[i]);
            }
        },
        updateTime(state, nt) {
            state.AsOf = nt;
        },
        updateGlobalStatuses(state, nt) {
            state.ActivityStatistics.Break = nt.Break;
            state.ActivityStatistics.TPVInProgress = nt.TPVInProgress;
            state.ActivityStatistics.LoggedOut = nt.LoggedOut;
            state.ActivityStatistics.Reserved = nt.Reserved;
            state.ActivityStatistics.Available = nt.Available;
            state.ActivityStatistics.UnscheduledBreak = nt.UnscheduledBreak;
            state.ActivityStatistics.Meeting = nt.Meeting;
            state.ActivityStatistics.Training = nt.Training;
            state.ActivityStatistics.Meal = nt.Meal;
            state.ActivityStatistics.Coaching = nt.Coaching;
            state.ActivityStatistics.System = nt.System;
            state.ActivityStatistics.NotReady = nt.NotReady;
            state.ActivityStatistics.AfterCallWork = nt.AfterCallWork;
        },
    },
    actions:
    {
        gatherStats(context) {
            return new Promise(((resolve, reject) => {
                const processState = (state) => {
                    if ('*ActivityStatistics' in state) {
                        context.commit('updateGlobalStatuses', Object.assign.apply(null, state['*ActivityStatistics'].map((i) => {
                            const y = {};
                            y[Object.keys(i)[0].replace(/ /g, '')] = i[Object.keys(i)[0]];
                            return y;
                        })));
                    }
                    if ('queues' in state) {
                        context.commit('addQueues', state.queues);
                    }
                    context.commit('updateTime', state.AsOf);
                };
                
                axios.get('/callcenter/stats')
                    .then((res) => {
                        const newState = res.data.document;
                        if ('isNew' in newState && newState.isNew) {
                            processState(newState);
                        }
                        resolve();
                    })
                    .catch((e) => {
                        reject(e);
                    });
            }));
        },
    },
};
