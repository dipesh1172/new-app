<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'DNIS', url: '/dnis'},
        {name: `Edit DNIS ${initialValues.dnis}`, url: '/reports/report_agent_statuses', active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Edit DNIS {{ initialValues.dnis }}
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-12">
                <div
                  v-if="flashMessage"
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" />
                  <em>{{ flashMessage }}</em>
                </div>

                <div
                  v-show="errors.length"
                  class="alert alert-danger"
                >
                  <li
                    v-for="(error, index) in errors"
                    :key="index"
                  >
                    {{ error }}
                  </li>
                </div>
                <ValidationObserver ref="validationHandler">
                  <form
                    ref="formHandler"
                    method="POST"
                    :action="action"
                    autocomplete="off"
                    enctype="multipart/form-data"
                  >
                    <input
                      name="_method"
                      type="hidden"
                      value="PUT"
                    >
                    <input
                      :value="csrf_token"
                      type="hidden"
                      name="_token"
                    >
                    <div class="row">
                      <div class="col-md-4">
                        <ValidationProvider
                          v-slot="{ errors }"
                          rules="min:1|required"
                        >
                          <div class="form-group">
                            <label
                              class="h4"
                              for="brand_id"
                            >Brand</label>
                            <select
                              v-model="values.brand_id"
                              name="brand_id"
                              class="form-control form-control-lg"
                            >
                              <option value="">
                                Select a Brand
                              </option>
                              <option
                                v-for="brand in brands"
                                :key="brand.id"
                                :value="brand.id"
                              >
                                {{ brand.name }}
                              </option>
                            </select>
                            <span class="text-danger">{{ errors[0] }}</span>
                          </div>
                        </ValidationProvider>
                      </div>
                      <div class="col-md-4">
                        <ValidationProvider
                          v-slot="{ errors }"
                          rules="min:1|required"
                        >
                          <div class="form-group">
                            <label
                              class="h4"
                              for="platform"
                            >Platform</label>
                            <select
                              v-model="values.platform"
                              name="platform"
                              class="form-control form-control-lg"
                            >
                              <option value="">
                                Select a Platform
                              </option>
                              <option
                                value="focus"
                              >
                                Focus
                              </option>
                              <option
                                value="dxc"
                              >
                                DXC/Legacy
                              </option>
                              <option
                                value="xcally"
                              >
                                XCALLY
                              </option>
                            </select>
                            <span class="text-danger">{{ errors[0] }}</span>
                          </div>
                        </ValidationProvider>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label
                            class="h4"
                            for="skill_name"
                          >Skill Name</label>
                          <input
                            v-model="values.skill_name"
                            type="text"
                            name="skill_name"
                            class="form-control form-control-lg"
                            placeholder="Optional"
                          >
                        </div>
                      </div>
                    </div>

                    <br><hr><br>

                    <p class="text-muted">
                      Hightlighted values are currently <em>NOT</em> configured by the client as active.
                    </p>

                    <div class="row">
                      <div class="col-md-8">
                        <div
                          v-for="(country, index) in countries"
                          :key="country.id"
                          class="container"
                        >
                          <hr v-if="index">
                          <h4>{{ country.name }}</h4>
                          <div class="row">
                            <div
                              v-for="state in statesByCountry[country.id]"
                              :key="state.id"
                              class="col-md-3"
                            >
                              <div class="form-check">
                                <input
                                  v-model="values.states"
                                  :value="state.id"
                                  name="states[]"
                                  class="form-check-input"
                                  type="checkbox"
                                >
                                <label
                                  :class="{'form-check-label':true,'bg-warning text-dark':!configuredStatesForBrand.includes(state.id)}"
                                  :title="`${configuredStatesForBrand.includes(state.id) ? 'This State is enabled for this Brand' : 'This State is NOT enabled for this Brand'}`"
                                >
                                  {{ state.name }}
                                </label>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <label>Config (optional)</label>
                        <p class="text-sm">
                          Config should be empty for most DNIS entries. Config is currently used to setup inbound customer calls (Flyer or normal). 
                          <br>Flyer setup looks like:
                          <pre v-html="JSON.stringify(flyerExampleConfig, null, 4)" />
                          Inbound Customer Only (no sales agent) calls look like:
                          <pre v-html="JSON.stringify(normalExampleConfig, null, 4)" />
                        </p>
                        <textarea
                          v-model="values.config"
                          placeholder="Enter Config Here"
                          name="config"
                          class="form-control form-control-lg"
                          style="height: 400px;"
                        />
                      </div>
                    </div>

                    <br><hr><br>

                    <button
                      type="button"
                      class="btn btn-primary btn-lg"
                      @click="onSubmit"
                    >
                      <i
                        class="fa fa-floppy-o"
                        aria-hidden="true"
                      />
                      Submit
                    </button>
                  </form>
                </ValidationObserver>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
  .text-sm {
    font-size: 0.775rem;
  }
  .form-check-input{
      margin-left: 0;
  }
  pre {
    background-color: #e6e6e6;
    padding: 0.5em;
  }
</style>

<script>
import Breadcrumb from 'components/Breadcrumb';
import { ValidationProvider, ValidationObserver } from 'vee-validate/dist/vee-validate.full.esm';

export default {
    name: 'EditDnis',
    components: {
        Breadcrumb,
        ValidationProvider,
        ValidationObserver,
    },
    props: {
        brandStates: {
            type: Object,
            default: () => {},
        },
        action: {
            type: String,
            default: '',
        },
        brands: {
            type: Array,
            default: () => [],
        },
        channels: {
            type: Array,
            default: () => [],
        },
        markets: {
            type: Array,
            default: () => [],
        },
        countries: {
            type: Array,
            default: () => [],
        },
        states: {
            type: Array,
            default: () => [],
        },
        errors: {
            type: Array,
            default: () => [],
        },
        initialValues: {
            type: Object,
            default: () => defaultValues,
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        const values = {};
        const initialValues = {
            ...this.initialValues,
            states: this.initialValues.states ? this.initialValues.states.split(',') : [],
        };

        const defaultValues = {
            brand_id: '',
            states: [],
            platform: 'focus',
            skill_name: null,
            market_id: null,
            channel_id: null,
            config: null,
        };

        Object.keys(defaultValues).forEach((key) => {
            if (key in initialValues) {
                values[key] = initialValues[key];
            }
            else {
                values[key] = defaultValues[key];
            }
        });

        return {
            values,
            flyerExampleConfig: {
                'type': 'flyer',
                'vendor': 'Vendor Name', 
                'office': 'Office Name', 
                'sales_agent': 'TSR ID',
            },
            normalExampleConfig: {
                'type': 'inbound',
            },
        };
    },
    computed: {
        configuredStatesForBrand() {
            if (!(this.values.brand_id in this.brandStates)) {
                return [];
            }
            return this.brandStates[this.values.brand_id].map((item) => item.state_id).sort();
        },
        csrf_token() {
            return csrf_token;
        },
        statesByCountry() {
            const states = {};
            this.states.forEach((state) => {
                if (!(state.country_id in states)) {
                    states[state.country_id] = [];
                }
                states[state.country_id].push(state);
            });
            return states;
        },
    },
    methods: {
        onSubmit() {
            this.$refs.validationHandler.validate().then((success) => {
                if (!success) {
                    return false;
                }

                this.$refs.formHandler.submit();
            }).catch((e) => console.log(e));
        },
    },
};
</script>
