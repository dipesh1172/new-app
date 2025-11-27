<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'KB', url: '/kb'},
        {name: `Edit Page: ${versions[0].title}`, url: `/kb/edit/${knowledgeBase.id}`},
        {name: 'Version History', active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="card">
        <div class="card-header">
          Page: "{{ versions[0].title }}" - Version History
          <button
            v-if="currentVersion !== selectedVersion"
            type="button"
            :disabled="working"
            class="btn btn-primary btn-sm pull-right"
            @click="setCurrentVersion"
          >
            <template v-if="settingVersion">
              <span class="fa fa-spinner fa-spin" />
            </template>Set V{{ selectedVersion }} as Current
          </button>
        </div>
        <div class="card-body container-fluid p-0">
          <div class="row ml-0 mr-0">
            <div class="col-2 bg-light pl-0 pr-0">
              <ul
                class="nav nav-pill nav-stacked"
                role="tablist"
              >
                <li
                  v-for="(ver, ver_i) in versions"
                  :key="`version_${ver_i}`"
                  role="presentation"
                  class="w-100"
                  :title="currentVersion === ver.version ? 'Current Version' : 'Old Version'"
                >
                  <a
                    :class="{'btn w-100': true, 'btn-primary': ver.version === currentVersion, 'btn-light': ver.version !== currentVersion && ver.version !== selectedVersion, 'btn-secondary': ver.version !== currentVersion && ver.version === selectedVersion}"
                    :disabled="working"
                    role="tab"
                    @click="changeDisplayedVersion(ver.version);"
                  >
                    <template v-if="working && selectedVersion === ver.version">
                      <span class="fa fa-spinner fa-spin" />
                    </template>
                    {{ ver.version }}: {{ ver.title }}<br>
                    <span
                      class="badge badge-light"
                      style="position: relative;"
                    >{{ ver.created_at }}</span>
                  </a>
                </li>
              </ul>
            </div>
            <div class="col-10 pl-0 pr-0">
              <iframe
                id="content-iframe"
                title="Preview"
                class="embed-responsive"
                :src="`/kb/versions/${knowledgeBase.id}?show=${selectedVersion}`"
                @load="iframeLoaded"
                @error="iframeError"
              />
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
    name: 'KbVersionHistory',
    components: {
        Breadcrumb,
    },
    props: {
        versions: {
            type: Array,
            required: true,
        },
        knowledgeBase: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            currentVersion: this.knowledgeBase.current_version,
            selectedVersion: this.knowledgeBase.current_version,
            working: true,
            settingVersion: false,
        };
    },
    methods: {
        changeDisplayedVersion(newVersion) {
            if (this.working) {
                return;
            }
            this.working = true;
            this.selectedVersion = newVersion;
        },
        iframeLoaded(e) {
            this.working = false;
        },
        iframeError(e) {
            this.working = false;
            console.log(e);
            alert('Error loading page');
        },
        setCurrentVersion() {
            if (this.working) {
                return;
            }
            this.working = true;
            this.settingVersion = true;
            axios.get(`/kb/versions/${this.knowledgeBase.id}?set=${this.selectedVersion}`)
                .then(() => {
                    window.location.reload();
                })
                .catch((e) => {
                    this.settingVersion = false;
                    this.working = false;
                    console.log(e);
                    alert(`Error setting current version: ${e}`);
                });
        },
    },
};
</script>

<style scoped>
    #content-iframe {
        min-height: 600px;
        width: 100%;
    }
	.nav-stacked {
		height: 600px;
		overflow-y: auto;
        overflow-x: hidden;
	}
	#tools hr {
		margin-top: 5px;
		margin-bottom: 5px;
	}
</style>
