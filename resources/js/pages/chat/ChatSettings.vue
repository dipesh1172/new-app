<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Support'},
        {name: 'Chat Settings', active: true}
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Chat Settings
          </div>
          <div class="card-body">
            <form
              method="POST"
              action="/chat-settings"
              accept-charset="UTF-8"
            >
              <input
                name="_token"
                type="hidden"
                :value="csrfToken"
              >

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

              <div class="row">
                <div class="col-md-12">
                  <div class="form-row">
                    <div class="col-md-4">
                      <div class="form-group">
                        Chat Enabled<br>
                        <label class="switch">
                          <input 
                            type="checkbox" 
                            name="chat_enabled"
                            :checked="chatEnabled" 
                          >
                          <span class="slider round" />
                        </label>
                      </div>
                    </div>

                    <hr>
                  </div>
                  <button
                    type="submit"
                    class="btn btn-primary pull-right"
                  >
                    <i class="fa fa-save" /> Save
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div> 
  </div>
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';
import { mapState } from 'vuex';

export default {
    name: 'ChatSettings',
    components: {
        Breadcrumb,
    },
    props: {
        chatEnabled: {
            type: Number,
            default: 0,
        },
    },
    data() {
        return {
            csrfToken: window.csrf_token,
        };
    },
    computed: mapState({
        errors: (state) => state.session.errors || [],
    }),
};
</script>
