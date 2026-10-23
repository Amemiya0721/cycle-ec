
document.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('cookie-banner');
    const button = document.getElementById('cookie-acknowledge');

    if (!banner || !button) {
        return;
    }

    const storageKey = 'overhaul_cookie_notice_v1';

    try {
        if (localStorage.getItem(storageKey) === 'acknowledged') {
            return;
        }
    } catch (error) {
        console.warn('Cookie案内の保存状態を確認できませんでした。');
    }

    banner.hidden = false;

    button.addEventListener('click', () => {
        banner.hidden = true;

        try {
            localStorage.setItem(storageKey, 'acknowledged');
        } catch (error) {
            console.warn('Cookie案内の確認状態を保存できませんでした。');
        }
    });
});
