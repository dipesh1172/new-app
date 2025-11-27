<template>
    <div>
        <Breadcrumb
            :items="[
                {name: 'Home', url: '/'},
                {name: 'Motion'},
                {name: 'Skills', url: `${skillsListUrl}`},
                {name: `Edit`, active: true}
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

                                        <!-- SKILL NAME -->
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">                                                
                                                    <ValidationProvider 
                                                        v-slot="{ errors }" 
                                                        name="name" 
                                                        rules="required|min:1|max:200"
                                                    >
                                                        <label for="name">Skill Name</label>
                                                        <input
                                                            v-model="skill.name"
                                                            type="text"
                                                            name="name"
                                                            class="form-control form-control-lg"
                                                            placeholder="Enter a Skill Name"
                                                        >
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
                                                            v-model="skill.language_id"
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

                                        <!-- DNIS -->
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">                                                
                                                    <ValidationProvider 
                                                        v-slot="{ errors }" 
                                                        name="dnis" 
                                                        rules="required|min:1|max:36"
                                                    >
                                                        <label for="name">DNIS</label>
                                                        <input
                                                            v-model="skill.dnis"
                                                            type="text"
                                                            name="dnis"
                                                            class="form-control form-control-lg"
                                                            placeholder="Enter a DNIS (+12223334444)"
                                                        >
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

const BASE_URL   = '/motion/skills'; // GET URL for displaying skills list page, and also base URL for update URL

export default {
    name: 'EditMotionSkill',
    components: {        
        Breadcrumb,
        ValidationProvider,
        ValidationObserver,        
    },
    props: {
        skill: {
            type: Object,
            default: () => ({})
        },
        languages: {
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
        };
    },
    computed: {
        csrf_token() {
            return csrf_token;
        },
        postUrl() {
            return `${BASE_URL}/${this.skill.id}`;
        },
        skillsListUrl() {
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
        }
    }
};
</script>
