<template>
  <div id="app">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        Home
      </li>
      <li class="breadcrumb-item">
        <a href="/contracts">Contracts</a>
      </li>
      <li class="breadcrumb-item">
        <a :href="`/contracts/` + contractConfig.id + `/show`">
          Show :: {{ contractConfig.contract_name }}
        </a>
      </li>
      <li class="breadcrumb-item active">
        Page #{{ contractConfigPage.page }}
      </li>
    </ol>

    <div class="container-fluid">
      <div class="row">
        <div
          id="image-map-wrapper"
          class="col-md-10 border-right text-center"
        >
          <div id="image-map-container">
            <vue-cropper
              ref="cropper"
              :src="contractConfigPage.filepath"
              alt="Source Image"
            />
          </div>
        </div>
        <div class="col-md-2 p-1 m-0 bg-light">
          <form
            method="POST"
            :action="addPageMapping"
            accept-charset="UTF-8"
            autocomplete="off"
          >
            <table
              id="image-mapper-table"
              class="table p-0 m-0"
            >
              <thead>
                <tr>
                  <th>Active</th>
                  <th>Variable</th>
                  <th style="width: 25px" />
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="width: 65px">
                    <div class="control-label input-sm">
                      <input
                        type="radio"
                        name="im[0][active]"
                        value="1"
                      >
                    </div>
                  </td>
                  <td>
                    <select
                      name="im[0][variable]"
                      class="form-control input-sm"
                    >
                      <option value="">
                        ---
                      </option>
                      <option value="customer_first_name">
                        Customer First Name
                      </option>
                      <option value="customer_last_name">
                        Customer Last Name
                      </option>
                      <option value="date">
                        Date
                      </option>
                    </select>
                  </td>
                  <td>
                    <button
                      class="btn btn-default btn-sm remove-row"
                      name="im[0][remove]"
                    >
                      <span class="fa fa-times" />
                    </button>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <th
                    colspan="6"
                    style="text-align: right"
                  >
                    <button
                      type="button"
                      class="btn btn-sm btn-danger add-row"
                    >
                      <span class="fa fa-plus" /> Add New Map
                    </button>
                  </th>
                </tr>
              </tfoot>
            </table>
          </form>
        </div>
      </div>

      <br>
    </div>
  </div>
</template>

<script>
export default {
    name: 'ContractEditor',
    components: {

    },
    props: {
        contractConfig: {
            type: Object,
        },
        contractConfigPage: {
            type: Object,
        },
    },
    data() {
        return {
            addPageMapping: '',
        };
    },
    computed: {
        csrf_token() {
            return csrf_token;
        },
    },
    mounted() {

    },
    methods: {

    },
};
</script>

<style lang="scss" scoped>
.image-mapper-image {
    /* For Opera and <= IE9, we need to add unselectable="on" attribute onto each element */
    /* Check this site for more details: http://help.dottoro.com/lhwdpnva.php */
    -moz-user-select: none; /* These user-select properties are inheritable, used to prevent text selection */
    -webkit-user-select: none;
    -ms-user-select: none; /* From IE10 only */
    user-select: none; /* Not valid CSS yet, as of July 2012 */
    -webkit-user-drag: none; /* Prevents dragging of images/divs etc */
    user-drag: none;
    pointer-events: none;
}
</style>
