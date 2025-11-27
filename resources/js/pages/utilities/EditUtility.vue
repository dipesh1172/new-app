<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Utilities', url:'/utilities'},
        {name: 'Edit Utility', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Edit a Utility Provider
          </div>
          <div class="card-body">
            <ValidationObserver ref="validationObserver">
              <form
                ref="formObject"
                method="POST"
                :action="`/utilities/${utilityID}/updateUtility`"
                autocomplete="off"
              >
                <input
                  type="hidden"
                  name="_token"
                  :value="csrf_token"
                >

                <div
                  v-if="errors.length > 0"
                  class="row"
                >
                  <div
                    class="alert alert-danger"
                  >
                    <ul>
                      <li
                        v-for="(error, index) in errors"
                        :key="`error-${index}`"
                      >
                        {{ error }}
                      </li>
                    </ul>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-8">

                    <div class="row">
                      <div class="col-md-4">
                        <div class="form-group">
                          <input
                            v-model="utility.multiple_meter_numbers"
                            type="checkbox"
                            name="multiple_meter_numbers"
                            value="1"
                          >
                          <label for="multiple_meter_numbers" class="checkbox-inline">Multiple Meter Number</label>
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-6">
                        <ValidationProvider
                          v-slot="{ errors }"
                          name="name"
                          rules="required|max:64"
                        >
                          <div class="form-group">
                            <label for="name">Utility Name</label>
                            <input
                              v-model="utility.name"
                              type="text"
                              name="name"
                              class="form-control form-control-lg"
                              placeholder="Enter a Utility Name"
                            >
                          </div>
                          <span class="text-danger">{{ errors[0] }}</span>
                        </ValidationProvider>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="ldc_code">LDC Code</label>
                          <input
                            type="text"
                            :value="utility.ldc_code"
                            name="ldc_code"
                            class="form-control form-control-lg"
                            placeholder="Enter a LDC Code"
                          >
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="name_ivr">IVR Name</label>
                          <input
                              type="text"
                              :value="utility.name_ivr"
                              name="name_ivr"
                              class="form-control form-control-lg"
                              placeholder="Enter a IVR name"
                          >
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="address">Address</label>
                          <input
                            type="text"
                            :value="utility.address1"
                            name="address"
                            class="form-control form-control-lg"
                            placeholder="Enter an Address"
                          >
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="city">City</label>
                          <input
                            type="text"
                            :value="utility.city"
                            name="city"
                            class="form-control form-control-lg"
                            placeholder="Enter a City"
                          >
                        </div>
                      </div>
                      <div class="col-md-4">
                        <ValidationProvider
                          v-slot="{ errors }"
                          name="state"
                          rules="required|min:1"
                        >
                          <div class="form-group">
                            <label for="state">State</label>
                            <select
                              id="state"
                              v-model="utility.state_id"
                              name="state"
                              class="form-control form-control-lg"
                            >
                              <option value>
                                Select a State
                              </option>
                              <option
                                v-for="state in states"
                                :key="state.id"
                                :value="state.id"
                                :selected="state.id == utility.state_id"
                              >
                                {{ state.name }}
                              </option>
                            </select>
                            <span
                              v-if="errors.length"
                              class="text-danger"
                            >{{ errors[0] }}</span>
                          </div>
                        </ValidationProvider>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="zip">Zip Code</label>
                          <input
                            type="text"
                            :value="utility.zip"
                            name="zip"
                            class="form-control form-control-lg"
                            placeholder="Enter a Zip"
                          >
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="service_number">Phone</label>
                          <the-mask
                            id="service_number"
                            v-model="utility.customer_service"
                            :mask="['(###) ###-####']"
                            class="form-control form-control-lg"
                            placeholder="Enter a Customer Service Phone"
                            name="service_number"
                          />
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="duns">DUNS</label>
                          <input
                            v-model="utility.duns"
                            type="text"
                            name="duns"
                            class="form-control form-control-lg"
                            placeholder="Enter the DUNS"
                          >
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="disclosure_document">Disclosure Document</label>
                          <input
                            v-model="utility.disclosure_document"
                            type="text"
                            name="disclosure_document"
                            class="form-control form-control-lg"
                            placeholder="Enter a Disclosure Document Name"
                          >
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="discount_program">Discount Program</label>
                          <input
                            v-model="utility.discount_program"
                            type="text"
                            name="discount_program"
                            class="form-control form-control-lg"
                            placeholder="Enter a Discount Program Name"
                          >
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="website">Website</label>
                          <input
                            v-model="utility.website"
                            type="text"
                            name="website"
                            class="form-control form-control-lg"
                            placeholder="Enter a Web Address"
                          >
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="service_zips">Service Zips (Comma Separated)</label>
                          <textarea
                            v-model="utility.service_zips"
                            name="service_zips"
                            class="form-control form-control-lg"
                            style="width: 100%; height: 200px;"
                            placeholder="Enter Service Zips (Comma Separated)"
                          />
                        </div>
                      </div>
                    </div>

                  </div>
                  <div class="col-md-4">
                    <h4>Supported Fuels</h4>
                    <template v-if="!utilitySupportedFuels.length">
                      No results found.
                    </template>
                    <template v-else>
                      <ul class="list-group">
                        <li
                          v-for="(usf, index) in utilitySupportedFuels"
                          :key="index"
                          class="list-group-item"
                        >
                          <em class="fa fa-check fa-2x text-success" />
                          <p class="lead d-inline">
                            {{ usf.utility_type }}
                          </p>
                        </li>
                      </ul>
                    </template>

                    <div
                      v-if="Object.keys(addSupported).length"
                      class="pt-2 row"
                    >
                      <div class="col-md-10">
                        <select
                          id="utility_type"
                          name="utility_type"
                          class="form-control form-control-lg"
                        >
                          <option
                            v-for="(adds, index) in addSupported"
                            :key="index"
                            :value="index"
                          >
                            {{ adds }}
                          </option>
                        </select>
                      </div>
                      <div class="col-md-2">
                        <span
                          id="add_utility_type"
                          class="btn btn-warning"
                          @click="addFuelType"
                        ><i
                          aria-hidden="true"
                          class="fa fa-plus"
                        /> Add</span>
                      </div>
                    </div>

                    <h4 class="mt-4">
                      Brands In Use
                    </h4>
                    <p class="small">
                      These brands have a "brand utilities" entry and may define a custom label etc
                    </p>
                    <template v-if="brandsInUse.length === 0">
                      No Brands in Use
                    </template>
                    <template v-else>
                      <ul class="list-group">
                        <li
                          v-for="(biu, b_i) in brandsInUse"
                          :key="`biu_${b_i}`"
                          class="list-group-item"
                        >
                          <a
                            :href="`/brands/${biu.brand.id}/utilities/${utilityID}/editUtility`"
                            target="_blank"
                          >{{ biu.brand.name }} <span class="fa fa-external-link" /></a>
                        </li>
                      </ul>
                    </template>
                  </div>
                </div>
                <div class="row">
                  <div class="col-12">
                    <hr>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <h4>Supported Fuels Extra Information</h4>
                    <template v-if="!utilitySupportedFuels.length">
                      No results found.
                    </template>
                    <template v-else>
                      <ul class="list-group">
                        <li
                          v-for="(usf, index) in supportedFuels"
                          :key="`fuel-${index}`"
                          class="list-group-item"
                        >
                          <input
                            type="hidden"
                            :name="`utility-${usf.utility_type_id}-id`"
                            :value="usf.id"
                          >
                          <div class="row">
                            <div class="col-12">
                              <i class="fa fa-check fa-2x text-success" />
                              <p class="lead d-inline">
                                {{ usf.utility_type }}
                              </p>
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-12">
                              <label>Monthly Fee</label>
                              <input
                                type="text"
                                :name="`utility_monthly_fee_${usf.utility_type_id}`"
                                :value="usf.utility_monthly_fee"
                                class="form-control form-control-lg"
                              >
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-12">
                              <label>Rate Addendum (cents per kwh)</label>
                              <input
                                type="text"
                                :name="`utility_rate_addendum_${usf.utility_type_id}`"
                                :value="usf.utility_rate_addendum"
                                class="form-control form-control-lg"
                              >
                            </div>
                          </div>
                        </li>
                      </ul>
                    </template>

                    <div
                      v-if="Object.keys(addSupported).length"
                      class="pt-2 row"
                    >
                      <div class="col-md-10">
                        <select
                          id="utility_type"
                          name="utility_type"
                          class="form-control form-control-lg"
                        >
                          <option
                            v-for="(adds, index) in addSupported"
                            :key="`asf-${index}`"
                            :value="index"
                          >
                            {{ adds }}
                          </option>
                        </select>
                      </div>
                      <div class="col-md-2">
                        <span
                          id="add_utility_type"
                          class="btn btn-warning"
                          @click="addFuelType"
                        ><i
                          aria-hidden="true"
                          class="fa fa-plus"
                        /> Add</span>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-12">
                    <hr>
                  </div>
                </div>
                <div class="row">
                  <div class="col-12">
                    <div class="card">
                      <div class="card-header">
                        Current Account Identifiers
                      </div>
                      <div class="card-body p-0">
                        <table class="table table-bordered table-shaded table-hover">
                          <tr>
                            <th>Fuel Type</th>
                            <th>Identifier Type</th>
                            <th>Identifier Description</th>
                            <th>Validation RegEx</th>
                            <th>Bill Location</th>
                          </tr>
                          <template v-if="!utilitySupportedFuels.length">
                            <tr>
                              <td
                                colspan="5"
                                class="text-center"
                              >
                                No Account Validation Regex's were found.
                              </td>
                            </tr>
                          </template>
                          <template v-else>
                            <template v-for="ui in utilitySupportedFuels.filter(ui => ui.account_type)">
                              <tr
                                :id="`${ui.utility_account_identifiers_id}-view`"
                                :key="`${ui.utility_account_identifiers_id}-view`"
                              >
                                <td>
                                  {{ ui.utility_type }}
                                  <button
                                    type="button"
                                    class="btn btn-warning"
                                    @click="clicked(ui.utility_account_identifiers_id)"
                                  >
                                    <i class="fa fa-pencil" />
                                  </button>
                                </td>
                                <td>
                                  {{ ui.account_type }}
                                  <br>

                                  <span
                                    v-if="ui.utility_account_number_type_id == 1"
                                    class="badge badge-info"
                                  >Primary (UAN1)</span>
                                  <span
                                    v-if="ui.utility_account_number_type_id == 2"
                                    class="badge badge-info"
                                  >Secondary (UAN2)</span>
                                  <span
                                    v-if="ui.utility_account_number_type_id == 3"
                                    class="badge badge-info"
                                  >Tertiary (UAN3)</span>
                                </td>
                                <td>
                                  <p v-if="ui.description">
                                    {{ ui.description }}
                                  </p>
                                  <p v-else>
                                    --
                                  </p>
                                </td>
                                <td style="overflow: scroll-x;">
                                  <pre v-if="ui.validation_regex">{{ ui.validation_regex }}</pre>
                                  <pre v-else>--</pre>
                                </td>
                                <td>
                                  <template v-if="ui.bill_location">
                                    <div
                                      v-if="'en' in ui.bill_location && ui.bill_location.en !== null && ui.bill_location.en.trim() !== ''"
                                      class="alert alert-success"
                                    >
                                      <strong>English:</strong>
                                      {{ ui.bill_location['en'] }}
                                    </div>
                                    <div
                                      v-else
                                      class="alert alert-success"
                                    >
                                      <strong>English:</strong> <em>Not Set</em>
                                    </div>
                                    <div
                                      v-if="'sp' in ui.bill_location && ui.bill_location.sp !== null && ui.bill_location.sp.trim() !== ''"
                                      class="alert alert-warning"
                                    >
                                      <strong>Spanish:</strong>
                                      {{ ui.bill_location['sp'] }}
                                    </div>
                                    <div
                                      v-else
                                      class="alert alert-warning"
                                    >
                                      <strong>Spanish:</strong> <em>Not Set</em>
                                    </div>
                                  </template>
                                  <template v-else>
                                    <em>Not Set</em>
                                  </template>
                                </td>
                              </tr>
                              <tr
                                :id="`${ui.utility_account_identifiers_id}-edit`"
                                :key="`${ui.utility_account_identifiers_id}-edit`"
                                class="d-none"
                              >
                                <td>{{ ui.utility_type }}</td>
                                <td>
                                  {{ ui.account_type }}
                                  <br>

                                  <span
                                    v-if="ui.utility_account_number_type_id == 1"
                                    class="badge badge-info"
                                  >Primary (UAN1)</span>
                                  <span
                                    v-if="ui.utility_account_number_type_id == 2"
                                    class="badge badge-info"
                                  >Secondary (UAN2)</span>
                                  <span
                                    v-if="ui.utility_account_number_type_id == 3"
                                    class="badge badge-info"
                                  >Tertiary (UAN3)</span>
                                </td>
                                <td colspan="3">
                                  <form
                                    :id="`${ui.utility_account_identifiers_id}-save`"
                                    method="POST"
                                    :action="`/utilities/update-ident/${ui.utility_account_identifiers_id}`"
                                  >
                                    <input
                                      type="hidden"
                                      name="_token"
                                      :value="csrf_token"
                                    >
                                    <label :for="`${ui.utility_account_identifiers_id}-description`">Description</label>
                                    <input
                                      :id="`${ui.utility_account_identifiers_id}-description`"
                                      type="text"
                                      name="description"
                                      :value="ui.description"
                                      class="form-control form-control-lg"
                                    >
                                    <label
                                      class="pt-2"
                                      :for="`${ui.utility_account_identifiers_id}-regex`"
                                    >Validation Regex</label>
                                    <input
                                      :id="`${ui.utility_account_identifiers_id}-regex`"
                                      type="text"
                                      name="validation_regex"
                                      :value="ui.validation_regex"
                                      class="form-control form-control-lg"
                                    >
                                    <template v-if="ui.bill_location">
                                      <label
                                        class="pt-2"
                                        :for="`${ui.utility_account_identifiers_id}-location-en`"
                                      >Bill Location (English)</label>
                                      <input
                                        :id="`${ui.utility_account_identifiers_id}-location-en`"
                                        type="text"
                                        name="bill_location_en"
                                        :value="ui.bill_location['en']"
                                        class="form-control form-control-lg"
                                      >
                                      <label
                                        class="pt-2"
                                        :for="`${ui.utility_account_identifiers_id}-location-sp`"
                                      >Bill Location (Spanish)</label>
                                      <input
                                        :id="`${ui.utility_account_identifiers_id}-location-sp`"
                                        type="text"
                                        name="bill_location_sp"
                                        :value="ui.bill_location['sp']"
                                        class="form-control form-control-lg"
                                      >
                                    </template>
                                    <template v-else>
                                      <label
                                        class="pt-2"
                                        :for="`${ui.utility_account_identifiers_id}-location-en`"
                                      >Bill Location (English)</label>
                                      <input
                                        :id="`${ui.utility_account_identifiers_id}-location-en`"
                                        type="text"
                                        name="bill_location_en"
                                        class="form-control form-control-lg"
                                      >
                                      <label
                                        class="pt-2"
                                        :for="`${ui.utility_account_identifiers_id}-location-sp`"
                                      >Bill Location (Spanish)</label>
                                      <input
                                        :id="`${ui.utility_account_identifiers_id}-location-sp`"
                                        type="text"
                                        name="bill_location_sp"
                                        class="form-control form-control-lg"
                                      >
                                    </template>
                                    <label class="pt-2">Account Identifier Type</label>
                                    <select
                                      :id="`${ui.utility_account_identifiers_id}-uan`"
                                      name="utility_account_number_type_id"
                                      class="form-control form-control-lg"
                                    >
                                      <option
                                        :value="1"
                                        :selected="ui.utility_account_number_type_id == 1"
                                      >
                                        Primary (UAN1)
                                      </option>
                                      <option
                                        :value="2"
                                        :selected="ui.utility_account_number_type_id == 2"
                                      >
                                        Secondary (UAN2)
                                      </option>
                                      <option
                                        :value="3"
                                        :selected="ui.utility_account_number_type_id == 3"
                                      >
                                        Tertiary (UAN3)
                                      </option>
                                    </select>
                                    <hr>
                                    <button
                                      type="button"
                                      class="btn btn-primary pull-right"
                                      @click="dosave(ui.utility_account_identifiers_id)"
                                    >
                                      <i class="fa fa-save" /> Save
                                    </button>
                                    <button
                                      type="button"
                                      class="btn btn-danger pull-left"
                                      @click="cancelit(ui.utility_account_identifiers_id)"
                                    >
                                      <i class="fa fa-remove" /> Cancel
                                    </button>
                                  </form>
                                </td>
                              </tr>
                            </template>
                          </template>
                        </table>
                        <hr>
                        <div class="p-2">
                          <button
                            type="button"
                            class="btn btn-primary"
                            @click="showAddIdent($event)"
                          >
                            <i class="fa fa-plus" /> Add Identifier
                          </button>
                          <div
                            id="add-ident"
                            class="card d-none"
                          >
                            <div class="card-header">
                              Add Identifier
                            </div>
                            <div class="card-body">
                              <div class="row">
                                <div class="col-md-3">
                                  <div class="form-group">
                                    <label for="supported_type">Supported Type</label>
                                    <select
                                      id="supported_type_id"
                                      name="supported_type_id"
                                      class="form-control form-control-lg"
                                    >
                                      <option value>
                                        Select a Supported Type
                                      </option>
                                      <option
                                        v-for="(utilityType, ut_i) in utilitySupportedFuels"
                                        :key="`ucct-${ut_i}-${utilityType.id}`"
                                        :value="utilityType.id"
                                      >
                                        {{ utilityType.utility_type }}
                                      </option>
                                    </select>
                                  </div>

                                  <div class="form-group">
                                    <label for="utility_account_type_id">Utility Identifier Type</label>
                                    <select
                                      id="utility_account_type_id"
                                      name="utility_account_type_id"
                                      class="form-control form-control-lg"
                                    >
                                      <option value>
                                        Select a Utility Identifier Type
                                      </option>
                                      <option
                                        v-for="accountType in accountTypes"
                                        :key="`accttype-${accountType.id}`"
                                        :value="accountType.id"
                                      >
                                        {{ accountType.account_type }}
                                      </option>
                                    </select>
                                  </div>
                                </div>
                                <div class="col-md-3">
                                  <div class="form-group">
                                    <label for="description">Identifier Description</label>
                                    <i
                                      class="fa fa-question-circle"
                                      aria-hidden="true"
                                      data-toggle="tooltip"
                                      data-placement="top"
                                      title="Example: 10 numeric characters, beginning with '4'"
                                    />
                                    <input
                                      type="text"
                                      name="description"
                                      :value="utility.description"
                                      class="form-control form-control-lg"
                                      placeholder="Enter the Identifier Description"
                                    >
                                  </div>
                                </div>
                                <div class="col-md-3">
                                  <div class="form-group">
                                    <label for="validation_regex">Validation Regex</label>
                                    <i
                                      class="fa fa-question-circle"
                                      aria-hidden="true"
                                      data-toggle="tooltip"
                                      data-placement="top"
                                      title="The code uses this information to validate that the account number is in a proper format."
                                    />
                                    <input
                                      type="text"
                                      name="validation_regex"
                                      :value="utility.validation_regex"
                                      class="form-control form-control-lg"
                                      placeholder="Enter the Validation Regex"
                                    >
                                    <small class="form-text text-muted">
                                      See the cheatsheet at
                                      <a
                                        rel="noopener noreferrer"
                                        href="https://docs.tpvhub.com/utilities/#identifier-cheat-sheet"
                                        target="_blank"
                                      >https://docs.tpvhub.com/utilities/#identifier-cheat-sheet</a>
                                    </small>
                                  </div>
                                </div>
                                <div class="col-md-3">
                                  <div class="form-group">
                                    <label class="pt-2">Bill Location (English)</label>
                                    <input
                                      type="text"
                                      name="bill_location_en"
                                      value
                                      class="form-control form-control-lg"
                                      placeholder="English description of location on bill"
                                    >
                                  </div>
                                  <div class="form-group">
                                    <label class="pt-2">Bill Location (Spanish)</label>
                                    <input
                                      type="text"
                                      name="bill_location_sp"
                                      value
                                      class="form-control form-control-lg"
                                      placeholder="Spanish description of location on bill"
                                    >
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-12">
                    <button
                      type="button"
                      class="btn btn-primary btn-lg pull-right"
                      @click="onSubmit"
                    >
                      <i
                        class="fa fa-floppy-o"
                        aria-hidden="true"
                      />
                      {{ $t('ui.save') }}
                    </button>
                  </div>
                </div>
              </form>
            </ValidationObserver>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import {TheMask} from 'vue-the-mask';
import Breadcrumb from 'components/Breadcrumb';
import { ValidationProvider, ValidationObserver } from 'vee-validate/dist/vee-validate.full.esm';

export default {
    name: 'EditUtility',
    components: {
        TheMask,
        Breadcrumb,
        ValidationProvider,
        ValidationObserver,
    },
    props: {
        errors: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            brandsInUse: [],
            utility: {},
            csrf_token: window.csrf_token,
            utilityID: window.location.pathname.split('/')[2],
            states: [],
            utilitySupportedFuels: [],
            utilityTypes: [],
            addSupported: [],
            accountTypes: [],
        };
    },
    computed: {
        supportedFuels() {
            const out = [];
            for (let i = 0, len = this.utilitySupportedFuels.length; i < len; i += 1) {
                const id = this.utilitySupportedFuels[i].id;
                let haveIt = false;
                for (let n = 0, nlne = out.length; n < nlne; n += 1) {
                    if (out[n].id === id) {
                        haveIt = true;
                    }
                }
                if (!haveIt) {
                    out.push(this.utilitySupportedFuels[i]);
                }
            }
            return out;
        },
    },
    mounted() {
        document.title += ' Edit Utility';
        axios
            .get(`/utilities/${this.utilityID}/editUtility`)
            .then((response) => {
                const res = response.data;
                this.brandsInUse = res.brands.filter((i) => i.brand != null).sort((lhs, rhs) => {
                    if (lhs.brand.name.toLowerCase() === rhs.brand.name.toLowerCase()) {
                        return 0;
                    }
                    if (lhs.brand.name.toLowerCase() < rhs.brand.name.toLowerCase()) {
                        return -1;
                    }
                    return 1;
                });
                this.utility = res.utility;
                this.states = res.states;
                this.utilitySupportedFuels = res.utility_supported_fuels.map((item) => {
                    if (item.bill_location !== null) {
                        item.bill_location = JSON.parse(item.bill_location);
                    }
                    return item;
                });
                this.utilityTypes = res.utility_types;
                this.addSupported = res.add_supported;
                this.accountTypes = res.account_types;
            })
            .catch((e) => console.log(e));
    },
    updated() {
        $('[data-toggle="tooltip"]').tooltip();
    },
    methods: {
        onSubmit() {
            this.$refs.validationObserver.validate().then((success) => {
                if (!success) {
                    return;
                }

                this.$refs.formObject.submit();
            });
        },
        addFuelType() {
            if (confirm('Are you sure you want to add this type?')) {
                const url = `${window.location.protocol}//${window.location.host}${window.location.pathname}`;
                document.location.href = `${url}?add_type=${$('#utility_type').val()}`;
            }
        },
        showAddIdent(e) {
            $(e.target).addClass('d-none');
            $('#add-ident').removeClass('d-none');
        },
        validateRegex(re) {
            if (re != null && re != undefined) {
                try {
                    const x = new RegExp(re);
                    if (x != null && x != undefined) {
                        return true;
                    }
                    console.error('Parsing the regex returned null/undefined', x);
                }
                catch (e) {
                    console.error(e);
                    return false;
                }
            }
            else {
                console.error('The supplied regular expression is null or undefined', re);
            }
            return false;
        },
        dosave(ident) {
            const description = $(`#${ident}-description`).val();
            const regex = $(`#${ident}-regex`).val();
            if (!this.validateRegex(regex)) {
                alert('The regular expression is not valid, please check the console for details.');
                return false;
            }
            const en = $(`#${ident}-location-en`).val();
            const sp = $(`#${ident}-location-sp`).val();
            const uan = $(`#${ident}-uan`).val();
            axios
                .post(`/utilities/update-ident/${ident}`, {
                    description,
                    regex,
                    bill_location: {
                        en,
                        sp,
                    },
                    uan,
                })
                .then(() => {
                    window.location.reload();
                })
                .catch(() => {
                    alert('Could not save updated identifier.');
                });
        },
        clicked(ident) {
            $(`#${ident}-edit`).removeClass('d-none');
            $(`#${ident}-view`).addClass('d-none');
        },
        cancelit(ident) {
            $(`#${ident}-view`).removeClass('d-none');
            $(`#${ident}-edit`).addClass('d-none');
        },
    },
};
</script>
