<template>
    <div>
        <Breadcrumb
            :items="[
                {name: 'Home', url: '/'},
                {name: 'Motion'},
                {name: 'Skill Maps', url: `${skillMapsUrl}`},
                {name: 'Edit', active: true}
            ]"
        />

        <div class="container-fluid mt-5">
            <div class="animated fadeIn">

                <!-- CONTENTS CARD -->
                <div class="card">

                    <!-- CARD HEADER -->
                    <div class="card-header">

                        <!-- CARD HEADER TEXT -->
                        <i class="fa fa-th-large" /> Edit Skill
                    </div>

                    <!-- CARD BODY -->
                    <div class="card-body">
                        <ValidationObserver ref="formHandler">

                            <!-- THE FORM -->
                            <form
                                ref="formObject"
                                method="POST"
                                :action="postUrl"
                                autocomplete="off"
                            >
                                <!-- ??? -->
                                <input
                                    name="_method"
                                    type="hidden"
                                    value="PUT"
                                >

                                <!-- CSRF TOKEN -->
                                <input 
                                    type="hidden"
                                    name="_token"
                                    :value="csrf_token"
                                >

                                <!-- ENABLE GRID SYSTEM FOR CARD CONTENTS BY DEFINING A ROW AND COL-12 -->
                                <div class="row">
                                    <div class="col-md-12">

                                        <!-- DISPLAY MESSAGE FROM LAST HTTP OPERATION? -->
                                        <div v-show="flashMessage" class="alert alert-success">
                                            <span class="fa fa-check-circle" /><em>{{ flashMessage }}</em>
                                        </div>

                                        <!-- DISPLAY ERROR MESSAGE(S)? -->
                                        <div v-show="errors.length" class="alert alert-danger">
                                            <li v-for="(error, index) in errors" :key="index">
                                                {{ error }}
                                            </li>
                                        </div>

                                        <!-- BRAND -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">                                                
                                                    <ValidationProvider 
                                                        v-slot="{ errors }" 
                                                        name="brand_id" 
                                                        rules="required|min:1"
                                                    >
                                                        <label for="language">Select a Brand</label>
                                                        <select
                                                            v-model="skillMap.brand_id"
                                                            name="brand_id"
                                                            class="form-control form-control-lg"
                                                            @change="onBrandNameChange"
                                                        >
                                                            <option value="">Select a Brand</option>
                                                            <option
                                                                v-for="brand in brands"
                                                                :key="brand.id"
                                                                :value="brand.id"
                                                            >
                                                                {{ brand.name }}
                                                            </option>
                                                        </select>
                                                        <span class="text-danger">{{ errors[0] }}</span>
                                                    </ValidationProvider>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- DNIS -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">                                                
                                                    <ValidationProvider 
                                                        v-slot="{ errors }" 
                                                        name="dnis_id" 
                                                        rules="required|min:1"
                                                    >
                                                        <label for="dnis">Select a DNIS</label>
                                                        <select
                                                            v-model="skillMap.dnis_id"
                                                            name="dnis_id"
                                                            class="form-control form-control-lg"
                                                        >
                                                            <option value="">Select a DNIS</option>
                                                            <option
                                                                v-for="d in dnisList"
                                                                :key="d.id"
                                                                :value="d.id"
                                                            >
                                                                {{ d.dnis }}
                                                            </option>
                                                        </select>
                                                        <span class="text-danger">{{ errors[0] }}</span>
                                                    </ValidationProvider>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- LANGUAGE -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">                                                
                                                    <ValidationProvider 
                                                        v-slot="{ errors }" 
                                                        name="language_id" 
                                                        rules="required|min:1"
                                                    >
                                                        <label for="language">Select a Language</label>
                                                        <select
                                                            v-model="skillMap.language_id"
                                                            name="language_id"
                                                            class="form-control form-control-lg"
                                                        >
                                                            <option value="">Select a Language</option>
                                                            <option
                                                                v-for="language in languages"
                                                                :key="language.id"
                                                                :value="language.id"
                                                            >
                                                                {{ language.language }}
                                                            </option>
                                                        </select>
                                                        <span class="text-danger">{{ errors[0] }}</span>
                                                    </ValidationProvider>
                                                </div>
                                            </div>
                                        </div>

                                        <hr/>

                                        <!-- MOTION SKILL -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">                                                
                                                    <ValidationProvider 
                                                        v-slot="{ errors }" 
                                                        name="motion_skills_id" 
                                                        rules="required|min:1"
                                                    >
                                                        <label for="motion_skill">Select a Motion Skill</label>
                                                        <select
                                                            v-model="skillMap.motion_skills_id"
                                                            name="motion_skills_id"
                                                            class="form-control form-control-lg"
                                                        >
                                                            <option value=null>Select a Motion Skill</option>
                                                            <option
                                                                v-for="skill in motionSkills"
                                                                :key="skill.id"
                                                                :value="skill.id"
                                                            >
                                                                {{ skill.name }} ({{ skill.dnis }})
                                                            </option>
                                                        </select>
                                                        <span class="text-danger">{{ errors[0] }}</span>
                                                    </ValidationProvider>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SUBMIT BUTTON -->
                                        <div class="row">
                                            <div class="col-md-10">
                                                <!-- SPACER TO PUSH BUTTON TO RIGHT SIDE OF FORM -->
                                            </div>
                                            <div class="col-md-2">
                                                <br/>
                                                <button
                                                    type="button"
                                                    class="btn btn-primary pull-right"
                                                    @click="onFormSubmit"
                                                >
                                                    <i
                                                        class="fa fa-floppy-o"
                                                        aria-hidden="true"
                                                    /> 
                                                    Submit
                                                </button>
                                            </div>
                                        </div>

                                    </div>
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
import Breadcrumb from 'components/Breadcrumb';
import {
    ValidationProvider,
    ValidationObserver,
} from 'vee-validate/dist/vee-validate.full';

const BASE_URL   = '/motion/skill_maps'; // GET URL for displaying skills list page, and also base URL for update URL

export default {
    name: 'EditMotionSkillMap',
    components: {        
        Breadcrumb,
        ValidationProvider,
        ValidationObserver,        
    },
    props: {
        skillMap: {
            type: Object,
            default: () => ({})
        },
        brands: {
            type: Array,
            default: () => []
        },
        dnis: {
            type: Array,
            default: () => []
        },
        languages: {
            type: Array,
            default: () => []
        },
        motionSkills: {
            type: Array,
            default: () => []
        },
        flashMessage: {
            type: String,
            default: null
        },
        errors: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            dnisList: [] // List of DNIS values we can selected from. The list changes based on currently selected brand.
        };
    },
    mounted() {
        this.setDnisList(this.skillMap.brand_id);
    },
    computed: {
        csrf_token() {
            return csrf_token;
        },
        postUrl() {
            return `${BASE_URL}/${this.skillMap.id}`;
        },
        skillMapsUrl() {
            return BASE_URL;
        }
    },
    methods: {
        // Event handler for form submission.
        onFormSubmit() {

            // Only submit the form if validation passes.
            this.$refs.formHandler.validate().then((success) => {

                // Validation failed, do nothing.
                if(!success) {
                    return false;
                }

                // All good, submit the form.
                this.$refs.formObject.submit();
            });
        },

        // Event handler for brand selection change.
        onBrandNameChange() {
            console.log("Brand changed, to:", this.skillMap.brand_id);

            // Active DNIS list is about to change, so if a DNIS is currently selected, it likely will no longer be valid.
            // Clear selected DNIS
            this.skillMap.dnis_id = null;

            // Refilter the DNIS list for the currently selected brand, and set as active list of DNISes we can select from.
            this.setDnisList(this.skillMap.brand_id);
        },

        // Filter selectable DNIS list to those assigned to selected brand
        setDnisList(brandId) {

            if(!brandId) {
                this.dnisList = [];
                return;
            }

            this.dnisList = this.dnis.filter((dnis) => {
                return dnis.brand_id === this.skillMap.brand_id;
            });
        }        
    }
};
</script>
