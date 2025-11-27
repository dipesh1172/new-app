<template>
    <div>
        <Breadcrumb
            :items="[
                {name: 'Home', url: '/'},
                {name: 'Motion'},
                {name: 'Skills', active: true}
            ]"
        />

        <div class="container-fluid mt-5">
            <div class="tab-content mb-4">

                <div class="animated fadeIn">

                    <!-- CONTENTS CARD -->
                    <div class="card mb-0">

                        <!-- CARD HEADER -->
                        <div class="card-header skills-header">

                            <!-- CARD HEADER TEXT -->
                            <i class="fa fa-th-large mt-3" /> Skills

                            <!-- ADD SKILL BUTTON -->
                            <a :href="createUrl" class="btn btn-success mr-0 mt-1 pull-right">
                                <i class="fa fa-plus" aria-hidden="true" /> Add Skill
                            </a>

                            <!-- SEARCH BUTTON -->
                            <button class="btn btn-primary pull-right mt-1 mr-2" @click="searchData">
                                <i class="fa fa-search" />
                            </button>

                            <!-- SEARCH BOX -->
                            <custom-input
                                :value="searchValue"
                                placeholder="Search"
                                name="search"
                                class="pull-right m-0 mt-1"
                                @onChange="updateSearchValue"
                                @onKeyUpEnter="searchData"
                            />
                        </div>

                        <!-- CARD BODY -->
                        <div class="card-body p-0">
                            <!-- DISPLAY MESSAGE FROM LAST HTTP OPERATION? -->
                            <div v-if="hasFlashMessage" class="alert alert-success">
                                <span class="fa fa-check-circle" /> <em>{{ flashMessage }}</em>
                            </div>

                            <!-- RESULTS TABLE -->
                            <custom-table
                                :headers="headers"
                                :data-grid="this.skills"
                                :data-is-loaded="dataIsLoaded"
                                show-action-buttons
                                has-action-buttons
                                empty-table-message="No skills were found. Create one."
                                class=""
                                :no-bottom-padding="numberPages <= 1"
                                @sortedByColumn="sortData"
                            />
                            
                            <!-- RESULTS PAGE CONTROLS-->
                            <pagination
                                v-if="dataIsLoaded"
                                :active-page="activePage"
                                :number-pages="numberPages"
                                class="ml-auto mr-auto mb-0"
                                @onSelectPage="selectPage"
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
import CustomInput from 'components/CustomInput';
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';

const NO_SORTED = '';
const BASE_URL = '/motion/skills'; // Base URL for dipslaying the page
const LIST_URL = BASE_URL + '/list';       // Base URL for fetching data

export default {
    name: 'MotionSkills',
    components: {
        Breadcrumb,
        CustomInput,
        CustomTable,
        Pagination
    },
    props: {
        createUrl: {
            type: String,
            required: true
        },
        searchParameter: {
            type: String,
            default: ''
        },
        columnParameter: {
            type: String,
            default: ''
        },
        directionParameter: {
            type: String,
            default: ''
        },   
        pageParameter: {
            type: String,
            default: ''
        },
        hasFlashMessage: {
            type: Boolean,
            default: false
        },
        flashMessage: {
            type: String,
            default: null
        },
    },
    data() {
        return {
            skills: [],
            headers: [{
                label: 'Name',
                key: 'name',
                serviceKey: 'name',
                width: '70%',
                sorted: NO_SORTED
            }, {
                label: 'Language',
                key: 'language',
                serviceKey: 'language',
                width: '15%',
                sorted: NO_SORTED
            }, {
                label: 'Motion DNIS',
                key: 'dnis',
                serviceKey: 'dnis',
                width: '15%'
            }],
            searchValue: this.searchParameter,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1
        };
    },
    computed: {
        dataGrid() {
            return this.skills;
        }
    },
    mounted() {
        this.init();
    },
    methods: {
        init() {
            const searchParam = this.searchParameter ? `&search=${this.searchParameter}` : '';
            const sortParams  = !!this.columnParameter && !!this.directionParameter
                ? `$column=${this.columnParameter}&direction=${this.directionParameter}` : '';
            const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';

            if(!!this.columnParameter && !!this.directionParameter) {
                const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
                this.headers[sortHeaderIndex].sorted = this.directionParameter;
            }

            this.fetch(BASE_URL, `?${pageParam}${searchParam}${sortParams}`);
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `${BASE_URL}?column=${serviceKey}
                    &direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}`;
        },
        searchData() {
            window.location.href = `${BASE_URL}?${this.getSortParams()}${this.getSearchParams()}`;
        },
        selectPage(page) {
            window.location.href = `${BASE_URL}?page=${page}${this.getSortParams()}${this.getSearchParams()}`;
        },
        getSearchParams() {
            return this.searchValue ? `&search=${this.searchValue}` : '';
        },
        getSortParams() {
            return !!this.columnParameter && !!this.directionParameter
                ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },

        // Formats each fetch result item for display
        denormalizeSkills({data: skillsData}) {

            const skillsResult = skillsData.map((skill) => {
                return {
                    id:       skill.id,
                    name:     skill.name,
                    language: skill.language,
                    dnis:     skill.dnis,
                    buttons: [{
                        type: 'edit',
                        url: `${BASE_URL}/${skill.id}/edit`
                    }, {
                        // type: 'delete',
                        // type: 'status',
                        type: 'custom',
                        label: 'Delete',
                        icon: 'trash',
                        classNames: 'btn-danger',
                        // url: `${BASE_URL}/${skill.id}/destroy`,
                        url: '#',
                        // url: '#',
                        // onClick: this.deleteSkill.bind(skill.id),
                        onClick: (e) => {
                            this.deleteSkill(e, skill.id)
                        },
                        // messageAlert: 'Are you sure you want to delete this skill?'
                    }]
                };
            });

            return skillsResult;
        },

        // Format fetch result data for display
        renderSkills(skillsData) {
            this.skills = this.denormalizeSkills(skillsData);

            this.dataIsLoaded = true; // Stops the spinner in the results table
            this.activePage   = skillsData.current_page;
            this.numberPages  = skillsData.last_page;
        },

        // Fetch the Skills list from database
        async fetch(baseUrl, params) {
            this.dataIsLoaded = false; // Starts the spinner in the results table

            // When page is first accessed, there are no search params, so checking for ? will let 
            // us know if this is an initial load, or a reload due to sorting or search.
            // const isInitialLoad = window.location.href.indexOf('?') === -1;

            let skills = [];

            try {
                skills = await axios.get(`${LIST_URL}${params}`);

                console.log("Skills", skills);
                this.renderSkills(skills.data);
                return skills;

            } catch (err) {
                return err;
            }
        },

        // Delete the selected skill
        async deleteSkill(event, id) {

            try {
                const c = confirm('Are you sure you want to delete this skill?');

                if(!c) {
                    event.preventDefault();
                    return;
                }
                
                // console.log("YOU ARE IN THE SKILLS.DELETE FUNCTION");
                console.log("ID to Delete: ", id);
                await axios.delete(`${BASE_URL}/${id}`);

                window.location.href = BASE_URL;

            } catch (err) {
                console.log(err.message);
                return err;
            }
        }
    }
};
</script>

<style lang="scss" scoped>
    .skills-header {
        clear: none;
        float: left;
        padding-top: 0;
        padding-bottom: 5px;
    }
</style>
