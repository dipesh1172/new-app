<template>
  <div class="container-fluid">
    <tab-bar :active-item="4" />

    <div class="alerts-container">
      <div class="card-wrapper">
        <div
          v-if="resetComplete"
          class="alert alert-success alert-dismissible fade show"
          role="alert"
        >
          Successfully reset surveys.

          <button
            type="button"
            class="close"
            data-dismiss="alert"
            aria-label="Close"
          >
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div
          v-if="releaseComplete"
          class="alert alert-success alert-dismissible fade show"
          role="alert"
        >
          Successfully released {{ trickle }} {{ language }} surveys to the queue. <br>
          <strong>Note: it may take a moment for numbers to be updated if system is busy.</strong>

          <button
            type="button"
            class="close"
            data-dismiss="alert"
            aria-label="Close"
          >
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="row">
          <div class="col-md-8">
            <div class="form-group mb-2">
              <div class="input-group mb-3">
                <select
                  v-model="language"
                  class="custom-select"
                >
                  <option
                    selected
                    value="english"
                  >
                    English
                  </option>
                  <option
                    selected
                    value="spanish"
                  >
                    Spanish
                  </option>
                </select>

                <select
                  v-model="brand"
                  class="custom-select"
                >
                  <option
                    value="all"
                    selected
                  >
                    All Brands
                  </option>
                  <option
                    v-for="b in brands"
                    :key="b.id"
                    :value="b.id"
                  >
                    {{ b.name }}
                  </option>
                </select>

                <input
                  v-model="trickle"
                  type="text"
                  class="form-control"
                  placeholder="Amount to Release"
                  aria-label="Amount to Release"
                  aria-describedby="basic-addon2"
                >
                <div class="input-group-append">
                  <button
                    :disabled="disableRelease"
                    class="btn btn-success"
                    type="button"
                    @click="handleRelease"
                  >
                    <i
                      v-if="disableRelease"
                      class="fa fa-spinner fa-spin"
                    /> Release Surveys
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="env !== 'production'"
            class="col-md-4 text-right"
          >
            Dev/Staging only:
            <button
              class="btn btn-warning"
              type="button"
              @click="resetCallTime"
            >
              Reset Calltime
            </button>

            <!-- <button
              @click="resetSurveys"
              class="btn btn-danger"
              type="button">
              Reset Surveys
            </button> -->
          </div>
        </div>

        <br class="clearfix">

        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0 pull-left">
              Surveys
            </h5>
          </div>
          <custom-table
            :headers="headers"
            :data-grid="results"
            :data-is-loaded="dataIsLoaded"
            show-action-buttons
            has-action-buttons
            empty-table-message="No surveys were found."
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
// import CustomInput from 'components/CustomInput';
// import CustomTextarea from 'components/CustomTextarea';
import CustomTable from 'components/CustomTable';
import TabBar from '../components/TabBar.vue';

const spinnerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';

export default {
    name: 'Alerts',
    components: {
        // CustomInput,
        // CustomTextarea,
        CustomTable,
        TabBar,
    },
    data() {
        return {
            trickle: 1,
            brands: [],
            brand: 'all',
            language: 'english',
            url: '/list/surveys',
            releaseComplete: false,
            resetComplete: false,
            disableRelease: false,
            env: null,
            results: [],
            headers: [
                {
                    label: 'Brand',
                    key: 'name',
                    serviceKey: 'name',
                    width: '80%',
                },
                {
                    align: 'center',
                    label: 'Ready',
                    key: 'ready',
                    serviceKey: 'ready',
                    width: '20%',
                },
                {
                    align: 'center',
                    label: 'Total',
                    key: 'all_count',
                    serviceKey: 'all_count',
                    width: '20%',
                },
            ],
            dataIsLoaded: false,
        };
    },
    mounted() {
        this.fetch();
        this.env = process.env.NODE_ENV;
    },
    methods: {
        async handleRelease() {
            if (this.disableRelease || this.trickle <= 0) {
                return;
            }
            this.disableRelease = true;
            
            const language = (this.language === 'english')
                ? 1 : 2;
            const url = `/surveys/release/${this.trickle}/${language}/${this.brand}`;
            const response = await axios.get(url);
            this.releaseComplete = true;
            await this.fetch();
                
            setTimeout(() => {
                this.disableRelease = false;
            }, 1000);
            
        },
        async fetch(cb) {
            const response = await axios.get(this.url);
            this.results = response.data;
            this.brands = [];
            this.results.forEach((item) => {
                if (!this.brands.includes(item.brand_id)) {
                    this.brands.push(
                        {
                            id: item.brand_id,
                            name: item.brand_name,
                        }
                    );
                }
            });

            // console.log(this.brands);

            this.dataIsLoaded = true;
            cb && cb();
        },
        resetSurveys() {
            if (confirm('Are you sure you want to reset the surveys back to defaults?')) {
                const response = axios.get('/surveys/reset');
                this.resetComplete = true;
                this.fetch();
            }
        },
        resetCallTime() {
            if (confirm('Are you sure you want to reset the surveys call time?')) {
                const response = axios.get('/surveys/resetCallTime');
                this.resetComplete = true;
                this.fetch();
            }
        },
    },
};
</script>

<style scoped>
  .card-wrapper {
    background: #ffff;
    padding: 20px;
  }
  .card {
    margin-bottom: 0;
  }

  @media (max-width: 480px) {
    .input-group-addon, .input-group-btn, .input-group, .custom-select, .form-control, .input-group-append .btn {
      display: block;
      margin-bottom: 10px;
      clear: both;
      width: 100%;
    }

    .input-group {
        position: relative;
        display: block;
        border-collapse: separate;
    }
  }
</style>
