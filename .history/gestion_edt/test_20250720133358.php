<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Inscription - Gestionnaire EDT</title>
    
    <!-- Icônes personnalisées -->
    <link type="image/png" sizes="16x16" rel="icon" href="https://cdn-icons-png.flaticon.com/16/709/709612.png"> 
    <link type="image/png" sizes="96x96" rel="icon" href="https://cdn-icons-png.flaticon.com/96/709/709612.png">
    <link type="image/png" sizes="16x16" rel="icon" href="https://cdn-icons-png.flaticon.com/16/709/709613.png">
    <link type="image/png" sizes="96x96" rel="icon" href="https://cdn-icons-png.flaticon.com/96/709/709613.png">
    
    <style>
        .feedback-error { color: #ef4444; font-weight: 600; }
        .feedback-success { color: #22c55e; font-weight: 600; }
        .input-error-message { color: #ef4444; font-size: 0.875rem; margin-top: 0.25rem; }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            background-color: #f9fafb;
            z-index: 10;
        }

        .password-toggle:hover {
            background-color: #e5e7eb;
            transform: translateY(-50%) scale(1.05);
        }

        .password-toggle:active {
            transform: translateY(-50%) scale(0.95);
        }

        .password-toggle img {
            width: 20px;
            height: 20px;
            transition: all 0.2s ease;
        }

        .password-toggle:hover img {
            transform: scale(1.15);
        }

        .input-container {
            position: relative;
        }

        .input-container input {
            padding-right: 48px;
        }
    </style>
</head>
<body class="bg-gray-100">

    <div class="h-screen flex items-center justify-center">
        <div class="flex flex-col bg-white rounded-2xl shadow-xl m-6 md:flex-row md:space-y-0 w-full max-w-4xl">
            
            <div class="flex flex-col justify-center p-8 md:p-14 flex-1">
                <div class="text-center">
                    <div class="flex justify-center items-center mb-6">
                        <img src="https://cdn-icons-png.flaticon.com/128/709/709612.png" alt="Icône œil" class="w-16 h-16 mr-3">
                        <h1 class="text-3xl font-bold text-indigo-700">Gestionnaire EDT</h1>
                    </div>
                </div>
                <span class="mb-3 text-4xl font-bold text-gray-800">Créer un Compte</span>
                <span class="font-light text-gray-500 mb-8">
                    Rejoignez-nous pour commencer à gérer vos emplois du temps.
                </span>

                <form id="registerForm" class="space-y-4">
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Nom complet</label>
                        <input type="text" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:border-transparent" name="nom_complet" id="nom_complet" required />
                    </div>
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Nom de l'établissement</label>
                        <input type="text" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:border-transparent" name="nom_etablissement" id="nom_etablissement" required />
                    </div>
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Email</label>
                        <input type="email" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:border-transparent" name="email" id="email" required autocomplete="email" />
                    </div>

                    <!-- Champ Mot de passe -->
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Mot de passe</label>
                        <div class="input-container">
                            <input type="password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:border-transparent" name="mot_de_passe" id="mot_de_passe" required autocomplete="new-password" />
                            <button type="button" id="togglePassword" class="password-toggle">
                                <img src="https://cdn-icons-png.flaticon.com/128/709/709612.png" alt="Afficher le mot de passe" id="eyeIconPassword">
                            </button>
                        </div>
                    </div>

                    <!-- Champ Confirmer le mot de passe -->
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Confirmer le mot de passe</label>
                        <div class="input-container">
                            <input type="password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:border-transparent" name="confirm_mot_de_passe" id="confirm_mot_de_passe" required autocomplete="new-password" />
                            <button type="button" id="toggleConfirmPassword" class="password-toggle">
                                <img src="https://cdn-icons-png.flaticon.com/128/709/709612.png" alt="Afficher la confirmation du mot de passe" id="eyeIconConfirm">
                            </button>
                        </div>
                        <div id="password-error" class="input-error-message mt-2"></div>
                    </div>
                    
                    <button type="submit" class="w-full bg-indigo-600 text-white p-3 rounded-lg my-6 hover:bg-indigo-700 transition-all duration-300 font-medium text-lg shadow-md hover:shadow-lg">
                        Créer mon compte
                    </button>
                </form>

                <div id="feedback" class="text-center py-3 rounded-lg"></div>
                
                <div class="text-center text-gray-500 mt-4">
                    Déjà un compte ?
                    <a href="login.html" class="font-bold text-indigo-600 hover:text-indigo-800 transition-colors">Connectez-vous</a>
                </div>
            </div>
            
            <div class="hidden md:flex md:flex-1">
                <div class="w-full h-full bg-gradient-to-br from-indigo-500 to-purple-600 rounded-r-2xl flex flex-col items-center justify-center p-8 text-white">
                    <div class="mb-8">
                        <img src="https://cdn-icons-png.flaticon.com/512/709/709612.png" alt="Icône de sécurité" class="w-24 h-24 mx-auto mb-4">
                        <h2 class="text-3xl font-bold text-center">Sécurité Avancée</h2>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <svg class="w-6 h-6 mr-3 mt-1 text-indigo-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            <p class="text-indigo-100">Protection des données avec chiffrement de pointe</p>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-6 h-6 mr-3 mt-1 text-indigo-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <p class="text-indigo-100">Authentification à double facteur disponible</p>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-6 h-6 mr-3 mt-1 text-indigo-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                            <p class="text-indigo-100">Sauvegardes quotidiennes automatiques</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- GESTION DE LA VISIBILITÉ DU MOT DE PASSE ---
        const togglePassword = document.getElementById("togglePassword");
        const passwordInput = document.getElementById("mot_de_passe");
        const eyeIconPassword = document.getElementById("eyeIconPassword");

        const toggleConfirmPassword = document.getElementById("toggleConfirmPassword");
        const confirmPasswordInput = document.getElementById("confirm_mot_de_passe");
        const eyeIconConfirm = document.getElementById("eyeIconConfirm");

        // URL des icônes
        const eyeOpenUrl = "https://cdn-icons-png.flaticon.com/128/709/709612.png";
        const eyeClosedUrl = "https://cdn-icons-png.flaticon.com/128/709/709613.png";

        togglePassword.addEventListener("click", () => {
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                eyeIconPassword.src = eyeClosedUrl;
                eyeIconPassword.alt = "Masquer le mot de passe";
            } else {
                passwordInput.type = "password";
                eyeIconPassword.src = eyeOpenUrl;
                eyeIconPassword.alt = "Afficher le mot de passe";
            }
            
            // Animation
            togglePassword.style.transform = "translateY(-50%) scale(0.9)";
            setTimeout(() => {
                togglePassword.style.transform = "translateY(-50%)";
            }, 100);
        });

        toggleConfirmPassword.addEventListener("click", () => {
            if (confirmPasswordInput.type === "password") {
                confirmPasswordInput.type = "text";
                eyeIconConfirm.src = eyeClosedUrl;
                eyeIconConfirm.alt = "Masquer la confirmation du mot de passe";
            } else {
                confirmPasswordInput.type = "password";
                eyeIconConfirm.src = eyeOpenUrl;
                eyeIconConfirm.alt = "Afficher la confirmation du mot de passe";
            }
            
            // Animation
            toggleConfirmPassword.style.transform = "translateY(-50%) scale(0.9)";
            setTimeout(() => {
                toggleConfirmPassword.style.transform = "translateY(-50%)";
            }, 100);
        });
        
        // --- GESTION DE LA SOUMISSION DU FORMULAIRE ---
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const feedbackEl = document.getElementById('feedback');
            const passwordErrorEl = document.getElementById('password-error');
            feedbackEl.textContent = '';
            feedbackEl.className = '';
            passwordErrorEl.textContent = '';

            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            // Validation des mots de passe
            if (password !== confirmPassword) {
                passwordErrorEl.textContent = "Les mots de passe ne correspondent pas.";
                return;
            }
            if (password.length < 8) {
                passwordErrorEl.textContent = "Le mot de passe doit contenir au moins 8 caractères.";
                return;
            }
            if (!/[A-Z]/.test(password)) {
                passwordErrorEl.textContent = "Le mot de passe doit contenir au moins une majuscule.";
                return;
            }
            if (!/[0-9]/.test(password)) {
                passwordErrorEl.textContent = "Le mot de passe doit contenir au moins un chiffre.";
                return;
            }

            // Simuler une requête réussie
            feedbackEl.className = 'feedback-success bg-green-50 p-4 rounded-lg';
            feedbackEl.textContent = "🎉 Compte créé avec succès ! Redirection vers la page de connexion...";
            
            setTimeout(() => { 
                feedbackEl.textContent = "Redirection en cours...";
                // window.location.href = 'login.html';
            }, 2000);
        });

        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = 0;
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.5s ease-in';
                document.body.style.opacity = 1;
            }, 100);
        });
    </script>
</body>
</html>