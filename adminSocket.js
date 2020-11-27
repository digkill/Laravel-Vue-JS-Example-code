import { mapActions } from "vuex";
import Vue from 'vue';
import Toasted from '@gitlab/vue-toasted';
Vue.use(Toasted);

export default {
    created () {
        this.listenChannels();
    },
    methods: {
        ...mapActions({
            fetchTaskCount: "adminNotify/fetchTaskCount",
            fetchRequestCount: "adminNotify/fetchRequestCount",
        }),
        listenChannels () {
            console.log("listenChannels", window.App.auth_user.id);
            Echo.private(`admin.` + window.App.auth_user.id)
                .listen(".task.create", e => {
                    console.log(e);
                    this.fetchTaskCount();
                    this.showNotify(e, `Задача "${e.task.task_title}" создана`, 'success', 'fa-check');
                })
                .listen(".task.update", e => {
                    console.log(e);
                    this.showNotify(e, `Задача "${e.task.task_title}" обновлена`, 'info', 'fa-repeat');
                })
                .listen(".task.delete", e => {
                    console.log(e);
                    this.fetchTaskCount();
                    this.showNotify(e, `Задача "${e.task.task_title}" удалена`, 'error', 'fa-times');
                })
                .listen(".lead.create", e => {
                    console.log(e);
                    this.fetchRequestCount();
                    this.showNotify(e, `Новый лид "${e.lead.name} - источник ${e.source}"`, 'info', 'fa-plus');
                })
        },
        async showNotify (data, title, type, icon) {
            this.$toasted.show(title, {
                duration: 8000,
                keepOnHover: true,
                type: type,
                iconPack: 'fontawesome',
                icon: icon,
                action: {
                    text: 'Перейти',
                    onClick: (e, toastObject) => {
                        if (window.location.pathname == data.url) return;
                        window.location.assign(data.url);
                    }
                },

            })
        }
    }
};
