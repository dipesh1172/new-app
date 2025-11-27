import { Store } from 'vuex';

const defaultState = {
    channels: [],
    states: [],
    markets: [],
    languages: [],
    commodities: [],
    brands: [],
    vendors: [],
    saleTypes: [],
    user: {},
    session: [],
};

const initializeStore = (initialState = defaultState) => (
    new Store({
        state: {
            ...defaultState,
            ...initialState,
        },
        mutations: {
            setBrands(state, value) {
                state.brands = value;
            },
            setLanguages(state, value) {
                state.languages = value;
            },
            setStates(state, value) {
                state.states = value;
            },
            setMarkets(state, value) {
                state.markets = value;
            },
            setChannels(state, value) {
                state.channels = value;
            },
            setCommodities(state, value) {
                state.commodities = value;
            },
            setVendors(state, value) {
                state.vendors = value;
            },
        },
    })
);

export default initializeStore;