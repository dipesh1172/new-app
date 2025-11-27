<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Zip Code Management', url: '/zcm'},
        {name: state+' Zip Codes', url: `/zcm/${country}/${state}`, active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="row page-buttons mb-2">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">
                <i class="fa fa-th-large" /> {{ state }} - Zip Codes</span>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-4">
                    Enter partial prefix to filter. To add missing zip code filter for entire zip code and use create button.
                  </div>
                  <div class="col-md-4">
                    <input
                      v-model="search"
                      type="text"
                      placeholder="Filter"
                      class="form-control"
                      @keypress.enter="doFilter"
                    >
                  </div>
                  <div class="col-md-4">
                    <button
                      type="button"
                      class="btn btn-primary"
                      title="Filter Results"
                      @click="doFilter"
                    >
                      <i class="fa fa-search" />
                    </button>
                    <button
                      v-if="search !== null && search.length >= 5 && zips.length == 0"
                      class="btn btn-success"
                      title="Create Zip Entry"
                      @click="selectZip(search)"
                    >
                      <i class="fa fa-edit" />
                    </button>
                  </div>
                </div>
                <hr>
                <div class="row">
                  <div v-if="loading">
                    <i class="fa fa-spinner fa-spin fa-5x" />
                  </div>
                  <div v-if="zips.length == 0 && !loading">
                    No matches found
                  </div>
                  <div
                    v-for="(zip, zi) in zips"
                    :key="zi"
                    class="col-md-3 mb-2"
                  >
                    <button
                      class="btn btn-secondary"
                      @click="selectZip(zip.zip)"
                    >
                      {{ zip.zip }}
                    </button>
                  </div>
                </div>
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
    name: 'ZcmState',
    components: {
        Breadcrumb,
    },
    props: {
        state: {
            type: String,
            required: true,
        },
        country: {
            type: Number,
            required: true,
        },
    },
    data() {
        return {
            zips: [],
            search: null,
            loading: false,
        };
    },
    computed: {
        dataURI() {
            return `/zcm/zips-for-state/${this.state}`;
        },
    },
    mounted() {
        this.loading = true;
        window.axios.get(this.dataURI)
            .then((res) => {
                this.zips = res.data;
            }).finally(() => {
                this.loading = false;
            });
    },
    methods: {
        doFilter() {
            this.loading = true;
            window.axios.get(`${this.dataURI}?search=${this.search}`)
                .then((res) => {
                    this.zips = res.data;
                }).finally(() => {
                    this.loading = false;
                });
        },
        selectZip(zipcode) {
            window.location.href = `/zcm/${zipcode}?state=${this.state}&country=${this.country}`;
        },
    },
};
</script>
