<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Zip Code Management', url: '/zcm'},
        {name: state+' Zip Codes', url: `/zcm/${country}/${state}`},
        {name: 'Editing Zip Code', active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="row page-buttons mb-2">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">
                <i class="fa fa-th-large" /> <span v-if="zip === null">Creating Zip Code Entry</span><span v-else>Editing Zip Code {{ zip.zip }}</span>
              </div>
              <div class="card-body">
                <div class="form-row">
                  <div class="col-4">
                    <label for="zip">Zip Code</label>
                  </div>
                  <div class="col-8">
                    <input
                      id="zip"
                      v-model="outzip.zip"
                      type="text"
                      name="zip"
                      readonly
                    >
                    <a
                      :href="`https://www.zip-codes.com/zip-code/${zipcode}/zip-code-${zipcode}.asp`"
                      target="_new"
                    >Info Lookup</a>
                  </div>
                </div>
                <div class="form-row">
                  <div class="col-4">
                    <label for="city">City</label>
                  </div>
                  <div class="col-8">
                    <input
                      id="city"
                      v-model="outzip.city"
                      type="text"
                      name="city"
                    >
                  </div>
                </div>
                <div class="form-row">
                  <div class="col-4">
                    <label for="county">County</label>
                  </div>
                  <div class="col-8">
                    <input
                      id="county"
                      v-model="outzip.county"
                      type="text"
                      name="county"
                    >
                  </div>
                </div>
                <div class="form-row">
                  <div class="col-4">
                    <label for="lat">Latitude</label>
                  </div>
                  <div class="col-8">
                    <input
                      id="lat"
                      v-model="outzip.lat"
                      type="text"
                      name="lat"
                    >
                  </div>
                </div>
                <div class="form-row">
                  <div class="col-4">
                    <label for="lon">Longitude</label>
                  </div>
                  <div class="col-8">
                    <input
                      id="lon"
                      v-model="outzip.lon"
                      type="text"
                      name="lon"
                    >
                  </div>
                </div>
                <div class="form-row">
                  <div class="col-4">
                    <label for="timezone">Timezone Offset</label>
                  </div>
                  <div class="col-8">
                    <input
                      id="timezone"
                      v-model="outzip.timezone"
                      type="text"
                      name="timezone"
                    >
                  </div>
                </div>
                <div class="form-row">
                  <div class="col-4">
                    <label for="dst">DST</label>
                  </div>
                  <div class="col-8">
                    <select
                      id="dst"
                      v-model="outzip.dst"
                      name="dst"
                    >
                      <option :value="0">
                        Does not follow DST
                      </option>
                      <option :value="1">
                        Follows DST
                      </option>
                    </select>
                  </div>
                </div>
                <hr>
                <div class="form-row">
                  <button
                    type="button"
                    class="btn btn-primary pull-left"
                    @click="trySave"
                  >
                    Save
                  </button>
                  <button
                    v-if="zip !== null && 1 == 0"
                    type="button"
                    class="btn btn-danger pull-right"
                    @click="doDelete"
                  >
                    Delete
                  </button>
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
    name: 'ZcmEdit',
    components: {
        Breadcrumb,
    },
    props: {
        zip: {
            type: Object,
            validator(i) {
                return i == null || 'zip' in i;
            },
        },
        state: {
            type: String,
            required: true,
        },
        country: {
            type: Number,
            required: true,
        },
        zipcode: {
            type: String,
            required: true, 
        },
    },
    data() {
        return {
            outzip: {
                zip: '',
                country: 1,
                county: '',
                dst: 1,
                lat: 41,
                lon: -87,
                state: '',
                timezone: -6,
            },
        };
    },
    computed: {
        saveURI() {
            if (this.zip !== null) {
                return '/zcm/update';
            }
            return '/zcm/save';
        },
    },
    mounted() {
        if (this.zip !== null) {
            this.outzip = this.zip;
        }
        else {
            this.outzip.zip = this.zipcode;
        }
    },
    methods: {
        trySave() {
            this.outzip.state = this.state;
            window.axios.post(this.saveURI, this.outzip)
                .then((res) => {
                    if (res.data.error !== false) {
                        alert(res.data.error);
                    }
                    else {
                        window.location.reload();
                    }
                }).catch((e) => {
                    let message = e.response.data.message;
                    const errs = Object.keys(e.response.data.errors);
                    for (let i = 0, len = errs.length; i < len; i += 1) {
                        const errors = e.response.data.errors[errs[i]];
                        for (let n = 0, nlen = errors.length; n < nlen; n += 1) {
                            message += `\n${errors[n]}`;
                        }
                    }
                    
                    alert(message);
                });
        },
        doDelete() {
            if (confirm('Are you sure you want to remove this zip code entry?')) {
                window.axios.delete(`/zcm/${this.zipcode}`)
                    .finally(() => {
                        window.location.reload();
                    });
            }
        },
    },
};
</script>
