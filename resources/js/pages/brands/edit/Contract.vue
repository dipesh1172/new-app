<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: 'Contacts', active: true }
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <div v-if="dataIsLoaded">
        <form
          class="card-body"
          @submit="handleSubmit"
        >
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <input
                  type="text"
                  name="name"
                  class="form-control"
                  placeholder="Name"
                >
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <input
                  name="description"
                  class="form-control"
                  placeholder="Description"
                >
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <input
                  type="file"
                  name="filename"
                  class="form-control"
                >
              </div>
            </div>
            <div class="col-md-6">
              <div class="hide">
                <input
                  name="brand_id"
                  type="hidden"
                  :value="brand.id"
                >
                <input
                  name="client_id"
                  type="hidden"
                  :value="brand.client_id"
                >
                <input
                  name="status"
                  type="hidden"
                  value="0"
                >
              </div>
              <button
                class="btn btn-primary btn-md"
                name="submit"
              >
                Save contract
              </button>
            </div>
          </div>
        </form>
        <custom-table
          :headers="headers"
          :data-grid="brandContracts"
          :data-is-loaded="dataIsLoaded"
          show-action-buttons
          has-action-buttons
          empty-table-message="No brands were found."
        />
      </div>
    </div>
  </layout>
</template>

<script>
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import axios from 'axios';
import Layout from './Layout';

const CREATE_BRAND_CONTRACT_ENDPOINT = '/brands/contract/create';
const GET_BRAND_CONTRACT_ENDPOINT = '/brands/contract/list';
const spinnerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';

const denormalizeBrandContracts = ({ data: brandContracts }) => brandContracts.map((brandContract) => ({
    id: brandContract.id,
    brand_id: brandContract.brand_id,
    client_id: brandContract.client_id,
    name: brandContract.name,
    description: brandContract.description,
    status: !brandContract.status ? 'Uploaded' : 'Generated',
    buttons: [{
        type: 'custom',
        label: 'View',
        target: '_blank',
        url: `${window.AWS_CLOUDFRONT_PATH}/${brandContract.filename}` || '',
        classNames: 'btn-primary',
    }],
}));

export default {
    name: 'BrandContract',
    components: {
        Layout,
        CustomTable,
        CustomInput,
    },
    props: {
        brand: {
            type: Object,
            default: {},
        },
    },
    data() {
        return {
            brandContracts: null,
            dataIsLoaded: false,
            headers: [
                {
                    label: 'Contract name',
                    key: 'name',
                },
                {
                    label: 'Description',
                    key: 'description',
                },
                {
                    label: 'Type',
                    key: 'status',
                },
            ],
        };
    },
    mounted() {
        this.fetch(`${GET_BRAND_CONTRACT_ENDPOINT}/${this.brand.id}`);
    },
    methods: {
        async handleSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const cta = form.submit;
            const valid = form.name.value && form.description.value && form.filename.value;

            if (!valid) { return false; }
            cta.disabled = true;
            cta.innerHTML = `${cta.innerHTML} ${spinnerHTML}`;

            this.saveBrandContract(CREATE_BRAND_CONTRACT_ENDPOINT, form, () => {
                cta.disabled = false;
                cta.querySelector('.fa-spinner').remove();
            });
        },
        async saveBrandContract(url, form, cb) {
            try {
                const formData = new FormData(form);

                await axios.post(url, formData);
                this.fetch(`${GET_BRAND_CONTRACT_ENDPOINT}/${this.brand.id}`, cb);
            }
            catch (error) {
                throw new Error(error.message);
            }
        },
        async fetch(url, cb) {
            try {
                const brandContractResp = await axios.get(url);
                this.brandContracts = denormalizeBrandContracts(brandContractResp);
                this.dataIsLoaded = true;
                cb && cb();
            }
            catch (error) {
                throw new Error(error.message);
            }
        },
    },
};
</script>
