<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Zip Code Management', url: '/zcm', active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="row page-buttons mb-2">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">
                <i class="fa fa-th-large" /> Zip Code Management <span v-if="selCountry !== null">| {{ countries[selCountry].country }}</span>
              </div>
              <div class="card-body">
                <template v-if="selCountry == null">
                  <div class="row">
                    <div
                      v-for="(country, ci) in countries"
                      :key="ci"
                      class="col-md-6"
                    >
                      <button
                        class="btn btn-lg btn-primary"
                        @click="doSelCountry(ci)"
                      >
                        {{ country.country }}
                      </button>
                    </div>
                  </div>
                </template>
                <template v-else>
                  <button
                    class="btn btn-secondary"
                    @click="doSelCountry(null)"
                  >
                    Back
                  </button>
                  <hr>
                  
                  <div
                    v-for="(row,ri) in statesForSelectedCountry"
                    :key="ri"
                    class="row mb-2"
                  >
                    <div
                      v-for="(state, si) in row"
                      :key="si"
                      class="col-md-3"
                    >
                      <button
                        class="btn btn-primary"
                        @click="doSelectState(state.state_abbrev)"
                      >
                        {{ state.name }}
                      </button>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'ZcmIndex',
    components: {
        Breadcrumb,
    },
    props: {
        states: {
            type: Array,
            required: true,
        },
        countries: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            selCountry: null,
        };
    },
    computed: {
        statesForSelectedCountry() {
            return this.statesForCountry(this.countries[this.selCountry].id);
        },
    },
    methods: {
        doSelCountry(i) {
            this.selCountry = i;
        },
        statesForCountry(i) {
            const temp = this.states.filter((x) => x.country_id == i);
            const out = [];
            for (let i = 0, j = temp.length; i < j; i += 4) {
                out.push(temp.slice(i, i + 4));
            }
            return out;
        },
        doSelectState(i) {
            window.location.href = `/zcm/${this.countries[this.selCountry].id}/${i}`;
        },
    },
};
</script>
