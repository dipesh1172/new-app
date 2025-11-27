<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item">
        <a href="/config">Company Configuration</a>
      </li>
      <li class="breadcrumb-item active">
        Runtime Settings
      </li>
    </ol>
    <div class="container-fluid">
      <div
        v-if="status"
        class="row"
      >
        <div class="col-md-12">
          <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
          >
            <button
              type="button"
              class="close"
              data-dismiss="alert"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
            <strong>{{ status }}</strong>
          </div>
        </div>
      </div>
      <form method="POST">
        <input
          type="hidden"
          name="_token"
          :value="csrf_token"
        >
        <div
          v-for="section in Object.keys(settings)"
          :key="section"
          class="row"
        >
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">
                {{ section.charAt(0).toUpperCase() + section.slice(1) }}
              </div>
              <ul class="list-group list-group-flush">
                <li
                  v-for="(set, index) in settings[section]"
                  :key="index"
                  class="list-group-item"
                >
                  <div class="form-row">
                    <div class="col-md-6">
                      <label
                        :for="set.namespace+'-'+set.name"
                      >{{ titleCase(set['name'].replace(/_/g, ' ')) }}</label>
                      <p
                        v-if="set['namespace'] == 'system' && set['name'] == 'high_volume'"
                        class="text-muted"
                      >
                        If enabled plays a message at the beginning of the IVR after our greeting. Default message (if enabled) is
                        "We appreciate your patience but we are experiencing high call volume at this time", however, you can also set
                        a custom message.
                      </p>
                      <p
                        v-if="set['description'] != null && set['description'] !== ''"
                        class="text-muted"
                        v-text="set['description']"
                      />
                    </div>
                    <div class="col-md-6">
                      <template v-if="endsWith(set['name'])">
                        <select
                          :id="set['namespace']+'-'+set['name']"
                          class="form-control"
                          type="text"
                          :name="set['namespace']+'-'+set['name']"
                          :value="set['value']"
                        >
                          <option value="0">
                            Disabled
                          </option>
                          <option
                            :selected="set['value'] == 1"
                            value="1"
                          >
                            Enabled
                          </option>
                        </select>
                      </template>
                      <template v-else>
                        <template
                          v-if="set['namespace'] == 'system' && set['name'] == 'high_volume'"
                        >
                          <div class="container-fluid">
                            <div class="form-check">
                              <input
                                :id="`${set['namespace']}-${set['name']}-1`"
                                class="form-check-input"
                                type="radio"
                                :name="set['namespace']+'-'+set['name']"
                                value="0"
                                :checked="set['value'] == 0"
                              >
                              <label
                                class="form-check-label"
                                :for="`${set['namespace']}-${set['name']}-1`"
                              >Disabled</label>
                            </div>
                            <div class="form-check">
                              <input
                                :id="`${set['namespace']}-${set['name']}-2`"
                                class="form-check-input"
                                type="radio"
                                :name="set['namespace']+'-'+set['name']"
                                value="1"
                                :checked="set['value'] == 1"
                              >
                              <label
                                class="form-check-label"
                                :for="`${set['namespace']}-${set['name']}-3`"
                              >Enabled</label>
                            </div>
                            <div class="form-check">
                              <input
                                :id="`${set['namespace']}-${set['name']}-3`"
                                class="form-check-input"
                                type="radio"
                                :name="set['namespace']+'-'+set['name']"
                                value="2"
                                :checked="isNaN(set['value'])"
                              >
                              <label
                                class="form-check-label"
                                :for="`${set['namespace']}-${set['name']}-3`"
                              >Custom Text</label>
                            </div>
                            <div class="form-row">
                              <textarea
                                :id="`${set['namespace']}-${set['name']}-4`"
                                class="form-control"
                                maxlength="150"
                                :name="`${set['namespace']}-${set['name']}:custom`"
                                v-html="isNaN(set['value'])? set['value'] : ''"
                              />
                            </div>
                          </div>
                        </template>
                        <template v-else>
                          <input
                            :id="`${set['namespace']}-${set['name']}`"
                            class="form-control"
                            type="text"
                            :name="`${set['namespace']}-${set['name']}`"
                            :value="set['value']"
                          >
                        </template>
                      </template>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
        <button
          type="submit"
          class="btn btn-primary btn-lg pull-right"
        >
          <i class="fa fa-save" /> Save Settings
        </button>
      </form>
    </div>
  </div>
</template>
<script>
export default {
    name: 'RuntimeSettings',
    props: {
        status: {
            type: String,
        },
        settings: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            csrf_token: window.csrf_token,
        };
    },
    created() {
        document.title += ' Runtime Settings';
    },
    methods: {
        titleCase(s) {
            return s
                .split(' ')
                .map((i) => i.charAt(0).toUpperCase() + i.slice(1))
                .join(' ');
        },
        endsWith(s) {
            switch (s) {
                case 'override_outgoing_number':
                case 'use_default_number':
                    return true;

                default:
                { 
                    const regex = new RegExp(/(enable|enabled)$/gm);
                    return regex.test(s);
                }
            }
        },
    },
};
</script>
