<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: 'File Transfer', active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <br>

      <div class="alert alert-info">
        This page is for the activation and configuration of file transfers to S/FTP<br><br>

        <b>Notes:</b><br><br>

        <ul>
          <li>The preferred method is for them to pull the recordings, contracts, and photos themselves using the nightly enrollment file.</li>
          <li>The upload schema is our standard and will always be <b>/&lt;date>/&lt;confirmation code></b>.  Don't ask to change it.</li>
          <li>File names are named in our standard format.  These will not be changed.  Don't ask.</li>
          <li>If a file gets missed, it can be downloaded from the UI.</li>
          <li>Only 50-100 files are being processed at any one time (across all brands) to not overload the Job server.</li>
          <li>We do not monitor if the FTP server is up or whether we can connect.  If it is down, we skip them.  When it becomes available, we will continue automatically.</li>
        </ul>

        <br>

        <b>Recording Names:</b> &lt;confirmation_code>-&lt;recording type>-&lt;datetime>.mp3 <b>Example option: "recordings_file_naming": false</b><br>
        <b>Recording Names:</b> &lt;confirmation_code>_&lt;01>_&lt;unique number>.mp3 <b>Example option: "recordings_file_naming": true</b><br>
        <b>Recording Names:</b> &lt;YYYYMMDDHHMMSS>_&lt;btn>_&lt;confirmation_code>.mp3 <b>Example option: "recordings_file_naming": "01"</b><br>
        <b>Contract Names:</b> &lt;confirmation_code>_&lt;datetime>_&lt;randomstring>.pdf<br>
        <b>Photo Names:</b> &lt;confirmation_code>_&lt;datetime>_&lt;randomstring>.png<br>

        <br>

        <div class="row">
          <div class="col-md-6">
            <b>Data Examples:</b><br><br>
            <ul>
              <li><b>Date Example</b>: 2019-01-03</li>
              <li><b>Confirmation Code Example:</b>: 123124141231</li>
              <li><b>Recording Type Example:</b> call_inbound or call_outbound</li>
              <li><b>Date Time Example:</b> 2019_02_12_13_29_51</li>
            </ul>
          </div>
          <div class="col-md-6">
            <b>Example Config:</b><br><br>
            <pre>
                {
                    "delivery_method": "sftp",
                    "username": "username",
                    "password": "password",
                    "hostname": "s/ftp hostname",
                    "port": 22,
                    "root": "/path/where/files/should/be/uploaded",
                    "file_types": [
                        "recordings",
                        "contracts",
                        "photos"
                    ],
                    "tpv_result": [
                    "sale",
                    "no sale"
                    ],
                    "create_date_folder": true,
                    "recordings_file_naming": false
                }
            </pre>
          </div>
        </div>
      </div>

      <br>

      <form
        method="post"
        :action="`/brands/${brand.id}/updateRecordings`"
        autocomplete="off"
      >
        <input
          name="_token"
          type="hidden"
          :value="csrfToken"
        >
        <div class="row">
          <div class="col-md-2">
            File Transfer
          </div>
          <div class="col">
            <label class="switch switch-text switch-lg switch-pill switch-primary">
              <input
                type="checkbox"
                class="switch-input"
                name="recording_transfer"
                :checked="brand.recording_transfer"
              >
              <span
                class="switch-label"
                data-on="On"
                data-off="Off"
              />
              <span class="switch-handle" />
            </label>
          </div>
        </div>

        <br>

        <select
          name="recording_transfer_type"
          class="form-control form-control-lg"
        >
          <option
            value="1"
            :selected="brand.recording_transfer_type == 1"
          >
            All Files up to 30 days ago
          </option>
          <option
            value="2"
            :selected="brand.recording_transfer_type == 2"
          >
            All Files
          </option>
        </select>

        <br>

        <textarea
          v-model="configTextArea"
          class="form-control form-control-lg"
          name="recording_transfer_config"
          style="width: 100%; height: 500px;"
        />

        <br>

        <p align="right">
          <button
            type="submit"
            name="submit"
            class="btn btn-danger"
          >
            <i
              class="fa fa-pencil-square-o"
              aria-hidden="true"
            /> Update
          </button>
        </p>
      </form>
    </div>
  </layout>
</template>
<script>
import Layout from './Layout';

export default {
    name: 'Recordings',
    components: {
        Layout,
    },
    props: {
        brand: {
            type: Object,
            required: true,
            default: () => {},
        },
    },
    data() {
        return {
            configTextArea: this.parseRTC(),
            csrfToken: window.csrf_token,
        };
    },
    methods: {
        parseRTC() {
            const val = this.brand.recording_transfer_config || null;
            if (!val) {
                return '';
            }
            try {
                return this.stripslashes(JSON.stringify(JSON.parse(val), null, 2));
            }
            catch (e) {
                console.log(e);
            }
            return '';
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
    },
};
</script>
