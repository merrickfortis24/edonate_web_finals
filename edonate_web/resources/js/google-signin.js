import { getApps, initializeApp } from 'firebase/app';
import {
    browserPopupRedirectResolver,
    GoogleAuthProvider,
    initializeAuth,
    inMemoryPersistence,
    signInWithPopup,
    signOut,
} from 'firebase/auth';

const appName = 'edonate-google-sign-in';
let auth = null;

async function prepare(config) {
    if (!auth) {
        const existingApp = getApps().find((app) => app.name === appName);
        const app = existingApp || initializeApp(config, appName);

        auth = initializeAuth(app, {
            persistence: inMemoryPersistence,
            popupRedirectResolver: undefined,
        });
    }

    await auth.authStateReady();
    return auth;
}

function signInWithGoogle() {
    if (!auth) {
        throw new Error('Google sign-in is not ready. Refresh the page and try again.');
    }

    const provider = new GoogleAuthProvider();
    provider.setCustomParameters({ prompt: 'select_account' });

    return signInWithPopup(auth, provider, browserPopupRedirectResolver);
}

function signOutCurrentUser() {
    return auth ? signOut(auth) : Promise.resolve();
}

window.eDonateGoogle = { prepare, signInWithGoogle, signOutCurrentUser };
window.dispatchEvent(new Event('edonate-google-sdk-ready'));
