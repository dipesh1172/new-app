<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'DNIS', url: '/dnis'},
        {name: `Add DNIS`, active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Add a DNIS
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
                <ValidationObserver ref="observerHandler">
                  <form
                    ref="formHandler"
                    method="GET"
                    action="/dnis/lookup"
                    accept-charset="UTF-8"
                    autocomplete="off"
                  >
                    <div class="row">
                      <div class="col-md-12">
                        <ValidationProvider
                          v-slot="{ errors }"
                          rules="required|min:1"
                        >
                          <div class="form-group">
                            <label for="type">Dnis Type</label>
                            <select
                              id="dnis_type"
                              v-model="dnisType"
                              name="type"
                              class="form-control form-control-lg"
                            >
                              <option value="">
                                Select one..
                              </option>
                              <option value="1">
                                Local
                              </option>
                              <option value="2">
                                Tollfree
                              </option>
                            </select>
                            <span class="text-danger">{{ errors[0] }}</span>
                          </div>
                        </ValidationProvider>

                        <ValidationProvider
                          v-if="dnisType == 1"
                          v-slot="{ errors }"
                          rules="required"
                        >
                          <div
                            
                            id="areaDiv"
                            class="form-group"
                          >
                            <label for="areacode">Area Code</label>
                            <input
                              ref="areacode"
                              v-model="areacode"
                              class="form-control form-control-lg"
                              placeholder="Enter an Areacode (ie: 918)"
                              name="areacode"
                              type="text"
                            >
                            <span class="text-danger">{{ errors[0] }}</span>
                          </div>
                        </ValidationProvider>
                      </div>
                    </div>				
                    <button
                      class="pull-right btn btn-success"
                      type="button"
                      @click="onSubmit"
                    >
                      <i
                        class="fa fa-search"
                        aria-hidden="true"
                      /> 
                      Search Numbers
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
<script>
import Breadcrumb from 'components/Breadcrumb';

import { ValidationProvider, ValidationObserver } from 'vee-validate/dist/vee-validate.full.esm';

export default {
    name: 'CreateDnis',
    components: {
        Breadcrumb,
        ValidationProvider,
        ValidationObserver,
    },
    props: {
        errors: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            dnisType: 1,
            areacode: null,
        };
    },
    methods: {
        onSubmit() {
            if (this.dnisType == 2) {
                this.$refs.formHandler.submit();
            } 
            this.$refs.observerHandler.validate().then((success) => {
                if (!success) {
                    return false;
                }

                this.$refs.formHandler.submit();
            });
        },
    },
};
</script>
