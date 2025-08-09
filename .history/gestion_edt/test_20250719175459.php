<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Inscription - Gestionnaire EDT</title>
    <style>
        .feedback-error {
            color: #ef4444; /* Rouge de Tailwind (red-500) */
            font-weight: 600;
        }
        .feedback-success {
            color: #22c55e; /* Vert de Tailwind (green-500) */
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-100">

    <div class="h-screen flex items-center justify-center">
        <div class="flex flex-col bg-white rounded-2xl shadow-xl m-6 md:flex-row md:space-y-0">
            
            <!-- ====== SECTION GAUCHE (FORMULAIRE) ====== -->
            <div class="flex flex-col justify-center p-8 md:p-14">
                <div class="text-center">
                    <img style="width: 200px; height: auto; display: block; margin: 0 auto 2rem auto;"
                         src="assets/images/logo.png" 
                         alt="Logo du site">
                </div>
                <span class="mb-3 text-4xl font-bold">Créer un Compte</span>
                <span class="font-light text-gray-400 mb-8">
                    Rejoignez-nous pour commencer à gérer vos emplois du temps.
                </span>

                <form id="registerForm">
                    <div class="py-2">
                        <span class="mb-2 text-md">Nom complet</span>
                        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" name="nom_complet" id="nom_complet" required />
                    </div>
                    <div class="py-2">
                        <span class="mb-2 text-md">Nom de l'établissement</span>
                        <input type="text" class="w-full p-2 border border-gray-300 rounded-md" name="nom_etablissement" id="nom_etablissement" required />
                    </div>
                    <div class="py-2">
                        <span class="mb-2 text-md">Email</span>
                        <input type="email" class="w-full p-2 border border-gray-300 rounded-md" name="email" id="email" required autocomplete="email" />
                    </div>
                    <div class="py-2">
                        <span class="mb-2 text-md">Mot de passe</span>
                        <input type="password" class="w-full p-2 border border-gray-300 rounded-md" name="mot_de_passe" id="mot_de_passe" required autocomplete="new-password" />
                    </div>
                    
                    <button
                        type="submit"
                        class="w-full bg-black text-white p-2 rounded-lg my-6 hover:bg-white hover:text-black hover:border hover:border-gray-300 transition-all duration-300"
                    >
                        Créer mon compte
                    </button>
                </form>

                <div id="feedback" class="text-center"></div>
                
                <div class="text-center text-gray-400 mt-4">
                    Déjà un compte ?
                    <a href="login.html" class="font-bold text-black">Connectez-vous</a>
                </div>
            </div>

            <!-- ====== SECTION DROITE (IMAGE) ====== -->
            <div class="relative hidden md:block">
                 <img src="https://images.unsplash.com/photo-1481627834876-b7833e8f5570?q=80&w=1854&auto=format&fit=crop" 
                      alt="Image" 
                      class="w-[400px] h-full hidden rounded-r-2xl md:block object-cover">
            </div>

        </div>
    </div>

    <!-- ====== SCRIPT JAVASCRIPT ====== -->
    <script>
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const feedbackEl = document.getElementById('feedback');
            feedbackEl.textContent = '';
            feedbackEl.className = '';

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            fetch('../api/auth/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    feedbackEl.textContent = "Compte créé avec succès ! Vous allez être redirigé vers la page de connexion.";
                    feedbackEl.className = 'feedback-success';
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 3000);
                } else {
                    feedbackEl.textContent = result.message || "Une erreur est survenue.";
                    feedbackEl.className = 'feedback-error';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedbackEl.textContent = "Erreur de communication avec le serveur.";
                feedbackEl.className = 'feedback-error';
            });
        });
    </script>
</body>
</html>