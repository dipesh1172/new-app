<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Billing', url: '/billing'},
        {name: 'Credits and Charges', active: true}
      ]"
    />

    <div class="container-fluid mt-3">
      <ul class="nav nav-tabs">
        <li class="nav-item">
          <a
            class="nav-link"
            href="/billing"
          ><i class="fa fa-list" /> Invoices</a>
        </li>
        <li class="nav-item">
          <a
            class="nav-link active"
            href="/billing/charges"
          ><i class="fa fa-usd" /> Charges and Credits</a>
        </li>
        <li class="nav-item">
          <a
            class="nav-link"
            href="/billing/create"
          ><i class="fa fa-plus-square" /> Generate Invoices</a>
        </li>
      </ul>
      <div class="card-wrapper">
        <div class="card">
          <div class="card-header">
            <select
              v-model="tab"
              class="form-control d-inline w-25"
            >
              <option value="uninvoiced">
                Not Invoiced
              </option>
              <option value="invoiced">
                Invoiced
              </option>
            </select>

            <a
              :href="exportUrl"
              class="btn btn-primary m-0"
              :class="{'disabled': !chargesData.length}"
            >
              <i
                class="fa fa-download"
                aria-hidden="true"
              /> 
              Data Export
            </a>
                
            <div class="form-group mb-0 pull-right">
              <button
                v-if="tab == 'uninvoiced'"
                class="btn btn-sm btn-success m-0"
                @click="handleAddCharge"
              >
                <i class="fa fa-plus" /> Add charge or credit
              </button>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="input-group">
              <input
                v-model="search"
                type="text"
                class="form-control"
                placeholder="Search"
                @keyup.enter="doSearch"
              >
              <div class="input-group-append">
                <button
                  class="btn btn-primary"
                  type="button"
                  @click="doSearch"
                >
                  <i class="fa fa-search" />
                </button>
              </div>
            </div>
            <custom-table
              :headers="headers"
              :data-grid="chargesData"
              :data-is-loaded="dataIsLoaded"
              show-action-buttons
              has-action-buttons
              empty-table-message="No charges were found."
              @sortedByColumn="sortData"
            />
          </div>
        </div>
        <pagination
          v-if="dataIsLoaded"
          :active-page="activePage"
          :number-pages="numberPages"
          @onSelectPage="handleSelectPage"
        />
      </div>
      <div
        id="createChargeModal"
        class="modal fade"
        role="dialog"
        tabindex="-1"
      >
        <div
          class="modal-dialog modal-dialog-centered"
          role="document"
        >
          <div class="modal-content">
            <div class="modal-header">
              <h4
                v-if="currentChargeData !== null"
                class="modal-title"
              >
                <span v-if="currentChargeData.id == null">Create</span>
                <span v-else>Edit</span> charge/credit
              </h4>
            </div>
            <div class="modal-body">
              <charge-form
                :handle-submit="handleSubmit"
                :current-charge-data="currentChargeData"
                :categories-data="categoriesData"
                :brands-data="brandsData"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';
import { getObjParamsFromStr, getStrParamsFromObj } from 'utils/stringHelpers';
import ChargeForm from './ChargeForm';
import { ROUTES, FIELDS, SPINNER_HTML } from './constants';
import data from './data';

export default {
    name: 'Charges',
    components: {
        CustomTable,
        Pagination,
        ChargeForm,
        Breadcrumb,
    },
    data() {
        return data;
    },
    computed: {
        getBaseUrl() {
            return location.href.split('?')[0];
        },
    },
    watch: {
        tab() {
            this.dataIsLoaded = false;
            this.chargesData = [];
            if (this.tab === 'invoiced') {
                this.headers.push({
                    label: 'Invoice Date',
                    key: 'invoice_bill_date',
                    canSort: true,
                    sorted: '',
                });
            }
            else {
                this.headers = this.headers.filter((v, i, a) => {
                    if (i + 1 < a.length) {
                        return true;
                    }
                    return false;
                });
            }
            this.browserState.tab = this.tab;
            this.browserState.sort = 'updated_at';
            this.browserState.dir = 'asc';
            this.browserState.search = '';
            this.search = '';
            this.updateBrowserState();
            const paramsStr = window.location.href.split('?')[1] || '';
            this.fetch(`${ROUTES.CHARGES_LIST}?${paramsStr}&${this.objToURL(this.browserState)}`);
            this.exportUrl = `${ROUTES.CHARGES_LIST}?${this.objToURL(this.browserState)}&export=csv`;
            this.paramsObj.tab = this.tab;            
        },
        browserState() {
            this.tab = this.browserState.tab;
        },
    },
    mounted() {
        this.getBrowserState();
        this.fetchClients(ROUTES.CLIENTS_LIST);
        this.fetchCategories(ROUTES.INVOICE_CATEGORIES_LIST);

        const paramsStr = window.location.href.split('?')[1] || '';
        this.fetch(`${ROUTES.CHARGES_LIST}?${paramsStr}&${this.objToURL(this.browserState)}`);

        this.exportUrl = `${ROUTES.CHARGES_LIST}?${this.objToURL(this.browserState)}&export=csv`;

        if (paramsStr) {
            this.paramsObj = getObjParamsFromStr(paramsStr);
        }
        $('#createChargeModal').on('hide.bs.modal', () => {
            this.updateOnHide();
        });
        this.updateBrowserState();
    },
    methods: {
        sortData(i, a, c) {
            if (i !== undefined) {
                if (this.browserState.sort === i) {
                    if (this.browserState.dir === 'asc') {
                        this.browserState.dir = 'desc';
                    }
                    else {
                        this.browserState.dir = 'asc';
                    }
                }
                else {
                    this.$set(this.browserState, 'sort', i);
                }
            }
            this.updateBrowserState();
            this.dataIsLoaded = false;
            this.chargesData = [];
            const paramsStr = window.location.href.split('?')[1] || '';
            this.fetch(`${ROUTES.CHARGES_LIST}?${paramsStr}&${this.objToURL(this.browserState)}`);
        },
        getBrowserState() {
            let currentState = window.history.state;
            if (currentState === null) {
                currentState = {
                    tab: 'uninvoiced',
                    sort: 'updated_at',
                    dir: 'asc',
                    search: '',
                };
            }
            if (this.tab !== currentState.tab) {
                this.tab = currentState.tab;
            }
            this.search = currentState.search;
            this.browserState = currentState;
        },
        objToURL(o) {
            const out = [];
            const keys = Object.keys(o);
            for (let i = 0, len = keys.length; i < len; i += 1) {
                out.push(`${keys[i]}=${o[keys[i]]}`);
            }
            return out.join('&');
        },
        updateBrowserState() {
            window.history.replaceState(
                this.browserState,
                '',
                `${this.getBaseUrl}?${this.objToURL(this.browserState)}`,
            );
        },
        updateOnHide() {
            this.currentChargeData = null;
        },
        doSearch() {
            this.browserState.search = this.search;
            this.sortData();
        },
        handleSubmit(e) {
            e.preventDefault();
            const form = e.target;
            // Validating form
            let validator = true;
            if (!form.brand.value) {
                this.validationErrors.push('You need to select a brand');
                validator = false;
            }
            if (!form.category.value) {
                this.validationErrors.push('You need to select a category');
                validator = false;
            }
            if (!validator) {
                return;
            }
            const cta = form.submit;
            cta.disabled = true;
            cta.innerHTML = `${cta.innerHTML} ${SPINNER_HTML}`;

            /* if (!this.isValid(form, FIELDS)) {
                cta.disabled = false;
                cta.querySelector('.fa-spinner').remove();
                return false;
            }*/

            const data = {
                id: form.id.value,
                owner: form.owner.value,
                ticket: form.ticket.value,
                category: form.category.value,
                duration: form.duration.value !== '' ? form.duration.value : 1,
                rate: form.rate.value,
                date_of_work: form.date_of_work.value,
                description: form.description.value,
                brand: form.brand.value,
                rate_is_credit: form.rate_is_credit.checked,
            };

            console.log('data is', data);
            console.log('credit is', form.rate_is_credit.checked);

            this.saveCharge(data, (c) => {
                cta.disabled = false;
                const spinners = document.querySelector('.fa-spinner');
                if (spinners) {
                    spinners.remove();
                }
                if (c) {
                    $('#createChargeModal').modal('hide');
                }
            });
        },
        isValid(form, FIELDS) {
            let valid = true;

            Object.keys(FIELDS).forEach((key) => {
                const errorMessage = document.createElement('span');
                errorMessage.innerHTML = 'Field required';
                errorMessage.classList.add('text-danger');
                const fieldParent = form[FIELDS[key]].parentNode;
                const currentErrorMessage = fieldParent.querySelector('.text-danger');
                currentErrorMessage && currentErrorMessage.remove();

                if (!form[FIELDS[key]].value) {
                    valid = false;
                    fieldParent.appendChild(errorMessage);
                }
            });
            return valid;
        },
        cleanErrorMessages() {
            const form = this.$el.querySelector('.add-charge-form');
            const errorMessages = form.querySelectorAll('.text-danger');
            Array.prototype.forEach.call(errorMessages, (el) => el.remove());
        },
        handleAddCharge() {
            const form = this.$el.querySelector('.add-charge-form');
            this.cleanErrorMessages();
            this.validationErrors = [];
            form.reset();
            this.currentChargeData = {};
            $('#createChargeModal').modal('show');
        },
        handleDelete(e) {
            const cta = e.currentTarget;
            const currentChargeID = cta.href.split('/').pop();
            cta.classList.add('disabled');
            const iElemt = cta.querySelector('i');
            cta.innerHTML = `${SPINNER_HTML} Delete`;

            this.deleteCharge(currentChargeID, () => {
                cta.classList.remove('disabled');
                cta.prepend(iElemt);
                cta.querySelector('.fa-spinner').remove();
            });
        },
        handleEdit(e) {
            this.currentChargeData = null;

            const currentChargeID = e.currentTarget.href.split('/').pop();
            const currentChargeData = this.chargesData.find(
                (charge) => +charge.id === +currentChargeID,
            );
            this.isAdd = false;
            this.cleanErrorMessages();
            this.currentChargeData = currentChargeData;
            $('#createChargeModal').modal('show');
        },
        handleSelectPage(page) {
            console.log(this.paramsObj);

            const currentParamsObj = { ...this.paramsObj, page };
            console.log(currentParamsObj);

            const paramsStr = getStrParamsFromObj(currentParamsObj);
            console.log(paramsStr);

            const url = `${ROUTES.CHARGES_LIST}?${paramsStr}&${this.objToURL(this.browserState)}`;
            console.log(url);

            this.fetch(url);
        },
        getShortDescription(d) {
            if (d == null || d === undefined) {
                return '';
            }
            if (d.includes('\n')) {
                return d.split('\n')[0];
            }
            return d;
        },
        denormalizeCharge({ data: charges }) {
            const newCharges = charges.map((charge) => ({
                updated_at: charge.updated_at,
                id: charge.id,
                owner: charge.owner,
                ticket: charge.ticket,
                category: charge.category_name,
                category_id: charge.category,
                duration: charge.duration,
                rate: charge.rate,
                total:
                    charge.duration > 0
                    && charge.rate !== null
                    && charge.rate !== ''
                    && charge.rate > 0
                        ? `$${(charge.duration * charge.rate).toFixed(2)}`
                        : `($${Math.abs(charge.rate).toFixed(2)})`,
                date_of_work: charge.date_of_work,
                description: charge.description,
                short_desc: this.getShortDescription(charge.description),
                brand: charge.brand ? charge.brand : '--',
                brand_id: charge.brand_id,
                invoice_bill_date: charge.invoice_bill_date,
                buttons: ((charge) => {
                    if (this.tab === 'uninvoiced') {
                        return [
                            {
                                type: 'custom',
                                label: 'Edit',
                                icon: 'pencil',
                                url: `/charges/edit/${charge.id}`,
                                classNames: 'btn-primary',
                                onClick: this.handleEdit,
                            },
                            {
                                type: 'custom',
                                label: 'Delete',
                                icon: 'trash',
                                url: `/charges/delete/${charge.id}`,
                                classNames: 'btn-danger',
                                onClick: this.handleDelete,
                            },
                        ];
                    }

                    return [
                        {
                            type: 'custom',
                            label: 'View',
                            url: `/invoice/${charge.invoice_id}`,
                            classNames: 'btn-primary',
                        },
                    ];
                })(charge),
            }));

            return newCharges;
        },
        denormalizeClients({ data: clients }) {
            // console.log(clients);
            const updatedClients = clients.map((client) => ({
                value: client.id,
                label: client.name,
            }));

            updatedClients.unshift({ value: '', label: 'Select option' });
            return updatedClients;
        },
        denormalizeCategories({ data: categories }) {
            const updatedCategories = categories.map((category) => ({
                value: category.id,
                label: category.item_desc,
                map_rate_to: category.map_rate_to,
            }));

            updatedCategories.unshift({ value: '', label: 'Select option' });
            return updatedCategories;
        },
        async deleteCharge(id, cb) {
            await axios.delete(ROUTES.CHARGES_DELETE, { data: { id } });
            this.fetch(ROUTES.CHARGES_LIST, cb);
        },
        async saveCharge(data, cb) {
            try {
                await axios.post(ROUTES.CHARGES_CREATE, data);
                this.fetch(ROUTES.CHARGES_LIST, cb);
            }
            catch (e) {
                if (e.response.status == 422) {
                    const errors = e.response.data.errors;
                    const errorFields = Object.keys(errors);
                    const emsg = [];
                    for (let i = 0, len = errorFields.length; i < len; i += 1) {
                        emsg.push(errors[errorFields[i]].join('\n'));
                    }
                    alert(`${e.response.data.message}\n${emsg.join('\n')}`);
                }
                console.log('the error is', JSON.stringify(e));
                cb(false);
            }
        },
        async fetch(url, cb) {
            const charges = await axios.get(url);
            this.chargesData = this.denormalizeCharge(charges.data);
            for (let i = 0, len = this.headers.length; i < len; i += 1) {
                if (this.headers[i].serviceKey === this.browserState.sort) {
                    this.headers[i].sorted = this.browserState.dir;
                    break;
                }
            }
            this.dataIsLoaded = true;
            this.activePage = charges.data.current_page;
            this.numberPages = charges.data.last_page;
            cb && cb(true);
        },
        async fetchClients(url, cb) {
            const clients = await axios.get(url);
            this.brandsData = this.denormalizeClients(clients);
        },
        async fetchCategories(url, cb) {
            const categories = await axios.get(url);
            this.categoriesData = this.denormalizeCategories(categories.data);
        },
    },
};
</script>

<style scoped>
</style>
