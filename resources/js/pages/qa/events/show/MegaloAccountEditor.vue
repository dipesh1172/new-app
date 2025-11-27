<template>
  <div class="container">
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label
            :for="`${qid}-${label_html_safe}-billing-name`"
          >
            <span v-if="product.market_id == 1">Billing Name</span><span v-else>Company Name</span></label>
          <p
            v-if="errors.billingName !== null"
            class="alert alert-danger"
          >
            {{ errors.billingName }}
          </p>
          <input
            v-if="!leadLocked"
            :id="`${qid}-${label_html_safe}-billing-name`"
            v-model.trim="billingName"
            class="form-control"
            type="text"
          >
          <p
            v-else
            class="lead"
          >
            {{ billingName }}
          </p>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label
            :for="`${qid}-${label_html_safe}-auth-name`"
          >
            Authorized Name</label>
          <p
            v-if="errors.authName !== null"
            class="alert alert-danger"
          >
            {{ errors.authName }}
          </p>
          <input
            v-if="!leadLocked"
            :id="`${qid}-${label_html_safe}-auth-name`"
            v-model.trim="authName"
            class="form-control"
            type="text"
          >
          <p
            v-else
            class="lead"
          >
            {{ authName }}
          </p>
        </div>
      </div>
      <div class="col-md-6 form-group">
        <label>Phone Number</label>
        <p
          v-if="errors.phone !== null"
          class="alert alert-danger"
        >
          {{ errors.phone }}
        </p>
        <input
          v-model="phone"
          type="text"
          class="form-control"
        >
      </div>
      <div class="col-md-6 form-group">
        <label>Email Address</label>
        <p
          v-if="errors.email !== null"
          class="alert alert-danger"
        >
          {{ errors.email }}
        </p>
        <input
          v-model="email"
          type="text"
          class="form-control"
        >
      </div>
    </div>
    <div class="row">
      <div
        v-if="!leadLocked"
        class="col-md-6"
      >
        <div

          class="form-group"
        >
          <label :for="`inputAddress_1_${index}`">Service Address</label>
          <p
            v-if="errors.serviceAddress !== null"
            class="alert alert-danger"
          >
            {{ errors.serviceAddress }}
          </p>
          <input
            :id="`inputAddress_1_${index}`"
            v-model.trim="serviceAddress.line_1"
            type="text"
            class="form-control"
            placeholder="1234 Main St"
          >
        </div>
        <div class="form-group">
          <label :for="`inputAddress2_1_${index}`">Address 2</label>
          <input
            :id="`inputAddress2_1_${index}`"
            v-model.trim="serviceAddress.line_2"
            type="text"
            class="form-control"
            placeholder="Apartment, studio, or floor"
          >
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label :for="`inputZip_1_${index}`">Zip</label>
            <div class="input-group mb-3">
              <input
                :id="`inputZip_1_${index}`"
                v-model.trim="serviceAddress.zip"
                type="text"
                class="form-control"
              >
              <div class="input-group-append">
                <button
                  class="btn btn-info"
                  type="button"
                  @click="lookupA"
                >
                  <i class="fa fa-search" />
                </button>
              </div>
            </div>
          </div>
          <div class="form-group col-md-4">
            <label :for="`inputCity_1_${index}`">City</label>
            <input
              :id="`inputCity_1_${index}`"
              v-model.trim="serviceAddress.city"
              type="text"
              class="form-control"
            >
          </div>
          <div class="form-group col-md-4">
            <label :for="`inputState_1_${index}`">State</label>
            <w-states
              :id="`inputState_1_${index}`"
              v-model="serviceAddress.state_province"
            />
          </div>
        </div>
      </div>
      <div
        v-else
        class="col-md-6"
      >
        <h2>Service Address</h2>
        <hr>
        <p class="lead">
          {{ serviceAddress.line_1 }} <br>
          {{ serviceAddress.line_2 }} <br>
          {{ serviceAddress.city }}
          {{ serviceAddress.zip }}
          {{ serviceAddress.state }}
        </p>
      </div>
      <div
        class="col-md-6"
        style="position:relative;"
      >
        <div style="position:relative">
          <div
            v-if="billAddressIsSame"
            class="rounded w-overlay"
          />
          <div>
            <div class="form-group">
              <label :for="`inputAddress_2_${index}`">Billing Address</label>
              <p
                v-if="errors.billingAddress !== null"
                class="alert alert-danger"
              >
                {{ errors.billingAddress }}
              </p>
              <input
                :id="`inputAddress_2_${index}`"
                v-model.trim="billingAddress.line_1"
                type="text"
                class="form-control"
                placeholder="1234 Main St"
              >
            </div>
            <div class="form-group">
              <label :for="`inputAddress2_2_${index}`">Address 2</label>
              <input
                :id="`inputAddress2_2_${index}`"
                v-model.trim="billingAddress.line_2"
                type="text"
                class="form-control"
                placeholder="Apartment, studio, or floor"
              >
            </div>
            <div class="form-row">
              <div class="form-group col-md-4 mb-0">
                <label :for="`inputZip_2_${index}`">Zip</label>
                <div class="input-group mb-3">
                  <input
                    :id="`inputZip_2_${index}`"
                    v-model.trim="billingAddress.zip"
                    type="text"
                    class="form-control"
                  >
                  <div class="input-group-append">
                    <button
                      class="btn btn-info"
                      type="button"
                      @click="lookupB"
                    >
                      <i class="fa fa-search" />
                    </button>
                  </div>
                </div>
              </div>
              <div class="form-group col-md-4 mb-0">
                <label :for="`inputCity_2_${index}`">City</label>
                <input
                  :id="`inputCity_2_${index}`"
                  v-model.trim="billingAddress.city"
                  type="text"
                  class="form-control"
                >
              </div>
              <div class="form-group col-md-4 mb-0">
                <label :for="`inputState_2_${index}`">State</label>
                <w-states
                  :id="`inputState_2_${index}`"
                  v-model="billingAddress.state_province"
                />
              </div>
            </div>
          </div>
        </div>
        <div class="form-group">
          <div class="form-check">
            <input
              id="gridCheck"
              ref="billSame"
              class="form-check-input ml-0"
              type="checkbox"
              :checked="billAddressIsSame"
              @click="toggleBillSame"
            >
            <label
              class="form-check-label"
              for="gridCheck"
            >
              The Billing Address is the same as the Service Address
            </label>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-12">
        <hr class="mt-0 mb-1">
        <p class="lead mb-0">
          Identifiers
        </p>
      </div>
      <div
        v-for="(ident, i) in identifiers"
        :key="ident.id"
        class="col-md-6"
      >
        <div class="form-group mb-1">
          <label :for="`inputIdentifier-${ident.id}`">{{ ident.utility_account_type.account_type }}</label>
          <p
            v-if="errors.identifiers[i] !== null"
            class="alert alert-danger"
          >
            {{ errors.identifiers[i] }}
          </p>
          <div class="input-group">
            <input
              :id="`inputIdentifier-${ident.id}`"
              v-model.trim="ident.identifier"
              type="text"
              class="form-control"
            >
            <div class="input-group-append">
              <i
                v-if="isIdentifierValid(ident)"
                class="ml-1 fa fa-thumbs-up text-success fa-2x input-group-text"
              />
              <i
                v-else
                class="ml-1 fa fa-thumbs-down text-danger fa-2x input-group-text"
              />
            </div>
          </div>
          <p class="text-muted mb-1">
            {{ getIdentDescription(ident) }}
          </p>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-12">
        <hr class="mt-1 mb-1">
        <p class="lead mb-1">
          QA Notes
        </p>
      </div>
      <div class="col-12">
        <textarea
          v-model="notes"
          class="form-control"
        />
        <p class="text-muted mb-1">
          Describe the reason for updating the account information.
        </p>
      </div>
    </div>
    <div class="row">
      <div
        v-if="!checking"
        class="col-md-12"
      >
        <hr class="mt-1 mb-1">
        <button
          type="button"
          class="btn btn-secondary"
          @click="$emit('cancel')"
        >
          <span class="fa fa-close" /> {{ $t('ui.cancel' ) }}
        </button>
        
        <button
          type="button"
          class="btn btn-primary pull-right"
          @click="clickedButton"
        >
          <span class="fa fa-save" /> {{ $t('ui.save' ) }}
        </button>

        <div
          v-show="event.products.length > 1"
          class="form-check pull-right mt-2 mb-0 mr-2"
        >
          <input
            id="syncChanges"
            v-model="syncChanges"
            class="form-check-input"
            type="checkbox"
          >
          <label
            class="form-check-label pl-0"
            for="syncChanges"
            title="If enabled will also update contact information on all products associated with this event."
          >Synchronize Changes?</label>
        </div>
      </div>
      <button
        v-else
        type="button"
        class="btn btn-primary pull-right disabled"
        disabled
      >
        <span class="fa fa-save" /> <span class="fa fa-spinner fa-spin" />
      </button>
    </div>
  </div>
</template>

<script>
import StateSelect from './StateSelect.vue';

export default {
    name: 'MegaloAccountEditor',
    components: {
        'w-states': StateSelect,
    },
    props: {
        product: {
            type: Object,
            default() {
                return {};
            },
        },
        event: {
            type: Object,
            default() {
                return {};
            },
        },
        qid: {
            type: String,
            default: '',
        },
        resolution: {
            type: Object,
            default() {
                return {};
            },
        },
        index: {
            type: Number,
            default: 0,
        },
        scope: {
            validator(v) {
                return v instanceof Object || v instanceof Array;
            },
            default() {
                return undefined;
            },
        },
    },
    data() {
        return {
            rid: '',
            checking: false,
            errors: {
                billingName: null,
                authName: null,
                serviceAddress: null,
                billingAddress: null,
                phone: null,
                email: null,
                identifiers: [],
            },
            billingName: '',
            authName: '',
            serviceAddress: {
                line_1: '',
                line_2: '',
                zip: '',
                city: '',
                state_province: '',
            },
            billingAddress: {
                line_1: '',
                line_2: '',
                zip: '',
                city: '',
                state_province: '',
            },
            phone: '',
            email: '',
            identifiers: [],
            billAddressIsSame: false,
            leadLocked: false,
            label_html_safe: 'mae-input',
            label: 'mae-input',
            hasAction: false,
            syncChanges: false,
            notes: '',
        };
    },
    computed: {},
    watch: {
        billAddressIsSame(v) {
            if (v) {
                this.billingAddress = JSON.parse(JSON.stringify(this.serviceAddress));
                if ('id' in this.billingAddress) {
                    this.billingAddress.id = null;
                }
            }
        },
    },
    mounted() {
        this.loadContent();
    },
    methods: {
        loadContent() {
            this.$set(this, 'serviceAddress', {
                line_1: '',
                line_2: '',
                zip: '',
                city: '',
                state_province: '',
            });
            this.$set(this, 'billingAddress', {
                line_1: '',
                line_2: '',
                zip: '',
                city: '',
                state_province: '',
            });
            for (let i = 0, len = this.product.addresses.length; i < len; i += 1) {
                if (this.product.addresses[i].id_type === 'e_p:service') {
                    this.$set(this, 'serviceAddress', this.product.addresses[i].address);
                }
            
                if (this.product.addresses[i].id_type === 'e_p:billing') {
                    this.$set(this, 'billingAddress', this.product.addresses[i].address);
                }
            }

            if (this.product.addresses.length == 1) {
                this.billAddressIsSame = true;
            }
            else {
                this.billAddressIsSame = this.areAddressesEqual(this.serviceAddress, this.billingAddress);
            }
            if (this.product.market_id == 1) {
                this.billingName = `${this.product.bill_first_name} ${this.product.bill_middle_name !== null ? this.product.bill_middle_name : ''} ${this.product.bill_last_name}`.replace('  ', ' ');
            }
            else {
                this.billingName = this.product.company_name;
            }
            this.authName = `${this.product.auth_first_name} ${this.product.auth_middle_name !== null ? this.product.auth_middle_name : ''} ${this.product.auth_last_name}`.replace('  ', ' ');
            this.identifiers = JSON.parse(JSON.stringify(this.product.identifiers));
            this.phone = this.event.phone !== null ? this.event.phone.phone_number.phone_number : '';
            this.email = this.event.email !== null ? this.event.email.email_address.email_address : '';
            this.notes = '';

            this.resetErrors();

        },
        areAddressesEqual(addressA, addressB) {
            if (addressB === null && addressA !== null) {
                return true;
            }
            const jaddressA = JSON.stringify(addressA);
            const jaddressB = JSON.stringify(addressB);
            console.log(`addressA is ${jaddressA} and B is ${jaddressB}`);
            return jaddressA === jaddressB;
        },
        toggleBillSame() {
            this.billAddressIsSame = !this.billAddressIsSame;
        },
        resetErrors() {
            this.errors = {
                billingName: null,
                authName: null,
                serviceAddress: null,
                billingAddress: null,
                phone: null,
                email: null,
                identifiers: [],
            };
            for (let i = 0, len = this.identifiers.length; i < len; i += 1) {
                this.errors.identifiers.push(null);
            }
        },
        doValidation() {
            this.resetErrors();
            return Promise.all([
                this.validateBillingName(),
                this.validateAuthName(),
                this.validateEmail(),
                this.validatePhone(),
                this.validateAddress(true, this.serviceAddress),
                this.validateAddress(false, this.billingAddress),
                this.validateIdents(),
            ]);
        },
        clickedButton() {
            this.checking = true;
            this.doValidation()
                .then(() => {
                    if (this.syncChanges) {
                        if (!confirm('This will update everything to match what you entered except identifiers (they are only updated on the changed product) across all products for this event, continue?')) {
                            return;
                        }
                    } 
                    const outData = {
                        serviceAddress: this.serviceAddress,
                        billingAddress: null,
                        phone: this.phone,
                        email: this.email,
                        billName: this.billingName,
                        authName: this.authName,
                        identifiers: this.identifiers.map((item) => ({
                            id: item.id,
                            identifier: item.identifier,
                        })),
                        ep_id: this.product.id,
                        event_id: this.event.id,
                        sync: this.syncChanges,
                        market: this.product.market_id,
                        notes: this.notes,
                    };

                    if (!this.billAddressIsSame) {
                        outData.billingAddress = this.billingAddress;
                    }

                    return axios.post('/events/api/qa_update_event', outData)
                        .then((res) => {
                            if (res.data.error !== false) {
                                alert(res.data.error);
                                return false;
                            }
                            alert('Event updated, reloading with updated information');
                            window.location.reload();
                        })
                        .catch((e) => {
                            throw new Error(e);
                        });
                })
                .catch((e) => {
                    if (e !== undefined) {
                        alert(e);
                    }
                }).finally(() => {
                    this.checking = false;
                    return Promise.resolve();
                });
        },
        validatePhone() {
            if (this.phone.length == 12 && this.phone[0] == '+') {
                return Promise.resolve();
            }
            this.errors.phone = 'Phone number must be in E.194 format: +12223334567';
            return Promise.reject();
        },
        validateEmail() {
            if (this.email == '') {
                return Promise.resolve();
            }
            const hasAt = this.email.includes('@');
            if (hasAt && this.email.length <= 255) {
                const parts = this.email.split('@');
                if (parts.length == 2 && parts[1].includes('.')) {
                    
                    return Promise.resolve();
                }
            }
            this.errors.email = 'The specified email is not valid';
            return Promise.reject();
        },
        validateName(t0, t1, n) {
            const matcher = new RegExp(/^\w+(\s\w)?\s\w+$/g); // start of line - word chars - optional single space and word char - single space - word chars - end of line

            if (matcher.test(n)) {
                return Promise.resolve();
            }

            this.errors[t0] = `The ${t1} must match the format {firstname} {middle initial} {lastname} where the middle initial is optional`;
            return Promise.reject();
        },
        validateBillingName() {
            if (this.product.market_id == 1) {
                return this.validateName('billingName', 'billing name', this.billingName);
            } 
            if (this.billingName.length <= 2) {
                this.errors.billingName = 'Invalid Company Name';
            }
            return this.billingName.length > 2 ? Promise.resolve() : Promise.reject();
            
        },
        validateAuthName() {
            this.validateName('authName', 'authorized name', this.authName);
        },
        lookupA() {
            this.lookupZipCode(this.serviceAddress.zip)
                .then((r) => {
                    if (r.data.error !== null) {
                        this.errors.serviceAddress = r.data.error;
                        return Promise.reject();
                    }
                    if (r.data.cities.length === 1) {
                        this.serviceAddress.city = r.data.cities[0].city;
                        this.serviceAddress.state_province = r.data.cities[0].state;
                        return Promise.resolve();
                    }
                    this.errors.serviceAddress = 'Multiple cities found, please enter city manually';
                    return Promise.reject();
                }).catch((e) => {
                    this.errors.serviceAddress = 'Unknown error looking up zip code';
                    return Promise.reject(e);
                });
        },
        lookupB() {
            this.lookupZipCode(this.billingAddress.zip)
                .then((r) => {
                    if (r.data.error !== null) {
                        this.errors.billingAddress = r.data.error;
                        return Promise.reject();
                    }
                    if (r.data.cities.length === 1) {
                        this.billingAddress.city = r.data.cities[0].city;
                        this.billingAddress.state_province = r.data.cities[0].state;
                        return Promise.resolve();
                    }
                    this.errors.billingAddress = 'Multiple cities found, please enter city manually';
                    return Promise.reject();
                }).catch((e) => {
                    this.errors.serviceAddress = 'Unknown error looking up zip code';
                    return Promise.reject(e);
                });
        },
        lookupZipCode(zip) {
            return axios.post('/events/api/zip_code_lookup', {
                zip,
            });
        },
        validateZipCode(zip, isService) {
            return new Promise((resolve, reject) => {
                this.lookupZipCode(zip).then((res) => {
                    if (res.data.error !== null) {
                        reject(res.data.error);
                        return;
                    }
                    if (isService && res.data.cities[0].state !== this.product.addresses[0].address.state_province) {
                        reject('The provided zip code is located outside the service state.'); // eslint-disable-line
                        return;
                    }
                    resolve();
                }).catch((e) => {
                    reject(e);
                });
            });

        },
        validateAddress(isService, addr) {
            const setError = (msg) => {
                if (isService) {
                    this.errors.serviceAddress = msg;
                }
                else {
                    this.errors.billingAddress = msg;
                }
                return Promise.reject();
            };
            const line1 = addr.line_1 !== null ? addr.line_1.trim() : '';
            if (line1 === '') {
                setError('The address cannot be blank');
            }
            const zipLen = addr.zip.length;
            if (zipLen !== 5 && zipLen !== 6) {
                return setError('The postal code contains an invalid number of characters.');
            }
            const city = addr.city !== null ? addr.city.trim() : '';
            if (city === '') {
                return setError('You must select a city.');
            }

            return this.validateZipCode(addr.zip, isService).catch((e) => setError(e));
        },
        validateIdents() {
            for (let i = 0, len = this.identifiers.length; i < len; i += 1) {
                if (!this.isIdentifierValid(this.identifiers[i])) {
                    this.errors.identifiers[i] = 'The entered value is invalid.';
                    return Promise.reject();
                }
            }
            return Promise.resolve();
        },
        getIdentDescription(ident) {
            const itype = ident.utility_account_type_id;
            let regexp = null;
            for (let i = 0, len = this.product.utility_supported_fuel.identifiers.length; i < len; i += 1) {
                if (this.product.utility_supported_fuel.identifiers[i].utility_account_type_id === itype) {
                    regexp = this.product.utility_supported_fuel.identifiers[i].description;
                    break;
                }
            }
            if (regexp != null) {
                return regexp;
            }
            return '';
        },
        isIdentifierValid(ident) {
            const itype = ident.utility_account_type_id;
            let regexp = null;
            for (let i = 0, len = this.product.utility_supported_fuel.identifiers.length; i < len; i += 1) {
                if (this.product.utility_supported_fuel.identifiers[i].utility_account_type_id === itype) {
                    regexp = this.product.utility_supported_fuel.identifiers[i].validation_regex;
                    break;
                }
            }
            if (regexp !== null) {
                const regex = new RegExp(regexp);
                return regex.test(ident.identifier);
            }

            console.log(`Could not locate regular expression for utility account type id ${itype}`);

            return false;
        },
    },
};
</script>

