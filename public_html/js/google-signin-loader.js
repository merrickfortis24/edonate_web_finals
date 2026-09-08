// Requested sign-in functionality, never loaded passively on a login page.
(() => {
    'use strict';
    let loading;
    function script(name) {
        return new Promise((resolve, reject) => {
            const element = document.createElement('script');
            element.src = `https://www.gstatic.com/firebasejs/10.12.5/${name}-compat.js`;
            element.referrerPolicy = 'no-referrer';
            element.onload = resolve;
            element.onerror = () => { element.remove(); reject(new Error('Google sign-in could not be loaded. Please retry.')); };
            document.head.append(element);
        });
    }
    window.eDonateGoogle = {
        prepare(config) {
            if (!loading) loading = (async () => {
                if (!window.firebase) await script('firebase-app');
                if (!window.firebase.auth) await script('firebase-auth');
                if (!firebase.apps.length) firebase.initializeApp(config);
                await firebase.auth().setPersistence(firebase.auth.Auth.Persistence.NONE);
            })().catch(error => { loading = null; throw error; });
            return loading;
        }
    };
})();
