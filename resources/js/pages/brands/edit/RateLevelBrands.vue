<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: 'Rate-Level Brands', active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >

      <br/>

      <div class="alert alert-info">
        <p>
        This page is used to enable rate-level brand names. Once a list of brand names is defined on this page, a Brand Name field appears in the rate editor, allowing a brand to be picked from a dropdown list.
        To disable the feature, remove all the brand names from the list on this page. This will prevent the Brand Name field from appearing in the rate editor. However, any rates that already have a brand name assigned will be unaffected.
        On those rates, the Brand Name field will still appear, but only with the option of clearing that field or keeping the already assigned brand name. 
        </p>

        <p>
        &bull;&nbsp;The brand names in the list you create are not associated with any brands set up in Focus. They are merely text values that get associated to the rate, and by extension any TPV calls using that rate.<br/>
        &bull;&nbsp;This page only defines list of Brand Name available to the rate editor. Changing values in this list after the fact does not affect any rate brand name assignments.<br/>
        </p>

        <p>
        Changes are not immediate. They only take effect after the 'Save' button is clicked.<br/>
        &bull;&nbsp;Items in <span class="existing-brand">&nbsp;white&nbsp;</span> are the original items loaded from the database (if editing an existing list).<br/>
        &bull;&nbsp;Items in <span class="new-brand">&nbsp;green&nbsp;</span> will be added to the list when the <span class="alert-primary"><b>&nbsp;Save&nbsp;</b></span> button is clicked.<br/>
        &bull;&nbsp;Items in <span class="deleted-brand">&nbsp;red&nbsp;</span> will be removed from the list whne the <span class="alert-primary"><b>&nbsp;Save&nbsp;</b></span> button is clicked.<br/>
        </p>
      </div>

      <div style="text-align:center" v-if="dataIsLoading">
        <span class="fa fa-spinner fa-spin fa-5x" />
      </div>
      <div v-else>
        <div>
          <em>Last Edited: {{ brandList && brandList.created_at ? brandList.created_at + ", by: " + brandList.created_by : "Never" }}</em>
          <br/><br/>
        </div>

        <div
          v-if="hasFlashMessage"
          class="alert"
          :class="flashMessageClass"
        >
          <span class="fa" :class="flashIcon"/>
          <em>{{ flashMessage }}</em>
        </div>

        <div class="table-responsive">
          <table
            id="brands"
            class="table table-bordered"
          >
            <thead>
              <tr>
                <th>Brand Name:</th>
                <th width="50"/>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(brand,index) in brandList.brand_names"
                :key="index"
              >
                <td v-bind:class="brand.tag">
                  {{ brand['name'] }}
                </td>
                <td class="text-right">
                  <a
                    v-if="brand.tag != 'deleted-brand'"
                    class="btn btn-danger btn-sm"
                    @click="deleteBrand(index)"
                  >Delete</a>
                  <a
                    v-if="brand.tag == 'deleted-brand'"
                    class="btn btn-warning btn-sm"
                    @click="restoreBrand(index)"
                  >Restore</a>
                </td>

              </tr>
            </tbody>

            <tfoot>
              <tr>
                <td>
                  <input
                    type="text"
                    class="form-control form-control-lg"
                    name="brand"
                    v-model="newBrand"
                    placeholder="Enter a brand name..."
                  >
                </td>
                <td>
                  <a
                    class="btn btn-success btn-sm"
                    @click="addBrand($event)"
                  >Add</a>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p align="right">
          <button
            type="button"
            name="submit"
            class="btn btn-primary"
            @click="saveBrandsList"
          >
            <i
              class="fa fa-pencil-square-o"
              aria-hidden="true"
            /> Save
          </button>
        </p>
      </div>

    </div>
  </layout>
</template>
<script>
import Layout from './Layout';

export default {
    name: 'RateLevelBrands',
    components: {
        Layout,
    },
    props: {
        brand: {
            type: Object,
            required: true,
        },
        user_id: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            newBrand: "",
            brandList: [],
            flashIcon: "fa-check-circle",
            flashMessage: "",
            flashMessageClass: "alert-warning",
            dataIsLoading: true
        };
    },
    computed: {
        csrf_token() {
            return window.csrf_token;
        },
        hasFlashMessage() {
          return this.flashMessage.trim() != "";
        }
    },
    mounted() {
      
    },
    created() {
      this.loadBrandsList();
    },
    methods: {
      /*
       * Clears flash messages.
       */
      clearMessages() {
        this.flashIcon = "fa-check-circle";
        this.flashMessage = "";
        this.flashMessageClass = "alert-warning";
      },
      /*
       * Shows a flash message.
       */
      showMessage(msg, type) {
        // set defaults, for 'success' type and in case of invalid 'type' values.
        this.flashIcon = "fa-check-circle";
        this.flashMessageClass = "alert-success";

        if(type === "warning") {
          this.flashIcon = "fa-exclamation-triangle";
          this.flashMessageClass = "alert-warning";

        } else if(type === "error") {
          this.flashIcon = "fa-exclamation-circle";
          this.flashMessageClass = "alert-danger";
        }

        this.flashMessage = msg;
      },
      /* 
       * Adds a brand to the list
       */
      addBrand() {
        this.clearMessages();

        // Reformat new brand to an object
        var brand = {
          name: this.newBrand.trim(),
          originalTag: "new-brand",
          tag: "new-brand"
        };

        console.log("New Brand: ");
        console.log(brand);

        // Does item already exist?
        if(this.isBrandInList(brand)) {
          return ;
        }
        
        // Not in list, add to array
        this.brandList.brand_names.push(brand);

        // Alphabetize list
        this.brandList.brand_names.sort((a, b) => (a.name.toLowerCase() > b.name.toLowerCase()) ? 1: - 1);

        this.clearInput();
        
        console.log("Brands after ADD operation:");
        console.log(this.brandList);
      },
      /* 
       * Deletes a brand from the list. The item doesn't actually get removed, but flagged. 
       * It's up to the back-end code to make sure the item is not saved in the resulting JSON object.
       */
      deleteBrand(index) {
        this.clearMessages();

        this.brandList.brand_names[index].tag = "deleted-brand"
        this.$forceUpdate(); // Refresh Vue components

        console.log("Brands after DELETE operation:");
        console.log(this.brandList);        
      },
      /*
       * Restores a deleted brand. This unflags the selected item as 'delete' to its previous tag.
       */
      restoreBrand(index) {
        this.clearMessages();

        this.brandList.brand_names[index].tag = this.brandList.brand_names[index].originalTag;
        this.$forceUpdate(); // Refresh Vue components

        console.log("Brands after RESTORE operation:");
        console.log(this.brandList);
      },
      /*
       * Loads the rate-level brands list
       */
      loadBrandsList() {
        this.dataIsLoading = true;
        console.log("RateLevelBrands::loadBrandsList()...");

        this.clearMessages();

        axios.post('/api/rate_level_brands/load', {
          brand_id: this.brand.id
        })
          .then((response) => {
            console.log("Brand list lookup result:");
            console.log(response);

            if(response.data && response.data.data) {
              this.brandList = response.data.data;
              console.log(this.brandList);

              console.log("Formatting brands list...");
              // Reformat brands from simple array to [{"name":"","originalTag":"","tag":""}]
              var formattedBrands = [];
              var brands = JSON.parse(this.brandList.brand_names);

              brands.forEach(function(brand) {
                formattedBrands.push({
                  name: brand,
                  originalTag: "existing-brand",
                  tag: "existing-brand"
                });
              });

              this.brandList.brand_names = formattedBrands;
              console.log(this.brandList.brand_names);              
            }

            this.dataIsLoading = false;
          })
          .catch(console.log);
      },
      /* 
       * Save the brand lis to DB. This is an async operation.
       */
      saveBrandsList() {
        this.clearMessages();

        console.log("Brands that will be saved:");
        console.log(this.brandList);

        // Check if anything was actually changed
        var hasChanges = false;
        for(const brand of this.brandList.brand_names) {
          if(brand.tag.toLowerCase() == 'deleted-brand' || brand.tag.toLowerCase() == 'new-brand') {
            hasChanges = true;
            break;
          }
        }

        if(!hasChanges) {
          this.showMessage("No changes detected. Database was not updated.", "warning");
          return;
        }

        // Changes exist. Post updated list to DB.
        // We'll posted the formatted brandList.brand_names array. Server-side is responsible for parsing the array and ignoring 'deleted' items.
        axios.post('/api/rate_level_brands/save', {
          'brand': this.brand,
          'brandsList': this.brandList,
          'userId': this.user_id
        })
          .then((response) => {
            // Refresh the view with a clean list.
            this.loadBrandsList();

            this.showMessage("The list was successfully saved!", "success");
          })
          .catch(console.log);
      },
      /* 
       * Object comparasion against array to determine if brand name is already in the list.
       */
      isBrandInList(obj) {
        for(const brand of this.brandList.brand_names) {
          if(brand.name.toLowerCase() == obj.name.toLowerCase()) {
            this.showMessage("Brand name '" + obj.name + "' is already in the list.", "warning");
            return true;
          }
        }
        return false;
      },
      /* 
       * Clears the 'Add Brand' input box 
       */
      clearInput() {
        this.newBrand = "";
      }
    },
};
</script>
<style scoped>
  .existing-brand {
    color: black;
    background-color: white;
    font-weight: bold;
  }

  .new-brand {
    color: green;
    border-color: green;
    background-color: #bbedc2;
    font-weight: bold;
  }
  .deleted-brand {
    color:red;
    border-color: red;
    background-color: #fdb9c3;
    font-weight: bold;
  }
</style>
