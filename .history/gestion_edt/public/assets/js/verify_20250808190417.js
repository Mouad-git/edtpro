document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const email = urlParams.get('email');
    const feedbackEl = document.getElementById('feedback');

    // --- CORRECTION CLÉ ---
    // On vérifie immédiatement si l'email est présent dans l'URL.
    if (!email) {
        // Si l'email est manquant, on ne peut pas continuer. On affiche une erreur et on bloque.
        feedbackEl.className = 'feedback-error';
        feedbackEl.textContent = "Erreur : L'adresse e-mail de vérification est manquante. Veuillez recommencer l'inscription.";
        document.getElementById('verifyForm').style.display = 'none'; // On cache le formulaire
        return; // On arrête le script ici.
    }

    // Si l'email est présent, on l'affiche et on remplit le champ caché.
    document.getElementById('user-email').textContent = email;
    document.getElementById('hidden-email').value = email;

    document.getElementById('verifyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        feedbackEl.textContent = '';
        feedbackEl.className = '';

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        // Le champ 'email' est maintenant inclus grâce à l'input caché

        fetch('../api/auth/verify_code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                feedbackEl.className = 'feedback-success';
                feedbackEl.textContent = result.message + " Redirection vers la page de connexion...";
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 3000);
            } else {
                feedbackEl.className = 'feedback-error';
                feedbackEl.textContent = result.message || "Une erreur est survenue.";
            }
        })
        .catch(error => {
            feedbackEl.className = 'feedback-error';
            feedbackEl.textContent = "Erreur de communication avec le serveur.";
        });
    });
});