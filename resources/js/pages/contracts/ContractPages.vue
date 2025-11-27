<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Contracts', url: '/contracts'},
        {name: 'Edit', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div
            v-if="errors"
          >
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
          </div>
          <div class="card-header">
            <div class="row">
              <div class="col-md-9">
                <i class="fa fa-th-large" /> Edit
              </div>
              <div class="col-md-3 text-right" />
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-9">
                <form
                  method="post"
                  :action="`/contracts/` + contractConfig.id + `/page/update`"
                >
                  <input
                    type="hidden"
                    name="_token"
                    :value="csrf_token"
                  >

                  <div class="row">
                    <div class="col-md-6">
                      English Intro: <textarea
                        name="english_intro"
                        class="form-control"
                        placeholder="Summary Page Intro/Title (english)"
                      >{{ pageIntroEnglish }}</textarea>
                    </div>
                    <div class="col-md-6">
                      Spanish Intro: <textarea
                        name="spanish_intro"
                        class="form-control"
                        placeholder="Summary Page Intro/Title (spanish)"
                      >{{ pageIntroSpanish }}</textarea>
                    </div>
                  </div>

                  <br><br>

                  <div
                    v-if="list.length > 0"
                    class="table-responsive"
                  >
                    <table class="table table-bordered table-striped">
                      <thead class="thead-dark">
                        <tr>
                          <th>Label</th>
                          <th>Body</th>
                          <th width="30" />
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="item in list"
                          :key="item.name"
                        >
                          <td>
                            English: <input
                              type="text"
                              name="english_label[]"
                              :value="item.english_label"
                              placeholder="Label (English)"
                              class="form-control"
                              required
                            ><br>
                            Spanish: <input
                              type="text"
                              name="spanish_label[]"
                              :value="item.spanish_label"
                              placeholder="Label (Spanish)"
                              class="form-control"
                              required
                            ><br>
                          </td>
                          <td>
                            English: <textarea
                              name="english_body[]"
                              :value="item.english_body"
                              placeholder="Body (English)"
                              class="form-control"
                              required
                            /><br>
                            Spanish: <textarea
                              name="spanish_body[]"
                              :value="item.spanish_body"
                              placeholder="Body (Spanish)"
                              class="form-control"
                              required
                            /><br>
                          </td>
                          <td>
                            <span
                              class="btn btn-sm btn-danger"
                              @click="removeFromList(item.id)"
                            >
                              <i
                                class="fa fa-trash-o"
                                aria-hidden="true"
                              />
                            </span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div v-else>
                    No rows are added.<br><br>
                  </div>
                  <span
                    class="btn btn-sm btn-success"
                    @click="addToList()"
                  >
                    <i
                      class="fa fa-plus"
                      aria-hidden="true"
                    /> Add Row
                  </span>

                  <br><hr><br>

                  <p align="right">
                    <button class="btn btn-lg btn-primary">
                      <i
                        class="fa fa-floppy-o"
                        aria-hidden="true"
                      /> Submit
                    </button>
                  </p>
                </form>
              </div>
              <div class="col-md-3">
                <div class="card">
                  <div class="card-header">
                    <div class="row">
                      <div class="col-md-12">
                        <a
                          :href="`/contracts/` + contractConfig.id + `/editContract`"
                          class="btn btn-sm btn-primary"
                        >
                          <i
                            class="fa fa-pencil"
                            aria-hidden="true"
                          /> Edit Contract
                        </a>
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                    <b>Brand:</b> {{ contractConfig.name }}<br>
                    <b>State:</b> {{ contractConfig.state_abbrev }}<br>
                    <b>Channel:</b> {{ contractConfig.channel }}<br>
                    <b>Market:</b> {{ contractConfig.market }}<br>
                    <b>Commodities:</b> {{ contractConfig.commodities }}<br>
                    <b>Rate Type:</b> {{ contractConfig.rate_type ? contractConfig.rate_type : 'any' }}<br>
                    <!-- <b>T&C's:</b> {{ contractConfig.terms_and_conditions_name ? contractConfig.terms_and_conditions_name : '--' }}<br /> -->
                  </div>
                </div>

                <div class="card">
                  <div class="card-header">
                    Variables
                  </div>
                  <div class="card-body">
                    client.name<br>
                    user.name<br>
                    date<br>
                    account.bill_name<br>
                    event.confirmation_code<br>
                    client.service_phone<br>
                    commodity<br>
                    product.amount<br>
                    product.intro_amount<br>
                    product.cancellation_fee<br>
                    product.intro_cancellation_fee<br>
                    product.term<br>
                    product.intro_term<br>
                    product.service_fee<br>
                    product.daily_fee<br>
                    product.monthly_fee<br>
                    product.program_code<br>
                    product.uom<br>
                    product.currency<br>
                    utility.name<br>
                    utility.customer_service<br>
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
    name: 'ContractPages',
    components: {
        Breadcrumb,
    },
    props: {
        contractConfig: {
            type: Object,
        },
        contractConfigPages: {
            type: Array,
        },
        errors: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            list: this.contractConfigPages,
            dragging: false,
            addContractUrl: `/contracts/${this.contractConfig.id}/uploadPdf`,
        };
    },
    computed: {
        pageIntroEnglish() {
            if (this.contractConfig.page_intro && this.contractConfig.page_intro.english) {
                return this.contractConfig.page_intro.english;
            }

            return '';
        },
        pageIntroSpanish() {
            if (this.contractConfig.page_intro && this.contractConfig.page_intro.spanish) {
                return this.contractConfig.page_intro.spanish;
            }

            return '';
        },
        csrf_token() {
            return csrf_token;
        },
    },
    mounted() {
        console.log(this.contractConfigPages);
    },
    methods: {
        removeFromList(num) {
            console.log('Removing ', num);
            const context = this;
            context.list.forEach((element) => {
                if (element.id === num) {
                    context.list.splice(context.list.indexOf(element), 1);
                }
            });
        },
        addToList() {
            let highest = 0;
            this.list.forEach((element) => {
                if (element.id > highest) {
                    highest = element.id;
                }
            });

            console.log('Adding ', (highest + 1));

            this.list.push(
                {
                    id: (highest + 1),
                    english_label: '',
                    spanish_label: '',
                    english_body: '',
                    spanish_body: '',
                },
            );
        },
    },
};
</script>

<style lang="scss" scoped>

</style>
