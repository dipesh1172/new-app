<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item">
        <a href="/kb">KB</a>
      </li>
      <li class="breadcrumb-item active">
        Edit {{ version.title }}
      </li>
    </ol>
    <div class="container-fluid">
      <div class="row" />
      <div class="row">
        <div class="card col-12">
          <div class="card-body p-0">
            <div
              v-if="!dataIsLoaded"
              class="text-center"
            >
              <span class="fa fa-spinner fa-spin fa-2x" />
            </div>
            <div
              v-if="dataIsLoaded && Object.entries(kb).length === 0"
              class="text-center"
            >
              <div
                class="alert alert-danger"
                role="alert"
              >
                No KB was founded. Try it again <a href="/kb">KB Dashboard</a>
              </div>
            </div>
            <div
              v-if="errors.length"
              class="alert alert-danger"
            >
              <strong>Errors</strong>
              <br>
              <ul>
                <li
                  v-for="(error, i) in errors"
                  :key="i"
                >
                  {{ error }}
                </li>
              </ul>
            </div>
            <div
              id="success_msg"
              role="alert"
              class="alert alert-success d-none"
            >
              The KB was succefully edited. You will be redirected to KB dashboard in <strong>{{ counter }}</strong> seconds
              <button
                type="button"
                class="btn btn-primary ml-1 btn-sm"
                @click="interceptRedirect"
              >
                Cancel
              </button>
            </div>
            <form
              id="create-form"
              method="POST"
              enctype="multipart/form-data"
              class="form-horizontal pt-2"
            >
              <div class="form-group">
                <label class="form-control-label">Version</label>
                <div class="form-row">
                  <div class="col-sm-11">
                    <input
                      id="version"
                      type="text"
                      :value="version.version"
                      class="form-control"
                      readonly
                    >
                  </div>
                  <div class="col-sm-1">
                    <a
                      title="View Versions"
                      :href="`/kb/versions/${kb.id}`"
                      class="btn btn-success"
                    >
                      <span class="fa fa-history" />
                    </a>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label
                  for="title"
                  class="form-control-label"
                >Title</label>
                <input
                  id="title"
                  type="text"
                  class="form-control"
                  name="title"
                  placeholder="Enter title here"
                  :value="version.title"
                  required
                >
              </div>
              <!-- <div class="form-group">
                <label for="category" class="form-control-label">Category</label>
                <div class="form-row">
                  <div class="col-sm-11">
                    <select id="category" name="category" class="form-control">
                      <option value></option>
                      <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                        :selected="category.id == version.category"
                      >{{category.name}}</option>
                    </select>
                  </div>
                  <div class="col-sm-1">
                    <button type="button" class="btn btn-success" @click="addCategory">
                      <span class="fa fa-plus"></span>
                    </button>
                  </div>
                </div>
              </div>-->

              <div class="form-group">
                <label
                  for="content"
                  class="form-control-label"
                >Page Content</label>
                <div id="content-errors" />
                <textarea
                  id="content"
                  name="content"
                />
              </div>
              <div class="form-group">
                <label
                  for="submit"
                  class="form-control-label"
                >&nbsp;</label>
                <button
                  id="submit"
                  class="btn btn-primary pull-right"
                  :class="{'disabled': dataIsLoaded && Object.entries(kb).length === 0}"
                  @click="do_save"
                >
                  Save
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
export default {
    name: 'KBEdit',
    data() {
        return {
            // categories: [],
            errors: [],
            kb: {},
            version: {},
            dataIsLoaded: false,
            counter: 10,
            interval: null,
        };
    },
    watch: {
        counter() {
            if (this.counter === 0) {
                window.location.href = '/kb/AllPages';
            }
        },
    },
    mounted() {
        document.title += ' Editing KB Entry';

        const KBID = window.location.href.split('/').pop();
        axios
            .get(`/kb/get_edit/${KBID}`)
            .then((response) => {
                // this.categories = response.data.categories;
                this.kb = response.data.kb;
                this.version = response.data.version;
                // Necessary for tinymce to load the content
                window.tinymce.activeEditor.setContent(this.version.content);

                this.dataIsLoaded = true;
            })
            .catch(console.log);

        $(() => {
            window.tinymce.init({
                selector: 'textarea',
                branding: false,
                valid_elements: '+*[*]',
                height: 450,
                theme: 'modern',
                relative_urls: true,
                document_base_url: '/kb/',
                convert_urls: false,
                plugins: [
                    'advlist autolink lists link image charmap print preview hr anchor pagebreak',
                    'searchreplace wordcount visualblocks visualchars code fullscreen',
                    'insertdatetime media nonbreaking save table contextmenu directionality',
                    'emoticons template paste textcolor colorpicker textpattern imagetools noneditable tpv_flashcard',
                ],
                toolbar1:
          'undo redo | insert | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | forecolor backcolor emoticons',
                image_advtab: true,
                content_css: ['/css/app.css'],
                body_class: 'tab-content p-3',
            });
        });
    },
    methods: {
        do_save(evt) {
            $('#submit').addClass('disabled');
            this.dataIsLoaded = false;
            if ($('#create-form').checkValidity && !$('#create-form').checkValidity()) { return; }
            evt.preventDefault();
            const values = {
                title: $('#title').val(),
                content: window.tinymce.activeEditor.getContent(),
                // category: $('#category').val(),
                _token: window.csrf_token,
            };
            axios
                .patch(`/kb/edit/${this.kb.id}`, values)
                .then((response) => {
                    $('#submit').removeClass('disabled');
                    $('#version').val(response.current_version);
                    $('#success_msg').removeClass('d-none');

                    this.interval = setInterval(() => {
                        if (this.counter === 0) {
                            clearInterval(this.interval);
                        }
                        this.counter--;
                    }, 1000);

                    this.dataIsLoaded = true;
                })
                .catch((error) => {
                    $('#submit').removeClass('disabled');
                    if (error.response.status == 422) {
                        for (const [key, value] of Object.entries(
                            error.response.data.errors,
                        )) {
                            this.errors.push(value);
                        }
                    }
                });
        },
        interceptRedirect() {
            clearInterval(this.interval);
            this.counter = 10;
            $('#success_msg').hide();
        },
    // addCategory() {
    //   let c = prompt('Enter name of Category');
    //   if (c != '') {
    //     this.categories.push({ id: c, name: c });
    //   }
    // },
    },
};
</script> 
