<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item">
        <a href="/issues">Issues</a>
      </li>
      <li class="breadcrumb-item active">Report New Issue</li>
    </ol>

    <div class="container-fluid">
      <div class="row"></div>
      <div class="row">
        <div class="card col-12">
          <div class="card-body">
            <div v-if="hasFlashMessage" class="alert alert-success">
              <span class="fa fa-check-circle" />
              <em>{{ flashMessage }}</em>
            </div>
            <ValidationObserver ref="observer">
              <form
                method="POST"
                id="create-form"
                enctype="multipart/form-data"
                class="form-horizontal"
                ref="formObject"
              >
                <input name="_token" type="hidden" :value="csrf_token" />
                  <div class="form-group" v-if="addTo === null">
                    <label for="title" class="form-control-label">Brief Issue Description</label>
                    <input
                      type="text"
                      class="form-control"
                      id="title"
                      name="title"
                      placeholder
                    />
                    <div class="text-danger">{{ errors[0] }}</div>
                  </div>
                <ValidationProvider rules="required" v-slot="{ errors }" name="call center">
                  <div class="form-group">
                    <label for="center" class="form-control-label">Which Call Center</label>
                    <select id="center" name="center" class="form-control" v-model="callCenter">
                      <option value="tulsa">Tulsa</option>
                      <option value="tqh">Tahlequah</option>
                      <option value="vegas">Las Vegas</option>
                    </select>
                    <div class="text-danger">{{ errors[0] }}</div>
                  </div>
                </ValidationProvider>
                <template v-if="addTo === null">
                    <div class="form-group">
                      <label
                        for="category"
                        class="form-control-label"
                      >Is this a report of a new issue or an additional instance for an existing issue?</label>
                      <select id="category" name="category" class="form-control">
                        <option value="new">It is a NEW issue</option>
                        <option value="existing">It is an EXISTING issue</option>
                      </select>
                      <div class="text-danger">{{ errors[0] }}</div>
                    </div>
                </template>
                <template v-else>
                  <input type="hidden" name="category" value="existing" />
                  <input type="hidden" name="add_to" :value="addTo" />
                </template>
                <ValidationProvider rules="required" v-slot="{ errors }" name="agents affected">
                  <div class="form-group">
                    <label for="agents" class="form-control-label">Agents Affected</label>
                    <input
                      type="text"
                      class="form-control"
                      id="agents"
                      name="agents"
                      placeholder
                      v-model="agents"
                    />
                    <div class="text-danger">{{ errors[0] }}</div>
                  </div>
                </ValidationProvider>
                <ValidationProvider rules="required" v-slot="{ errors }" name="time/date of occurence">
                  <div class="form-group">
                    <label for="occurence" class="form-control-label">Time and Date of Occurence</label>
                    <input
                      type="text"
                      class="form-control"
                      id="occurence"
                      name="occurence"
                      placeholder
                      v-model="timeDate"
                    />
                    <div class="text-danger">{{ errors[0] }}</div>
                  </div>
                </ValidationProvider>
                <div class="form-group">
                  <label for="confirmation-code" class="form-control-label">Confirmation Code(s)</label>
                  <input
                    type="text"
                    class="form-control"
                    id="confirmation-code"
                    name="confirmation-code"
                    placeholder
                    required
                  />
                </div>

                <ValidationProvider rules="required" v-slot="{ errors }" name="description of issue">
                  <div class="form-group row">
                    <div class="col-md-8">
                      <label for="content" class="form-control-label">Describe the Issue in more detail</label>
                      <textarea id="content" class="form-control" rows="10" name="content" v-model="content"></textarea>
                      <div class="text-danger">{{ errors[0] }}</div>
                    </div>
                    <div class="col-md-4">
                      <div class="card">
                        <div class="card-body">
                          <!-- <h4 class="card-title">What should you put here? <i class="fa fa-question-circle pull-right mr-2"></i></h4> -->
                          <div class="card-text">
                            <p>A great description is just the facts. List exactly what happened including any error messages and also what you expected to happen that didn't.</p>
                            <p>
                              You can exclude info from other boxes like confirmation code and agent info, but any information you can provide here may be helpful so include the company
                              name and any other information you feel may be relevant.
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </ValidationProvider>

                <div class="form-group">
                  <label for="screenshot" class="form-control-label">Screenshot</label>
                  <input
                    type="file"
                    accept="image/png, image/jpeg"
                    class="form-control"
                    id="screenshot"
                    name="screenshot"
                    placeholder
                  />
                </div>

                <div class="form-group">
                  <label for="log" class="form-control-label">Console Log (if available)</label>
                  <input type="file" class="form-control" id="log" name="log" placeholder />
                </div>

                <div class="form-group">
                  <label for="submit" class="form-control-label">&nbsp;</label>
                  <button class="btn btn-primary pull-right" type="button" @click="onSubmit">Submit Issue Report</button>
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

import {
  ValidationProvider,
  ValidationObserver
} from 'vee-validate/dist/vee-validate.full';

export default {
  name: 'NewIssue',
  components: {
    ValidationProvider,
    ValidationObserver
  },
  props: {
    addTo: {
      type: String,
      default: '',
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
      csrf_token: window.csrf_token,
      content: null,
      timeDate: null,
      agents: null,
      callCenter: null
    };
  },
  mounted() {
    document.title += ' Report New Issue';
  },
  methods: {
    onSubmit() {
      this.$refs.observer.validate().then(success => {
        if (!success) {
          return;
        }

        this.$refs.formObject.submit();
      });
    }
  }
};
</script>