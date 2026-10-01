// The confirmation must repeat the password: checked before sending, and again
// by the server. An invalid field blocks the submit with this message.
const motDePasse = document.getElementById('password');
const confirmation = document.getElementById('password_confirmation');

if (motDePasse && confirmation) {
    const comparer = () => {
        confirmation.setCustomValidity(
            confirmation.value === motDePasse.value ? '' : 'The two passwords differ.');
    };
    motDePasse.addEventListener('input', comparer);
    confirmation.addEventListener('input', comparer);
}
