document.addEventListener('DOMContentLoaded', async function() {
            
    // --- LOGIQUE DE CHARGEMENT DES ÉTABLISSEMENTS ---
    const complexeSelect = document.getElementById('complexeSelect');
    const etablissementSelect = document.getElementById('etablissementSelect');
    const hiddenEtablissementInput = document.getElementById('nom_etablissement');

    try {
        const response = await fetch('data/etablissements.json');
        if (!response.ok) throw new Error("Le fichier etablissements.json n'a pas pu être chargé.");
        const etablissementsParComplexe = await response.json();

        const complexes = Object.keys(etablissementsParComplexe).sort();
        complexes.forEach(complexe => {
            const option = new Option(complexe, complexe);
            complexeSelect.appendChild(option);
        });

        complexeSelect.addEventListener('change', function() {
            const selectedComplexe = this.value;
            etablissementSelect.innerHTML = '<option value="">-- Sélectionnez un établissement --</option>';
            hiddenEtablissementInput.value = '';

            document.getElementById('complexe').value = selectedComplexe;

            if (selectedComplexe) {
                etablissementSelect.disabled = false;
                etablissementsParComplexe[selectedComplexe].sort().forEach(etab => {
                    etablissementSelect.appendChild(new Option(etab, etab));
                });
            } else {
                etablissementSelect.disabled = true;
            }
        });
        
        etablissementSelect.addEventListener('change', function() {
            hiddenEtablissementInput.value = this.value;
        });

    } catch (error) {
        console.error("Erreur critique lors du chargement des établissements:", error);
        complexeSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        complexeSelect.disabled = true;
        etablissementSelect.disabled = true;
    }
    
    // --- NOUVELLE LOGIQUE POUR L'EMAIL FIXE ---
    const emailUsernameInput = document.getElementById('email_username');
    const emailHiddenInput = document.getElementById('email');

    if (emailUsernameInput && emailHiddenInput) {
        emailUsernameInput.addEventListener('input', function() {
            // Met à jour le champ caché avec l'identifiant + le domaine fixe
            emailHiddenInput.value = `${this.value}@gmail.com`;
        });
    }

    // --- GESTION DE LA VISIBILITÉ DU MOT DE PASSE ---
    const passwordInput = document.getElementById("mot_de_passe");
    const toggleIcon = document.getElementById("togglePasswordIcon");

    toggleIcon.addEventListener("click", function () {
        const isPassword = passwordInput.type === "password";
        passwordInput.type = isPassword ? "text" : "password";
        toggleIcon.src = isPassword
          ? "https://img.icons8.com/fluency-systems-regular/24/visible--v1.png"
          : "https://img.icons8.com/fluency-systems-regular/24/invisible.png";
    });

    const confirmPasswordInput = document.getElementById("confirm_mot_de_passe");
    const toggleConfirmIcon = document.getElementById("toggleConfirmPasswordIcon");

    toggleConfirmIcon.addEventListener("click", function () {
        const isPassword = confirmPasswordInput.type === "password";
        confirmPasswordInput.type = isPassword ? "text" : "password";
        toggleConfirmIcon.src = isPassword
            ? "https://img.icons8.com/fluency-systems-regular/24/visible--v1.png"
            : "https://img.icons8.com/fluency-systems-regular/24/invisible.png";
    });

    
    // --- GESTION DE LA SOUMISSION DU FORMULAIRE ---
    document.getElementById('registerForm').addEventListener('submit', function(e) {
e.preventDefault();

const feedbackEl = document.getElementById('feedback');
const passwordErrorEl = document.getElementById('password-error');
feedbackEl.textContent = '';
passwordErrorEl.textContent = '';

const password = document.getElementById('mot_de_passe').value;
const confirmPassword = document.getElementById('confirm_mot_de_passe').value;

// --- NOUVELLE VÉRIFICATION AJOUTÉE ---
const nomEtablissement = document.getElementById('nom_etablissement').value;
if (!nomEtablissement) {
feedbackEl.className = 'feedback-error';
feedbackEl.textContent = "Veuillez sélectionner un complexe ET un établissement.";
return; // On arrête l'envoi
}
// ------------------------------------

if (password !== confirmPassword) {
passwordErrorEl.textContent = "Les mots de passe ne correspondent pas.";
return;
}
if (password.length < 6) {
passwordErrorEl.textContent = "Le mot de passe doit contenir au moins 6 caractères.";
return;
}

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        // On supprime les champs inutiles pour le serveur
        delete data.confirm_mot_de_passe;
        delete data.email_username; // IMPORTANT : On retire l'identifiant partiel

        fetch('../api/auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success && result.action === 'verify') {
                window.location.href = `verify.html?email=${encodeURIComponent(result.email)}`;
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