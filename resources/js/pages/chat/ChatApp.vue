<template>
    <div class="chat-app">
        <ContactsList :contacts="contacts" @selected="startConversationWith" />
        <Conversation :contact="selectedContact" :messages="messages" @new="saveNewMessage" />
    </div>
</template>

<script>
    import Conversation from './Conversation.vue';
    import ContactsList from './ContactsList.vue';
    import Echo from 'laravel-echo';
    import Pusher from 'pusher-js'

    export default {
        props: {
            user: {
                type: Object,
                required: true
            }
        },
        data() {
            return {
                selectedContact: null,
                messages: [],
                contacts: []
            };
        },
        created() {
            console.log(process.env);

            window.Pusher = Pusher;
            window.Pusher.log = console.log;
            window.Echo = new Echo({
                broadcaster: 'pusher',
                key: process.env.MIX_PUSHER_APP_KEY,
                wsHost: process.env.MIX_PUSHER_HOST,
                wsPort: process.env.MIX_PUSHER_PORT,
                disableStats: true,
            });
            window.Echo.join('chat.focus')
            .here((users) => {
                console.log("currently online: ");
                users.forEach(function(user) {
                    console.log(user.name);
                });
            })
            .joining((user) => {
                console.log("joining: " + user.name);
            })
            .leaving((user) => {
                console.log("leaving: " + user.name);
            });
        },
        mounted() {
            window.Echo.private(`messages.${ this.user.id }`)
            .listen("NewMessage", (e) => {
                this.handleIncoming(e.message);
            });


            axios.get('/contacts')
                .then((response) => {
                    this.contacts = response.data;
                });
        },
        methods: {
            startConversationWith(contact) {
                this.updateUnreadCount(contact, true);

                axios.get(`/conversation/${contact.id}`)
                    .then((response) => {
                        console.log(response.data);
                        this.messages = response.data;
                        this.selectedContact = contact;
                    });
            },
            saveNewMessage(message) {
                this.messages.push(message);
            },
            handleIncoming(message) {
                if (this.selectedContact && message.from_id == this.selectedContact.id) {
                    this.saveNewMessage(message);
                    return;
                }

                document.getElementById("mail_icon").nextElementSibling.style.display = "block";
                document.getElementById("mail_icon").classList.add("has_message");

                this.updateUnreadCount(message.from, false);
            },
            updateUnreadCount(contact, reset) {
                this.contacts = this.contacts.map((single) => {
                    if (single.id != contact.id) {
                        return single;
                    }

                    if (reset) {
                        single.unread = 0;
                        document.getElementById("mail_icon").nextElementSibling.style.display = "none";
                        document.getElementById("mail_icon").classList.remove("has_message");
                    } else {
                        single.unread += 1;
                    }

                    return single;
                });
            }
        },
        components: {
            Conversation,
            ContactsList
        }
    }
</script>
<style scoped>
.chat-app {
    height: 100%;
    width: 250px;
    position: fixed;
    z-index: 1;
    top: 0;
    right: 0;
    background-color: #e4e5e6;
    overflow-x: hidden;
    padding-top: 50px;
    transition: 0.5ss;
    border: 1px solid black;
    display: flex;
    flex-direction: column;
    box-shadow: -5px 0px lightgrey;
  }

.card-body {
    padding: 0px !important;
}
</style>
<style>
.has_message {
    text-align: center;
    -webkit-animation: glow 1s ease-in-out infinite alternate;
    -moz-animation: glow 1s ease-in-out infinite alternate;
    animation: glow 1s ease-in-out infinite alternate;
}

@-webkit-keyframes glow {
    from {
        text-shadow: 0 0 10px #fff, 0 0 20px #fff, 0 0 30px rgb(0, 116, 200), 0 0 40px rgb(0, 116, 200), 0 0 50px rgb(0, 116, 200), 0 0 60px rgb(0, 116, 200), 0 0 70px rgb(0, 116, 200);
    }
    to {
        text-shadow: 0 0 20px #fff, 0 0 30px rgb(255, 127, 42), 0 0 40px rgb(255, 127, 42), 0 0 50px rgb(255, 127, 42), 0 0 60px rgb(255, 127, 42), 0 0 70px rgb(255, 127, 42), 0 0 80px rgb(255, 127, 42);
    }
}
</style>
