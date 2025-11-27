<template>
    <div class="contacts-list">
        <h3>Contacts</h3>
        <ul>
            <li v-for="contact in sortedContacts" :key="contact.id" @click="selectContact(contact)" :class="{ 'selected': contact == selectedContact }">
                <div class="contact">
                    <p class="name">{{ contact.first_name }} {{ contact.last_name }}</p>
                    <p class="position">{{ contact.role }}</p>
                    <p class="location">{{ contact.call_center }}</p>
                    <span class="unread" v-if="contact.unread">{{ contact.unread }}</span>
                </div>
            </li>
        </ul>
    </div>
</template>

<script>
    export default {
        props: {
            contacts: {
                type: Array,
                default: []
            }
        },
        data() {
            return {
                selectedContact: this.contacts.length ? this.contacts[0] : null
            };
        },
        methods: {
            selectContact(contact) {
                this.selectedContact = contact;
                this.$emit('selected', contact);
            }
        },
        computed: {
            sortedContacts() {
                return _.sortBy(this.contacts, [(contact) => {
                    if (contact == this.selectedContact) {
                        return Infinity;
                    }
                    return contact.unread;
                }]).reverse();
            }
        }
    }
</script>

<style lang="scss" scoped>
.contacts-list {
    flex: 3;
    max-height: 400px;
    overflow-x: hidden;
    overflow-y: scroll;
    border-left: 1px solid lightgrey;
    border-bottom: 1px dotted black;

    h3 {
        text-align: center;
    }

    ul {
        list-style-type: none;
        padding-left: 0;

        li {
            display: block;
            padding: 2px;
            border-bottom: 1px solid lightgrey;
            height: 50px;
            position: relative;
            cursor: pointer;

            &.selected {
                background: lightblue;
            }

            span.unread {
                background: blue;
                color: white;
                position: absolute;
                right: 11px;
                top: 20px;
                font-weight: 700;
                min-width: 20px;
                justify-content: center;
                align-items: center;
                line-height: 20px;
                padding: 0 4px;
                border-radius: 8px;
            }

            .contact {
                font-size: 10px;
                overflow: hidden;
                flex: 3;
                font-size: 10px;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                font-size: 10px;
                overflow: hidden;
                justify-content: center;

                p {
                    margin: 0;

                    &.name {
                        font-weight: bold;
                    }
                }
            }
        }
    }
}
</style>