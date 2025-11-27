<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: 'Contacts', active: true}
    ]"
  >
    <div 
      role="tabpanel" 
      class="tab-pane active p-1"
    >
      <contact-form
        v-if="formOpen && !isViewer"
        :initial-values="initialValues"
        :submitting="submitting"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />

      <div v-if="formOpen && isViewer">
        <div class="card p-1 mb-0">
          <div class="card-header">
            {{ selectedContact.name }}
            <i 
              class="fa fa-times pull-right" 
              style="cursor:pointer;"
              @click="viewClose"
            />
          </div>
          <div class="card-body">
            <div class="row">
              <div 
                v-for="(phone, i) in selectedContact.phones" 
                :key="i"
                class="col-3 card"
              >
                <div class="card-body">
                  <h4 class="text-center">
                    {{ phone.phone_number.label.label }}
                  </h4>
                  <a 
                    :href="`tel:${phone.phone_number.phone_number}`" 
                    class="btn btn-success"
                  >
                    <i class="fa fa-phone" />
                    {{ phone.phone_number.phone_number }}
                  </a>
                  <span 
                    v-if="phone.phone_number.extension !== null && phone.phone_number.extension !== ''" 
                    class="h5 d-block w-100 text-center"
                  >
                    Ext: {{ phone.phone_number.extension }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div 
        v-if="!formOpen" 
        class="clearfix d-block mb-2 float-right"
      >
        <button
          type="button"
          class="btn btn-info"
          @click="handleNew"
        >
          <i class="fa fa-plus" /> New Contact
        </button>
      </div>

      <custom-table 
        v-if="!formOpen"
        :headers="tableHeaders"
        no-bottom-padding
        :data-grid="brandContacts"
        :data-is-loaded="fetching"
      >
        <template 
          slot="actions" 
          slot-scope="slotProps"
        >
          <a 
            v-if="slotProps.row.email !== null"
            :href="`mailto:${slotProps.row.email}`"
            title="Email" 
            class="btn btn-success"
          ><i class="fa fa-envelope" /></a>
          <button 
            v-if="slotProps.row.phones !== null && slotProps.row.phones instanceof Array && slotProps.row.phones.length > 0" 
            title="View Phone Number(s)"
            type="button" 
            class="btn btn-primary" 
            @click="handleView(slotProps.row)"
          >
            <i class="fa fa-phone" />
          </button>
          
          <button
            :disabled="slotProps.row.id === deleting"
            type="button"
            class="btn btn-danger pull-right"
            @click="handleDelete(slotProps.row)"
          >
            <i class="fa fa-trash" /> Delete
          </button>
          <button
            :disabled="slotProps.row.id === deleting"
            type="button"
            class="btn btn-info pull-right mr-2"
            @click="handleEdit(slotProps.row)"
          >
            <i class="fa fa-pencil" /> Edit
          </button>
        </template>
      </custom-table>

      <pagination
        v-if="fetching"
        :active-page="pageParameter"
        :number-pages="pagesNumber"
        @onSelectPage="selectPage"
      />            
    </div>
  </layout>
</template>

<script>
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import Layout from './Layout';
import ContactForm from './ContactForm';

const defaultValues = {
    name: null,
    title: null,
    email: null,
    brand_contact_type_id: null,
    phones: [],
    remove: [],
};

export default {
    name: 'BrandContacts',
    components: {
        Layout,
        ContactForm,
        CustomTable,
        Pagination,
    },
    props: {
        brand: {
            type: Object,
            default: () => ({}),
        },
        pageParameter: {
            type: Number,
            default: 1,
        },
    },
    data() {
        return {
            initialValues: defaultValues,
            brandContacts: [],
            fetching: false,
            submitting: false,
            deleting: null,
            editingContactId: null,
            pagesNumber: 1,
            formOpen: false,
            isViewer: false,
            selectedContact: null,
            tableHeaders: [
                {
                    label: 'Name',
                    key: 'name',
                },
                {
                    label: 'Title',
                    key: 'title',
                },
                {
                    label: 'Contact Type',
                    key: 'contact_type',
                },
                {
                    label: 'Actions',
                    slot: 'actions',
                },
            ].map((header) => ({...header, canSort: false})),
        };
    },
    mounted() {
        this.fetchContacts();
    },
    methods: {
        async handleSubmit(values) {
            try {
                this.submitting = true;
                const response = await axios[this.editingContactId ? 'put' : 'post'](
                    `/brands/${this.brand.id}/contacts${this.editingContactId ? `/${this.editingContactId}` : ''}`,
                    values,
                );
                this.formOpen = false;
                this.initialValues = defaultValues;
                this.editingContactId = null;
                this.fetchContacts();
            }
            catch (e) {
                console.log('error submitting contact: ', e);
            }
            finally {
                this.submitting = false;
            }
        },
        handleCancel() {
            this.formOpen = false;
            this.editingContactId = null;
            this.initialValues = defaultValues;
        },
        selectPage(page) {
            window.location.href = `/brands/${brand.id}/contacts?page=${page}`;
        },
        handleNew() {
            this.initialValues = defaultValues;
            this.formOpen = true;
            this.editingContactId = null;
        },
        handleView(contact) {
            this.isViewer = true;
            this.formOpen = true;
            this.selectedContact = contact;
        },
        viewClose() {
            this.isViewer = false;
            this.formOpen = false;
            this.selectedContact = null;
        },
        handleEdit(contact) {
            this.initialValues = {
                name: contact.name,
                title: contact.title,
                email: contact.email,
                phones: contact.phones,
                brand_contact_type_id: contact.brand_contact_type_id,
                remove: [],
            };
            this.formOpen = true;
            this.editingContactId = contact.id;
        },
        async handleDelete(contact) {
            if (!confirm('Are you sure to delete this contact?')) { return; }

            try {
                this.deleting = contact.id;
                const response = await axios.delete(`/brands/${this.brand.id}/contacts/${contact.id}`);
                this.deleting = null;
                this.fetchContacts();
            }
            catch (e) {
                console.log('delete error', e);
            }
        },
        async fetchContacts() {
            try {
                this.brandContacts = [];
                this.fetching = false;

                const { data } = await axios.get(`/brands/${this.brand.id}/list-contacts`, {
                    params: {
                        page: this.pageParameter || null,
                    },
                });
        
                this.brandContacts = data.data;
                this.fetching = true;
                this.pagesNumber = data.last_page;
            }
            catch (e) {
                console.log('error fetching brand contacts', e);
            }
        },
    },
};
</script>
