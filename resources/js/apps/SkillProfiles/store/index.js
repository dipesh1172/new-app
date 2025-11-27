const skillSortFunction = (a, b) => {
    if (a.FriendlyName < b.FriendlyName) return -1;
    if (a.FriendlyName > b.FriendlyName) return 1;
    return 0;
};

export default {
    strict: false,
    state: {
        skills: [],
        profiles: [],
    },
    mutations: {
        setSkills(state, s) {
            state.skills = s.sort(skillSortFunction);
        },
        addSkill(state, s) {
            state.skills.push(s).sort(skillSortFunction);
        },
        setProfiles(state, p) {
            state.profiles = p;
        },
        addProfile(state, p) {
            state.profiles.push(p);
        },
    },
    actions: {
        loadProfiles(context) {
            axios.get('/skill-profiles/list')
                .then((res) => {
                    context.commit('setProfiles', res.data.skill_profiles);
                })
                .catch((e) => {
                    throw e;
                });
        },
        loadSkills(context) {
            axios.get('/skill-profiles/list-skills')
                .then((res) => {
                    context.commit('setSkills', res.data.skills);
                })
                .catch((e) => {
                    throw e;
                });
        },
    },
};
