<template>
    <div class="feed" ref="feed">
        <ul v-if="contact">
            <li v-for="message in messages" :class="`message${message.to_id == contact.id ? ' sent' : ' received'}`" :key="message.id">
                <div class="text">
                    {{ message.content }}
                </div>
                <span class="timestamp">
                    {{ message.created_at }}
                </span>
            </li>
        </ul>
    </div>
</template>

<script>
    export default {
        props: {
            contact: {
                type: Object
            },
            messages: {
                type: Array,
                required: true
            }
        },
        methods: {
            scrollToBottom() {
                setTimeout(() => {
                    this.$refs.feed.scrollTop = this.$refs.feed.scrollHeight - this.$refs.feed.clientHeight;
                }, 50);
            }
        },
        watch: {
            contact(contact) {
                this.scrollToBottom();
            },
            messages(messages) {
                this.scrollToBottom();
            }
        }
    }
</script>

<style lang="scss" scoped>
.feed {
    background: lightgrey;
    height: 100%;
    max-height: 470px;
    overflow: scroll;

    ul {
        list-style-type: none;
        padding: 5px;

        li {

            &.message {
                margin: 10px 0;
                width: 100%;

                .text {
                    max-width: 200px;
                    border-radius: 5px;
                    padding: 12px;
                    display: inline-block;
                }

                .timestamp {
                    color: lightgrey;
                    font-weight: lighter;
                }

                &.received {
                    text-align: right;

                    .text {
                        background: grey;
                    }
                }

                &.sent {
                    text-align: left;

                    .text {
                        background: lightblue;
                    }
                }
            }
        }
    }
}
</style>