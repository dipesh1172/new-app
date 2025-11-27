<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'DNIS', url: '/dnis'},
        {name: 'DNIS Lookup', active: true}
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> DNIS Lookup
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

                <table class="table table-bordered table-shaded">
                  <tr>
                    <th>Number</th>
                    <th v-if="type == 1">
                      Location
                    </th>
                    <th width="75" />
                  </tr>
                  <template v-if="numbers.length">
                    <tr
                      v-for="(number, index) in numbers"
                      :key="index"
                    >
                      <td>{{ number.friendlyName }}</td>
                      <td v-if="type == 1">
                        {{ number.rateCenter }}, {{ number.region }}
                      </td>
                      <td>
                        <a
                          :href="`/dnis/choose?number=${number.phoneNumber}&type=${type}`"
                          class="btn btn-success"
                        ><i
                          class="fa fa-check"
                          aria-hidden="true"
                        /> Choose</a>
                      </td>
                    </tr>
                  </template>
                  <tr v-if="dataIsLoaded && !numbers.length">
                    <td :colspan="type == 1? 3 : 2">
                      No numbers were found.
                    </td>
                  </tr>
                  <tr v-if="!dataIsLoaded">
                    <td
                      :colspan="type == 1? 3 : 2"
                      class="text-center"
                    >
                      <span class="fa fa-spinner fa-spin fa-2x" />
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!--/.col-->
    </div> 
  </div>
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'LookupDnis',
    components: {
        Breadcrumb,
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
            type: this.getParams().type,
            numbers: [],
            dataIsLoaded: false,
        };
    },
    mounted() {
        axios.get(`/dnis/list_lookupPhone?${this.filterParams(this.getParams())}`)
            .then(({data}) => {
                this.numbers = data;
                this.dataIsLoaded = true;
            })
            .catch(console.log);
    },
    methods: {
        filterParams({type, areacode}) {
            return [
                type ? `&type=${type}` : '',
                areacode ? `&areacode=${areacode}` : '',
            ].join('');
        },
        getParams() {
            const url = new URL(window.location.href);
            const type = url.searchParams.get('type') || 1;
            const areacode = url.searchParams.get('areacode');
            return {
                type,
                areacode,
            };
        },
    },
};
</script>
