<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: 'Disposition Shortcuts', active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <div class="animated fadeIn">
        <div class="card">
          <div
            v-if="errors.length > 0"
            class="alert alert-danger"
          >
            <ul>
              <li
                v-for="(error, index) in errors"
                :key="index"
              >
                {{ error.status }}: {{ error.statusText }}
              </li>
            </ul>
          </div>
          <h3 class="card-header">
            Current Shortcuts
          </h3>
          <div class="card-body">
            <div class="row">
              <div
                v-for="shortcut in shortcutsM"
                :key="shortcut.id"
                class="col-4"
              >
                <div class="card">
                  <div class="card-body">
                    <button
                      type="submit"
                      class="btn btn-sm btn-danger pull-right"
                      @click="removeDispo($event, shortcut)"
                    >
                      <span
                        class="fa fa-spinner fa-spin"
                        style="display: none;"
                      />
                      <i
                        class="fa fa-trash"
                        aria-hidden="true"
                      /> 
                      Remove
                    </button>
                    <h4>{{ shortcut.reason }}</h4>
                    <p>{{ shortcut.description }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="card">
          <h3 class="card-header">
            Add Shortcut
          </h3>
          <div class="card-body">
            <div class="form-group">
              <label for="disposition">Choose a Disposition</label>
              <select
                id="disposition"
                name="disposition"
                class="form-control form-control-lg"
              >
                <option />
                <option
                  v-for="dispo in dispositionsM"
                  :key="dispo.id"
                  :value="dispo.id"
                >
                  <strong>{{ dispo.reason }}</strong> - {{ (dispo.description !== null && dispo.description.length > 50) ? dispo.description.slice(0, 50) : dispo.description }}
                </option>
              </select>
            </div>
            <button
              type="submit"
              class="btn btn-primary btn-lg pull-right"
              @click="addDispo($event)"
            >
              <span
                class="fa fa-spinner fa-spin"
                style="display: none;"
              />
              <i
                class="fa fa-plus"
                aria-hidden="true"
              /> 
              Add Shortcut
            </button>
          </div>
        </div>
      </div>
    </div>     
  </layout>
</template>
<script>
import Layout from './Layout';

export default {
    name: 'DispoShortcuts',
    components: {
        Layout,
    },
    props: {
        brand: {
            type: Object,
            required: true,
            default: () => {},
        },
        dispositions: {
            type: Array,
            default: () => [],
        },
        shortcuts: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            csrfToken: window.csrf_token,
            shortcutsM: this.shortcuts,
            dispositionsM: this.dispositions,
            errors: [],
        };
    },
    methods: {
        removeDispo(e, shortcut) {
            $(e.target).find('span.fa-spinner').show();
            $(e.target).find('i').hide();
            axios.post(window.location.pathname, {
                _token: this.csrfToken,
                action: 'remove',
                disposition: shortcut.id,
            }).then((response) => {
                // Cleaning errors[] after positive response
                if (this.errors.length) {
                    this.errors.length = 0;
                }
                
                this.shortcutsM.splice(this.shortcutsM.indexOf(shortcut), 1);
                this.dispositionsM.push(response.data.disposition);
            }).catch(({response}) => {
                $(e.target).find('span.fa-spinner').hide();
                $(e.target).find('i').show();
                this.errors.push({
                    status: response.status, 
                    statusText: response.statusText,
                });
                console.log(response);
            });
        },
        addDispo(e) {
            if ($('#disposition').val()) {
                $(e.target).find('i').hide();
                $(e.target).find('span.fa-spinner').show();
                axios.post(window.location.pathname, {
                    _token: this.csrfToken,
                    action: 'add',
                    disposition: $('#disposition').val(),
                }).then((response) => {
                // Cleaning errors[] after positive response
                    if (this.errors.length) {
                        this.errors.length = 0;
                    }
                    $(e.target).find('span.fa-spinner').hide();
                    $(e.target).find('i').show();
                    this.shortcutsM.push(response.data.disposition);
                    this.dispositionsM.splice(this.dispositionsM.findIndex((d) => d.id === response.data.disposition.id), 1);
                }).catch(({response}) => {
                    $(e.target).find('span.fa-spinner').hide();
                    $(e.target).find('i').show();
                    this.errors.push({
                        status: response.status, 
                        statusText: response.statusText,
                    });
                    console.log(response);
                });
            }
            
        },
    },
};
</script>
