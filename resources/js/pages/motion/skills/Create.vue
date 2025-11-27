<template>
    <div>
        <Breadcrumb
            :items="[
                {name: 'Home', url: '/'},
                {name: 'Motion'},
                {name: 'Skills', url: `${skillsListUrl}`},
                {name: `Create`, active: true}
            ]"
        />

        <div class="container-fluid mt-5">
            <div class="animated fadeIn">

                <!-- CONTENTS CARD -->
                <div class="card">

                    <!-- CARD HEADER -->
                    <div class="card-header">

                        <!-- CARD HEADER TEXT -->
                        <i class="fa fa-th-large" /> Create Skill
                    </div>

                    <!-- CARD BODY -->
                    <div class="card-body">
                        <ValidationObserver ref="formHandler">

                            <!-- THE FORM -->
                            <form
                                ref="formObject"
                                :action="postUrl"
                                method="POST"
                                autocomplete="off"
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
                                                            v-model="values.name"
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
                                                            v-model="values.language_id"
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
                                            <div class="col-md-6">
                                                <div class="form-group">                                                
                                                    <ValidationProvider 
                                                        v-slot="{ errors }" 
                                                        name="dnis" 
                                                        rules="required|min:1|max:36"
                                                    >
                                                        <label for="name">DNIS</label>
                                                        <input
                                                            v-model="values.dnis"
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

const BASE_URL   = '/motion/skills';         // GET URL for displaying skills list page
const CREATE_URL = '/motion/skills/store';   // POST URL for creating new record

export default {
    name: 'CreateMotionSkill',
    components: {        
        Breadcrumb,
        ValidationProvider,
        ValidationObserver,        
    },
    props: {
        languages: {
            type: Array,
            default: () => []
        },
        initialValues: {
            type: Object,
            default: () => ({})
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
        console.log('initialValues', this.initialValues);
        const values = {};

        const defaultValues = {
            name: '',
            language_id: 1,
            dnis: null
        };

        Object.keys(defaultValues).forEach((key) => {
            if(key in this.initialValues) {
                values[key] = this.initialValues[key] == null ? '' : this.initialValues[key];
            } else {
                values[key] = defaultValues[key];
            }
        });

        return {
            values
        };
    },
    computed: {
        csrf_token() {
            return csrf_token;
        },
        postUrl() {
            return CREATE_URL;
        },
        skillsListUrl() {
            return BASE_URL;
        }
    },
    methods: {
        onFormSubmit() {
            this.$refs.formHandler.validate().then((success) => {
                if(!success) {
                    return false;
                }

                this.$refs.formObject.submit();
            });
        }
    }
};
</script>
