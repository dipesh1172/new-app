<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: 'Enrollment Files', active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <template v-if="bef.live_enroll">
        This is a Live Enrollment Brand. Configuration has been hidden.
      </template>
      <template v-else>
        <div>
          <form
            method="post"
            :action="`/brands/${brand.id}/createEnrollment`"
            autocomplete="off"
          >
            <input
              type="hidden"
              name="_token"
              :value="csrfToken"
            >
            <div
              class="card"
            >
              <div class="card-header">
                Manual Run
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-3">
                    <input
                      type="text"
                      name="date"
                      class="datepicker form-control"
                      placeholder="Date (YYYY-MM-DD)"
                      autocomplete="off"
                    >
                  </div>
                  <div class="col-3 text-center mt-2">
                    <input
                      id="inlineCheckbox1"
                      class="form-check-input"
                      type="checkbox"
                      name="noalert"
                      value="1"
                    >
                    <label
                      class="form-check-label"
                      for="inlineCheckbox1"
                    >Suppress alerts/delivery</label>
                  </div>
                  <div class="col-1">
                    <button
                      type="submit"
                      class="btn btn-warning"
                      name="Run Enrollment File"
                    >
                      <i
                        class="fa fa-floppy-o"
                        aria-hidden="true"
                      />
                      Submit
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </form>

          <br>

          <div class="row">
            <div class="col-6">
              <table class="table table-bordered table-striped">
                <thead class="thead-dark">
                  <tr>
                    <th scope="col">
                      Start Date
                    </th>
                    <th scope="col">
                      End Date
                    </th>
                    <th
                      scope="col"
                      class="text-center"
                    >
                      Products
                    </th>
                    <th
                      scope="col"
                      class="text-center"
                    />
                  </tr>
                </thead>
                <template v-if="lefs.data.length">
                  <tr
                    v-for="(lef, index) in lefs.data"
                    :key="index"
                  >
                    <td>{{ lef.start_date }}</td>
                    <td>{{ lef.end_date }}</td>
                    <td class="text-center">
                      {{ lef.products }}
                    </td>
                    <td class="text-center">
                      <a
                        target="_blank"
                        class="btn btn-success"
                        :href="`${awsCloudFront}/${lef.filename}`"
                      ><i
                        class="fa fa-download"
                        aria-hidden="true"
                      /> 
                        Download
                      </a>
                    </td>
                  </tr>
                </template>
                
                <template>
                  <tr>
                    <td
                      colspan="4"
                      class="text-center"
                    >
                      No enrollment files were found.
                    </td>
                  </tr>
                </template>
              </table>

              <pagination
                v-if="true"
                :active-page="lefs.current_page"
                :number-pages="lefs.last_page"
                @onSelectPage="selectPage"
              />
            </div>
            <div class="col-6">
              <h4>Configuration</h4>

              <hr>

              <template v-if="bef">
                <div v-html="configText()" />
                <br>

                <form
                  method="post"
                  :action="`/brands/${brand.id}/updateEnrollmentFile`"
                  autocomplete="off"
                >
                  <input
                    type="hidden"
                    name="_token"
                    :value="csrfToken"
                  >
                  <div
                    v-if="msgError.config"
                    class="alert alert-danger"
                    role="alert"
                  >
                    Error on JSON format. Visit <a href="https://www.json.org/">https://www.json.org/</a> for more info about it.  
                  </div>
                  <textarea
                    v-model="configTextArea"
                    name="delivery_data"
                    style="width: 100%; height: 200px;"
                    @blur="checkFormat($event, 'config')"
                  />

                  <br><br>

                  <h2>Enrollment File</h2>
                  <a
                    href="https://docs.tpvhub.com/enrollment"
                    target="_blank"
                  >https://docs.tpvhub.com/enrollment</a><br><br>

                  <div
                    v-if="msgError.enrroll"
                    class="alert alert-danger"
                    role="alert"
                  >
                    Error on JSON format. Visit <a href="https://www.json.org/">https://www.json.org/</a> for more info about it.  
                  </div>
                  <textarea
                    v-model="enrollmentTextArea"
                    name="report_fields"
                    style="width: 100%; height: 300px;"
                    @blur="checkFormat($event, 'enrroll')"
                  />

                  <br><hr><br>

                  <p align="right">
                    <button
                      type="submit"
                      name="submit"
                      class="btn btn-danger"
                    >
                      <i
                        class="fa fa-refresh"
                        aria-hidden="true"
                      /> 
                      Update Enrollment File
                    </button>
                  </p>
                </form>  
              </template>    
              <template v-else>
                No enrollment file configuration found.
              </template>

              <br>

              <h4>Log (last 10)</h4>
  
              <div class="table-responsive">
                <table class="table table-bordered table-striped">
                  <tr
                    v-for="(log, index) in logs"
                    :key="index"
                  >
                    <td>
                      {{ log }}
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </layout>
</template>
<script>
import Pagination from 'components/Pagination';
import Layout from './Layout';

export default {
    name: 'Enrollments',
    components: {
        Pagination,
        Layout,
    },
    props: {
        awsCloudFront: {
            type: String,
            required: true,
            default: '',
        },
        brand: {
            type: Object,
            required: true,
            default: () => {},
        },
        bef: {
            type: Object,
            default: () => {},
        },
        lefs: {
            type: Object,
            default: () => {},
        },
        logs: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            csrfToken: window.csrf_token,
            msgError: {config: false, enrroll: false},
            enrollmentTextArea: this.stripslashes(this.validateJSON(this.bef.report_fields)),
            configTextArea: JSON.stringify(this.bef.delivery_data, null, 2),
        };
    },
    // computed: {
    //     configTextArea: {
    //         get() {
    //             return JSON.stringify(this.bef.delivery_data, null, 2);
    //         },
    //         set(val) {
    //             try {
    //                 this.bef.delivery_data = JSON.parse(val);
    //             }
    //             catch (e) {
    //                 console.log(e);
    //             }
    //         },
    //     },
    //     enrollmentTextArea: {
    //         get() {
    //             return this.stripslashes(JSON.stringify(JSON.parse(this.bef.report_fields), null, 2));
    //         },
    //         set(val) {
    //             try {
    //                 this.bef.report_fields = JSON.parse(val);
    //             }
    //             catch (e) {
    //                 console.log(e);
    //             }
    //         },
    //     },
    // },
    methods: {
        configText() {
            let text = '';
            text = `File Format: ${this.bef.format}<br />
                        Last Automated Run: ${this.bef.last_run}<br />
                        Next Automated Run: ${this.bef.next_run}<br />

                        Delivery:<br />`;
            if (this.bef.delivery_data) {
                text += `&nbsp;&nbsp;&nbsp;&nbsp;Run Time: ${this.bef.delivery_data.run_time}<br />
                            &nbsp;&nbsp;&nbsp;&nbsp;Method: ${this.bef.delivery_data.delivery_method}<br />`;
                switch (this.bef.delivery_data.delivery_method) {
                    case 'email':
                        if (this.bef && this.bef.delivery_data && this.bef.delivery_data.email_address) {
                            if (Array.isArray(this.bef.delivery_data.email_address)) {
                                this.bef.delivery_data.email_address.forEach((email) => {
                                    text += `&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- ${email}<br />`;  
                                });
                            }
                            else { text += `&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- ${this.bef.delivery_data.email_address}<br />`; }
                        }
                        break;
                    case 'sftp':
                    case 'ftp':
                        text += `Hostname: ${this.bef.delivery_data.hostname}<br />
                                    Username: ${this.bef.delivery_data.username}<br />
                                    Password: ********<br />
                                    Port: ${this.bef.delivery_data.port}<br />
                                    Path: ${this.bef.delivery_data.root}<br />`;

                    default:
                        break;
                }
            }
            else {
                text += '&nbsp;&nbsp;&nbsp;&nbsp;No email/ftp/sftp configuration.<br>';
            }
            return text;
        },
        stripslashes(str) {
            return (`${str}`)
                .replace(/\\(.?)/g, (s, n1) => {
                    switch (n1) {
                        case '\\':
                            return '\\';
                        case '0':
                            return '\u0000';
                        case '':
                            return '';
                        default:
                            return n1;
                    }
                });
        },
        selectPage(page) {
            window.location.href = `/brands/${this.brand.id}/enrollments?${page ? `page=${page}` : ''}`;
        },
        validateJSON(r) {
            try {
                r = JSON.stringify(JSON.parse(r || '{}'), null, 2);
            }
            catch (e) {
                this.$once('hook:created', () => {
                    this.msgError.enrroll = true;
                });
                console.log(e);
            }

            return r;
            
        },
        checkFormat(e, type) {
            try {
                JSON.parse(e.target.value);                               
                this.msgError[type] = false;
            }
            catch (error) {
                this.msgError[type] = true;
            }
        },
    },
};
</script>