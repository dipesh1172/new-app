<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item">
        <a href="/kb">KB</a>
      </li>
      <li class="breadcrumb-item active">New Knowledge Base Entry</li>
    </ol>
    <div class="container-fluid">
      <div class="row"></div>
      <div class="row">
        <div class="card col-12">
          <div class="card-body">
            <div v-if="errors.length" class="alert alert-danger">
              <strong>Errors</strong>
              <br />
              <ul>
                <li v-for="(error, i) in errors" :key="i">{{ error }}</li>
              </ul>
            </div>
            <form
              method="POST"
              id="create-form"
              enctype="multipart/form-data"
              class="form-horizontal"
            >
              <div class="form-group">
                <label for="title" class="form-control-label">Title</label>
                <input
                  type="text"
                  class="form-control"
                  id="title"
                  name="title"
                  placeholder
                  required
                />
                <div id="title-errors"></div>
              </div>
              <!-- <div class="form-group">
                <label for="category" class="form-control-label">Category</label>
                <select id="category" name="category" class="form-control">
                  <option value></option>
                  <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                  >{{category.name}}</option>
                </select>
              </div> -->

              <div class="form-group">
                <label for="content" class="form-control-label">Page Content</label>
                <div id="content-errors"></div>
                <textarea id="content" name="content"></textarea>
              </div>
              <div class="form-group">
                <label for="submit" class="form-control-label">&nbsp;</label>
                <button class="btn btn-primary" id="submit" @click="do_save">Save</button>
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
  name: 'KBCreate',
  data() {
    return {
      //categories: [],
      errors: [],
    };
  },
  mounted() {
    document.title += ' New KB Entry';
    // axios.get(`/kb/get_categories`).then(response => {
    //   this.categories = response.data;
    // }).catch(console.log);

    $(function() {
      window.tinymce.init({
        selector: 'textarea',
        branding: false,
        valid_elements: '+*[*]',
        height: 450,
        theme: 'modern',
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
      });
    });

  },
  methods: {
    do_save(evt) {
      if ($('#create-form').checkValidity && !$('#create-form').checkValidity())
        return;
      evt.preventDefault();
      var values = {
        title: $('#title').val(),
        content: window.tinymce.activeEditor.getContent(),
        //category: $('#category').val(),
        _token: window.csrf_token,
      };

      axios
        .post(`/kb/create`, values)
        .then(response => {
          window.location.href = '/kb/edit/' + response.data.id;
        })
        .catch(error => {
          if (error.response.status == 422) {
            for (let [key, value] of Object.entries(error.response.data.errors)) {
              this.errors.push(value);
            }
          }
        });
    },
  },
};
</script>