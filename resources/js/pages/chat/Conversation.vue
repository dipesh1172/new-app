<template>
    <div class="conversation">
        <h1>{{ contact ? contact.first_name : "Select a Contact"}} {{ contact ? contact.last_name : "" }}</h1>
        <MessagesFeed :contact="contact" :messages="messages" />
        <MessageComposer @send="sendMessage" />
    </div>
</template>

<script>

    import MessagesFeed from './MessagesFeed.vue';
    import MessageComposer from './MessageComposer.vue';

    export default {
        props: {
            contact: {
                type: Object,
                default: null
            },
            messages: {
                type: Array,
                default: []
            }
        },
        methods: {
            sendMessage(text) {
                if (!this.contact) {
                    return;
                }

                axios.post('/conversation/sendMessage', {
                    to_id: this.contact.id,
                    content: text
                }).then((response) => {
                    this.$emit('new', response.data);
                });
            }
        },
        components: {
            MessagesFeed,
            MessageComposer
        }
    }
</script>

<style lang="scss" scoped>
.conversation {
    flex: 3;
    display: flex;
    flex-direction: column;
    justify-content: space-between;

    h1 {
        font-size: 16px;
        padding: 10px;
        margin: 0;
        text-align: center;
        border-bottom: 1px dashed lightgrey;
    }
}
</style>