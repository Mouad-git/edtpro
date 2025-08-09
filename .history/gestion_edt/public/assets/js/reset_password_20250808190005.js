document.addEventListener('DOMContentLoaded', function() {
    const resetForm = document.getElementById('resetPasswordForm');
    const feedbackEl = document.getElementById('feedback');
    const loginLink = document.getElementById('login-link');
    
    // --- ÉTAPE 1 : Récupérer et valider le jeton depuis l'URL ---
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (!token) {
        resetForm.innerHTML = ''; // Vider le formulaire
        feedbackEl.className = 'feedback-error';
        feedbackEl.textContent = 'Erreur : Le lien de réinitialisation est invalide ou manquant.';
        loginLink.style.display = 'inline-block'; // Afficher le lien de retour
        return; // Bloquer l'exécution
    }
    // Si le jeton existe, on le stocke dans le champ caché du formulaire
    document.getElementById('token').value = token;

    // --- ÉTAPE 2 : Gérer la visibilité des mots de passe ---
    const passwordInput = document.getElementById("mot_de_passe"); 
const toggleIcon = document.getElementById("togglePassword");

toggleIcon.addEventListener("click", function () {
const isPassword = passwordInput.type === "password";
passwordInput.type = isPassword ? "text" : "password";
toggleIcon.src = isPassword
? "https://img.icons8.com/fluency-systems-regular/24/visible--v1.png"
: "https://img.icons8.com/fluency-systems-regular/24/invisible.png";
});

const confirmPasswordInput = document.getElementById("confirm_mot_de_passe");
const toggleConfirmIcon = document.getElementById("toggleConfirmPassword");

toggleConfirmIcon.addEventListener("click", function () {
const isPassword = confirmPasswordInput.type === "password";
confirmPasswordInput.type = isPassword ? "text" : "password";
toggleConfirmIcon.src = isPassword
? "https://img.icons8.com/fluency-systems-regular/24/visible--v1.png"
: "https://img.icons8.com/fluency-systems-regular/24/invisible.png";
});



    // --- ÉTAPE 3 : Gérer la soumission du formulaire ---
    resetForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const passwordErrorEl = document.getElementById('password-error');
        feedbackEl.textContent = '';
        passwordErrorEl.textContent = '';

        // Validation côté client pour une meilleure expérience utilisateur
        if (passwordInput.value !== confirmPasswordInput.value) {
            passwordErrorEl.textContent = "Les mots de passe ne correspondent pas.";
            return;
        }
        if (passwordInput.value.length < 6) {
            passwordErrorEl.textContent = "Le mot de passe doit faire au moins 6 caractères.";
            return;
        }

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        fetch('../api/auth/reset_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                feedbackEl.className = 'feedback-success';
                resetForm.style.display = 'none'; // Cacher le formulaire
                loginLink.style.display = 'inline-block'; // Afficher le lien de retour
                feedbackEl.textContent = result.message + " Vous pouvez maintenant vous connecter.";
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