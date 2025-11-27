<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Billing', url: '/billing'},
        {name: `Invoice #${invoice.invoice_number ? invoice.invoice_number : ` <span class='fa fa-spinner fa-spin' />`}`, active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <div
        id="editModal"
        class="modal fade"
        tabindex="-1"
        role="dialog"
        aria-labelledby="exampleModalLabel"
        aria-hidden="true"
      >
        <div
          class="modal-dialog"
          role="document"
        >
          <div class="modal-content">
            <div class="modal-header">
              <h5
                id="exampleModalLabel"
                class="modal-title"
              >
                Edit Invoice Item
              </h5>
              <button
                type="button"
                class="close"
                data-dismiss="modal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label for>Item:</label>
                <label for>{{ edit_item }}</label>
              </div>

              <div class="form-group">
                <label for="price">Price</label>
                <input
                  v-model="edit_price"
                  type="text"
                  class="form-control"
                  name="edit_price"
                  placeholder="0.00"
                >
              </div>
              <div class="form-group">
                <label for="quantity">Quantity</label>
                <input
                  v-model="edit_quantity"
                  type="text"
                  class="form-control"
                  name="edit_quantity"
                  placeholder="0.00"
                >
              </div>
              <div class="form-group">
                <label for="total">Total:</label>
                <label for>{{ edit_price * edit_quantity }}</label>
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-secondary"
                data-dismiss="modal"
              >
                Close
              </button>
              <button
                type="button"
                class="btn btn-primary"
                :disabled="updating"
                @click="updateInvoiceItem()"
              >
                <span
                  v-if="updating"
                  class="fa fa-spinner fa-spin"
                /> Save changes
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="animated fadeIn">
        <div class="container mb-2">
          <div
            v-if="hasFlashMessage"
            class="alert alert-success"
          >
            <span class="fa fa-check-circle" />
            <em>{{ flashMessage }}</em>
          </div>

          <div class="row">
            <template v-if="invoice.status != 'approved'">
              <div class="col-6">
                <template v-if="invoice.brand_notes != null && invoice.brand_notes !== ''">
                  <div class="card mb-0">
                    <div class="card-header p-2">
                      Brand Notes
                    </div>
                    <div class="card-body alert-warning p-2">
                      <p class="">
                        {{ invoice.brand_notes }}
                      </p>
                    </div>
                  </div>
                </template>
                <template v-else>
                  &nbsp;
                </template>
              </div>
              <div class="col-6">
                <a
                  class="btn btn-lg btn-success pull-right"
                  :href="`/invoice/${invoice.id}/approve`"
                  onclick="return confirm('Are you sure you want to approve this invoice?')"
                >Approve</a>
              </div>
            </template>
            <template v-else>
              <div class="col-12">
                <div class="alert alert-success">
                  This invoice is approved.
                  <div
                    v-if="invoice.pdf !== null && invoice.pdf !== ''"
                    class="text-right"
                  >
                    <div
                      v-for="file in invoice.pdf"
                      :key="file.doc"
                      :value="file.filename"
                      class="d-inline mr-2"
                    >
                      <a
                        target="_blank"
                        :href="`/invoice/${invoice.id}/download?url=${file.filename}&add_cdn=true`"
                        class="btn btn-success"
                      >
                        <i
                          class="fa fa-download"
                          aria-hidden="true"
                        />
                        Download {{ file.doc }}
                      </a>
                    </div>
                    <a
                      :href="`/invoice/${invoice.id}/send`"
                      class="btn btn-warning"
                    >
                      <i
                        class="fa fa-envelope"
                        aria-hidden="true"
                      /> Re-send Invoice Email
                    </a>
                  </div>
                  <div
                    v-else
                    class="text-right"
                  >
                    <a
                      :href="`/invoice/${invoice.id}/generate`"
                      class="btn btn-warning"
                    >Generate a PDF</a>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
        <ul
          v-if="supplemental_invoice === 1"
          class="nav nav-tabs"
        >
          <li class="nav-item active">
            <a
              class="nav-link"
              :href="`/invoice/${invoice.id}`"
            >
              <i class="fa fa-list" /> Invoice
            </a>
          </li>
          <li class="nav-item">
            <a
              class="nav-link"
              :href="`/invoices/${invoice.id}/supplemental_report`"
              target="_blank"
            >
              <i class="fa fa-usd" /> Supplmental Invoice
            </a>
          </li>
          <li class="nav-item">
            <a
              class="nav-link"
              :href="`/invoices/${invoice.id}/live_minutes_view`"
              target="_blank"
            >
              <i class="fa fa-clock-o" /> Live Minutes Report
            </a>
          </li>
        </ul>
        <div class="card">
          <div class="card-body">
            <div class="row p-2">
              <div
                class="col-md-10"
                style="border-right: 2px solid #DDDDDD;"
              >
                <div class="invoice-title">
                  <div class="row">
                    <div class="col-md-6">
                      <h2>
                        <img src="https://tpv-assets.s3.amazonaws.com/tpv-new-220x120.png">
                      </h2>
                    </div>
                    <div class="col-md-6">
                      <h3 class="text-right">
                        <strong>Invoice for TPV Services</strong>
                        <br>
                        <small>Period: {{ $moment(invoice.invoice_start_date).format('MM/DD/YYYY') }} - {{ $moment(invoice.invoice_end_date).format('MM/DD/YYYY') }}</small>
                      </h3>
                    </div>
                  </div>
                </div>

                <br>

                <div
                  v-if="showItems"
                  class="row"
                >
                  <div class="col-md-6">
                    <h5>
                      <strong>Invoice Number: {{ invoice.invoice_number }}</strong>
                      <template v-if="invoice.purchase_order_no !== null">
                        <br>
                        <strong>PO # {{ invoice.purchase_order_no }}</strong>
                      </template>
                    </h5>
                  </div>
                  <div class="col-md-6 text-right">
                    <h5>
                      <strong>Due Date: {{ $moment(invoice.invoice_due_date).format('MMMM DD, YYYY') }}</strong>
                    </h5>
                  </div>
                </div>

                <div
                  v-if="showItems"
                  class="row"
                >
                  <div class="offset-md-1 col-md-6">
                    <h5>
                      <address>
                        {{ invoice.legal_name !== null && invoice.legal_name !== undefined && invoice.legal_name !== '' && invoice.legal_name.trim() !== '' ? invoice.legal_name : invoice.name }}
                        <br>
                        {{ invoice.address }}
                        <br>
                        {{ invoice.city }}, {{ invoice.state }} {{ invoice.zip }}
                      </address>
                    </h5>
                  </div>
                  <div class="col-md-6 text-right" />
                </div>

                <br>

                <div class="row">
                  <div class="col-md-12">
                    <div class="panel panel-default">
                      <div class="panel-body">
                        <div class="table-responsive">
                          <div
                            v-if="errors.length > 0"
                            class="alert alert-danger"
                          >
                            <ul>
                              <li
                                v-for="(error, index) in errors"
                                :key="index"
                              >
                                {{ error }}
                              </li>
                            </ul>
                          </div>
                          <form
                            :action="`/invoice/${invoice.id}/add`"
                            method="post"
                            autocomplete="off"
                          >
                            <input
                              name="_token"
                              type="hidden"
                              :value="csrf_token"
                            >

                            <div v-if="!showItems">
                              <hr>
                              <span class="fa fa-spinner fa-spin fa-2x" /> Loading Invoice
                              <hr>
                            </div>
                            <table
                              v-if="showItems"
                              class="table table-condensed table-bordered table-striped"
                            >
                              <thead>
                                <tr>
                                  <td>
                                    <strong>Item</strong>
                                  </td>
                                  <td class="text-center">
                                    <strong>Price</strong>
                                  </td>
                                  <td class="text-center">
                                    <strong>Quantity</strong>
                                  </td>
                                  <td class="text-right">
                                    <strong>Totals</strong>
                                  </td>
                                  <td
                                    v-if="invoice.status !== 'approved'"
                                    class="text-center"
                                  />
                                </tr>
                              </thead>
                              <tbody>
                                <template v-if="live.length > 1">
                                  <tr>
                                    <td>
                                      Live Minutes
                                      <a
                                        :href="`/invoices/${invoice.id}/live_minutes_view`"
                                        target="_blank"
                                      >
                                        <span class="fa fa-external-link" />
                                      </a>
                                    </td>
                                    <td class="text-center">
                                      <b>${{ number_format(live_avg_rate, 3, ".", ",") }}</b>
                                    </td>
                                    <td class="text-center">
                                      <b>{{ number_format(live_minutes, 2, ".", ",") }}</b>
                                    </td>
                                    <td class="text-right">
                                      <b>${{ number_format(live_total, 2, ".", ",") }}</b>
                                    </td>
                                  </tr>
                                  <tr
                                    v-for="(i, index) in live"
                                    :key="index"
                                    :class="{'soft-deleted' : i.deleted_at !== null}"
                                  >
                                    <td class="text-right text-muted">
                                      {{ i.note }}
                                    </td>
                                    <td
                                      class="text-center text-muted"
                                    >
                                      ${{ number_format(i.rate, 2, ".", ",") }}
                                    </td>
                                    <td
                                      class="text-center text-muted"
                                    >
                                      {{ number_format(i.quantity, 2, ".", ",") }}
                                    </td>
                                    <td
                                      class="text-right text-muted"
                                    >
                                      ${{ number_format(i.total, 2, ".", ",") }}
                                    </td>
                                    <td v-if="invoice.status !== 'approved'">
                                      <template v-if="i.deleted_at == null">
                                        <button
                                          type="button"
                                          class="btn btn-danger btn-sm"
                                          @click="invoiceSoftDelete(i.invoice_item_id)"
                                        >
                                          <i class="fa fa-trash" />
                                        </button>
                                        <button
                                          type="button"
                                          class="btn btn-warning btn-sm"
                                          data-toggle="modal"
                                          data-target="#editModal"
                                          @click="editInvoice(i)"
                                        >
                                          <i class="fa fa-edit" />
                                        </button>
                                      </template>
                                      <template v-else>
                                        <button
                                          type="button"
                                          class="btn btn-success btn-sm"
                                          @click="invoiceRestore(i.invoice_item_id)"
                                        >
                                          <i class="fa fa-undo" />
                                        </button>
                                      </template>
                                    </td>
                                  </tr>
                                </template>
                                <template v-else>
                                  <tr
                                    :class="{'soft-deleted' : (live && live.length > 0) && live[0].deleted_at !== null}"
                                  >
                                    <td>
                                      Live Minutes
                                      <a
                                        :href="`/invoices/${invoice.id}/live_minutes_view`"
                                        target="_blank"
                                      >
                                        <span class="fa fa-external-link" />
                                      </a>
                                    </td>
                                    <td
                                      class="text-right"
                                    >
                                      ${{ number_format((live && live.length > 0)? live[0].rate : 0, 2, ".", ",") }}
                                    </td>
                                    <td
                                      class="text-right"
                                    >
                                      {{ number_format((live && live.length > 0)? live[0].quantity : 0, 2, ".", ",") }}
                                    </td>
                                    <td
                                      class="text-right"
                                    >
                                      ${{ number_format((live && live.length > 0)? live[0].total : 0, 2, ".", ",") }}
                                    </td>
                                    <td v-if="invoice.status !== 'approved'">
                                      <template
                                        v-if="(live && live.length > 0) && live[0].deleted_at == null"
                                      >
                                        <button
                                          type="button"
                                          class="btn btn-danger btn-sm"
                                          @click="invoiceSoftDelete(live[0].invoice_item_id)"
                                        >
                                          <i class="fa fa-trash" />
                                        </button>
                                        <button
                                          type="button"
                                          class="btn btn-warning btn-sm"
                                          data-toggle="modal"
                                          data-target="#editModal"
                                          @click="editInvoice(live[0])"
                                        >
                                          <i class="fa fa-edit" />
                                        </button>
                                      </template>
                                      <template v-else>
                                        <button
                                          type="button"
                                          class="btn btn-success btn-sm"
                                          @click="invoiceRestore(live[0].invoice_item_id)"
                                        >
                                          <i class="fa fa-undo" />
                                        </button>
                                      </template>
                                    </td>
                                  </tr>
                                </template>

                                <tr
                                  v-for="(i, index) in items"
                                  :key="index"
                                  :class="{'soft-deleted' : i.deleted_at !== null}"
                                >
                                  <td>
                                    {{ i.item_desc }}
                                    <template v-if="i.note">
                                      {{ i.note }}
                                    </template>
                                    <span v-html="getReportLink(i.invoice_desc_id)" />
                                  </td>
                                  <td class="text-right">
                                    <template
                                      v-if="i.item_desc == 'Interconnect Fee (domestic)'"
                                    >
                                      ${{ number_format(i.rate, 3, ".", ",") }}
                                    </template>
                                    <template v-else>
                                      ${{ number_format(i.rate, 2, ".", ",") }}
                                    </template>
                                  </td>
                                  <td
                                    class="text-right"
                                  >
                                    {{ number_format(i.quantity, 2, ".", ",") }}
                                  </td>
                                  <td class="text-right">
                                    ${{ number_format(i.total, 2, ".", ",") }}
                                  </td>
                                  <td v-if="invoice.status !== 'approved'">
                                    <template v-if="i.deleted_at == null">
                                      <button
                                        type="button"
                                        class="btn btn-danger btn-sm"
                                        @click="invoiceSoftDelete(i.invoice_item_id)"
                                      >
                                        <i class="fa fa-trash" />
                                      </button>
                                      <button
                                        type="button"
                                        class="btn btn-warning btn-sm"
                                        data-toggle="modal"
                                        data-target="#editModal"
                                        @click="editInvoice(i)"
                                      >
                                        <i class="fa fa-edit" />
                                      </button>
                                    </template>
                                    <template v-else>
                                      <button
                                        type="button"
                                        class="btn btn-success btn-sm"
                                        @click="invoiceRestore(i.invoice_item_id)"
                                      >
                                        <i class="fa fa-undo" />
                                      </button>
                                    </template>
                                  </td>
                                </tr>

                                <tr v-if="invoice.status != 'approved' && invoice_desc !== null">
                                  <td>
                                    <select
                                      id="item_desc"
                                      name="item_desc"
                                      class="form-control"
                                    >
                                      <option
                                        v-for="id in invoice_desc"
                                        :key="id.id"
                                        :value="id.id"
                                      >
                                        {{ id.item_desc }}
                                      </option>
                                    </select>

                                    <br>

                                    <input
                                      id="item_desc_note"
                                      type="text"
                                      class="form-control"
                                      name="item_desc_note"
                                      placeholder="Item Desc (optional)"
                                    >
                                  </td>
                                  <td class="text-right">
                                    <input
                                      id="item_price"
                                      type="text"
                                      class="form-control"
                                      name="item_price"
                                      placeholder="0.00"
                                      @keyup="updateTotals"
                                    >
                                  </td>
                                  <td class="text-right">
                                    <input
                                      id="item_quantity"
                                      type="text"
                                      class="form-control"
                                      name="item_quantity"
                                      placeholder="0.00"
                                      @keyup="updateTotals"
                                    >
                                  </td>
                                  <td class="text-right">
                                    $
                                    <span id="item_total">0.00</span>

                                    <br>
                                    <br>

                                    <button class="btn btn-sm btn-success">
                                      add
                                    </button>
                                  </td>
                                </tr>

                                <tr>
                                  <td colspan="2" />
                                  <td align="right">
                                    <b>Total</b>
                                  </td>
                                  <td align="right">
                                    <input
                                      id="grand_total_unformatted"
                                      type="hidden"
                                      name="grand_total_unformatted"
                                      :value="amount"
                                    >
                                    $
                                    <span
                                      id="grand_total"
                                    >{{ number_format(amount, 2, ".", ",") }}</span>
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                          </form>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-2">
                <br>
                <br>

                <h4>TPV.com</h4>
                <br>Our address:
                <br>3930 Commerce Avenue
                <br>
                <br>Willow Grove, PA 19090
                <br>
                <br>
                <hr>
                <br>
                <br>For questions on your invoice, please contact AnswernetTPV at 
                <a
                  href="mailto:accountmanagers@answernet.com"
                >accountmanagers@answernet.com</a>.
              </div>
            </div>

            <br>

            <center>
              <strong>
                Remittance for Electronic Payments<br>
                Financial Institution: Firstrust Bank<br>
                15 E. Ridge Pike; Conshohocken, PA 19248<br>
                Account name: AnswerNet TPV<br>
                ABA number: 236073801<br>
                Account number: 8000335722<br>
              </strong>
            </center>

            <br>

            <hr style="border-style: dashed;">

            <p class="text-center">
              Please detach and return this remittance portion with your check.
            </p>

            <br>

            <div class="row">
              <div class="col-md-12">
                <div class="row">
                  <div class="col-md-4">
                    <img src="https://tpv-assets.s3.amazonaws.com/tpv-new-220x120.png">
                  </div>
                  <div class="col-md-4">
                    <strong>TPV.com</strong>
                    <br>Attn: Accounts Receivable
                    <br>3930 Commerce Avenue
                    <br>Willow Grove, PA 19090
                  </div>
                  <div class="col-md-4 text-right">
                    Invoice Number: {{ invoice.invoice_number }}
                    <br>
                    <template v-if="invoice.purchase_order_no !== null">
                      PO # {{ invoice.purchase_order_no }}
                      <br>
                    </template>
                    Bill Date: {{ $moment(invoice.invoice_bill_date).format('MMMM DD, YYYY') }}
                    <br>
                    Due Date: {{ $moment(invoice.invoice_due_date).format('MMMM DD, YYYY') }}
                    <br>Amount Due: $
                    <span id="amount_bottom">{{ number_format(amount, 2, ".", ",") }}</span>
                    <br>
                    <br>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="invoice.status != 'approved'"
        align="right"
      >
        <form
          method="post"
          action="/billing/regenerate"
        >
          <input
            name="_token"
            type="hidden"
            :value="csrf_token"
          >
          <input
            type="hidden"
            name="invoice_id"
            :value="invoice.id"
          >
          <button
            class="btn btn-danger"
            onclick="return confirm('Are you sure you want to regenerate this invoice?');"
          >
            Re-generate this invoice
          </button>
        </form>
      </div>

      <br>

      <table
        v-if="invoice_tracking.length > 0"
        class="table table-bordered table-striped"
      >
        <tr>
          <th>Date</th>
          <th>Type</th>
          <th>IP Address</th>
        </tr>
        <tr
          v-for="(it, index) in invoice_tracking"
          :key="index"
        >
          <td>{{ it.created_at }}</td>
          <td>
            <template v-if="it.invoice_tracking_type_id == 1">
              Email
            </template>
            <template v-else>
              URL
            </template>
          </td>
          <td>{{ long2ip(it.ip_addr) }}</td>
        </tr>
      </table>
    </div>
  </div>
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'Invoice',
    components: {
        Breadcrumb,
    },
    props: {
        errors: {
            type: Array,
            default: function() {
                return [];
            },
        },
        hasFlashMessage: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            updating: false,
            invoice: {},
            live: [],
            items: [],
            invoice_tracking: [],
            invoice_desc: [],
            live_avg_rate: 0,
            live_minutes: 0,
            live_total: 0,
            edit_item: '',
            edit_quantity: 0,
            edit_total: 0,
            edit_price: 0,
            edit_id: '',
            csrf_token: window.csrf_token,
            showItems: false,
            supplemental_invoice: 0,
        };
    },
    computed: {
        amount() {
            let amount = 0;
            if (this.live.length > 1) {
                this.live.forEach((element) => {
                    if (element.deleted_at == null) {
                        amount += element.total;
                    }
                });
            }
            else if (this.live.length > 0) {
                if (this.live[0].deleted_at == null) {
                    amount = this.live[0].total;
                }
            }

            this.items.forEach((element) => {
                if (element.deleted_at == null) {
                    amount += element.total;
                }
            });

            return amount;
        },

        isUpdatable() {
            return this.edit_price != '' && this.edit_quantity != '';
        },
    },
    mounted() {
        const invoiceId = window.location.pathname.split('/').pop();
        axios
            .get(`/invoice/invoice_get_vars/${invoiceId}`)
            .then((response) => {
                const res = response.data;

                this.invoice = res.invoice;
                document.title += ` ${res.invoice.invoice_number}`;
                this.live = res.live;
                this.items = res.items;
                this.invoice_tracking = res.invoice_tracking;
                this.invoice_desc = res.invoice_desc;
                this.live_avg_rate = res.live_avg_rate;
                this.live_minutes = res.live_minutes;
                this.live_total = res.live_total;
                this.supplemental_invoice = res.supplemental_invoice;

                this.showItems = true;
            })
            .catch(console.log);
    },
    methods: {
        long2ip(ip) {
            if (!isFinite(ip)) {
                return false;
            }

            return [
                (ip >>> 24) & 0xff,
                (ip >>> 16) & 0xff,
                (ip >>> 8) & 0xff,
                ip & 0xff,
            ].join('.');
        },
        number_format(number, decimals, decPoint, thousandsSep) {
            number = `${number}`.replace(/[^0-9+\-Ee.]/g, '');
            const n = !isFinite(+number) ? 0 : +number;
            const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
            const sep = typeof thousandsSep === 'undefined' ? ',' : thousandsSep;
            const dec = typeof decPoint === 'undefined' ? '.' : decPoint;
            let s = '';

            const toFixedFix = function(n, prec) {
                if (`${n}`.indexOf('e') === -1) {
                    return +`${Math.round(`${n}e+${prec}`)}e-${prec}`;
                }
                const arr = `${n}`.split('e');
                let sig = '';
                if (+arr[1] + prec > 0) {
                    sig = '+';
                }
                return (+`${Math.round(
                    `${+arr[0]}e${sig}${+arr[1] + prec}`,
                )}e-${prec}`).toFixed(prec);
            };

            // @todo: for IE parseFloat(0.55).toFixed(0) = 0;
            s = (prec ? toFixedFix(n, prec).toString() : `${Math.round(n)}`).split(
                '.',
            );
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }

            return s.join(dec);
        },
        formatNumber(num) {
            return num.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
        },
        updateTotals() {
            const quantity = $('#item_quantity').val() * 1;
            const price = $('#item_price').val() * 1;
            const total_amount = $('#grand_total_unformatted').val() * 1;
            if (quantity > 0 && price > 0) {
                const total = quantity * price;
                $('#item_total').html(this.formatNumber(total.toFixed(2)));
                const grand_total = total + total_amount;
                $('#grand_total').html(this.formatNumber(grand_total.toFixed(2)));
                $('#amount_bottom').html(this.formatNumber(grand_total.toFixed(2)));
            }
            else {
                $('#item_total').html('0.00');
                $('#grand_total').html(this.formatNumber(total_amount.toFixed(2)));
                $('#amount_bottom').html(this.formatNumber(total_amount.toFixed(2)));
            }
        },
        invoiceSoftDelete(invoiceItemId) {
            axios
                .post('/invoice-item/soft-delete', { invoiceItemId: invoiceItemId })
                .then((response) => {
                    const res = response.data;
                    this.live = res.live;
                    this.items = res.items;

                    window.location.reload();
                })
                .catch((err) => {
                    console.log(err);
                });
        },
        invoiceRestore(invoiceItemId) {
            axios
                .post('/invoice-item/restore', { invoiceItemId: invoiceItemId })
                .then((response) => {
                    const res = response.data;
                    this.live = res.live;
                    this.items = res.items;

                    window.location.reload();
                })
                .catch((err) => {
                    console.log(err);
                });
        },
        editInvoice(invoiceItem) {
            this.edit_item = invoiceItem.item_desc;
            this.edit_quantity = invoiceItem.quantity;
            this.edit_total = invoiceItem.total;
            this.edit_price = invoiceItem.rate;
            this.edit_id = invoiceItem.invoice_item_id;
        },

        getReportLink(invoice_desc_id) {
            let reportUrl = null;
            switch (invoice_desc_id) {
                case 3: // IVR
                    reportUrl = `/reports/ivr_report?startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&brand[]=${this.invoice.brand_id}`;
                    break;
                case 6: // EZTPV (DTD)
                    reportUrl = `/reports/eztpv_by_channel?startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&channel[]=1&brand[]=${this.invoice.brand_id}`;
                    break;
                case 7: // EZTPV (Retail)
                    reportUrl = `/reports/eztpv_by_channel?startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&channel[]=3&brand[]=${this.invoice.brand_id}`;
                    break;
                case 13: // Document Services (contracts)
                    reportUrl = `/reports/report_contracts?upload_type_id=3&startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&brand[]=${this.invoice.brand_id}`;
                    break;
                case 14: // SMS / Text Delivery
                    reportUrl = `/reports/sms?mode=notif&startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&brand[]=${this.invoice.brand_id}`;
                    break;
                case 16: // EZTPV (TM)
                    reportUrl = `/reports/eztpv_by_channel?startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&channel[]=2&brand[]=${this.invoice.brand_id}`;
                    break;
                case 25: // Digital TPV
                    reportUrl = `/reports/digital_report?startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&brand[]=${this.invoice.brand_id}`;
                    break;
                case 28: // API Submissions
                    reportUrl = `/reports/api_submissions?startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&brand[]=${this.invoice.brand_id}`;
                    break;
                case 29: // Web Enroll
                    reportUrl = `/reports/web_enroll_submissions?startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&brand[]=${this.invoice.brand_id}`;
                    break;
                case 34: // Daily Questionnaires
                    reportUrl = `/reports/questionnaire_report?startDate=${this.cleanDate(this.invoice.invoice_start_date)}&endDate=${this.cleanDate(this.invoice.invoice_end_date)}&brand[]=${this.invoice.brand_id}`;
                    break;
            }
            if (reportUrl !== null) {
                return `<a href="${reportUrl}" target="_blank"><span class="fa fa-external-link"></span></a>`;
            }
            return '';
        },

        cleanDate(dirty_date) {
            const parts = dirty_date.split(' ');
            return parts[0];
        },

        updateInvoiceItem() {
            const data = {
                quantity: this.edit_quantity,
                price: this.edit_price,
                id: this.edit_id,
            };

            if (this.isUpdatable) {
                this.updating = true;
                axios
                    .post('/invoice-item/update', data)
                    .then((response) => {
                        const res = response.data;
                        this.live = res.live;
                        this.items = res.items;
                        $('#editModal').modal('hide');

                        window.location.reload();
                    })
                    .catch((err) => {
                        console.log(err);
                        $('#editModal').modal('hide');
                    });
            }
            else {
                alert('Invalid input.');
            }
        },
    },
};
</script>
<style>
.invoice-title h2,
.invoice-title h3 {
  display: inline-block;
}

.table > tbody > tr > .no-line {
  border-top: none;
}

.table > thead > tr > .no-line {
  border-bottom: none;
}

.table > tbody > tr > .thick-line {
  border-top: 2px solid;
}

.soft-deleted {
  text-decoration: line-through;
  color: red;
}
</style>
