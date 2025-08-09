       // --- GESTION DE LA VISIBILITÉ DU MOT DE PASSE (Votre code est parfait) ---
       const passwordInput = document.getElementById("mot_de_passe");
       const toggleIcon = document.getElementById("togglePassword");
   
       toggleIcon.addEventListener("click", function () {
           const isPassword = passwordInput.type === "password";
           passwordInput.type = isPassword ? "text" : "password";
           toggleIcon.src = isPassword
               ? "https://img.icons8.com/fluency-systems-regular/24/visible--v1.png"
               : "https://img.icons8.com/fluency-systems-regular/24/invisible.png";
       });
   
       // --- GESTION DE LA SOUMISSION DU FORMULAIRE (Version Corrigée) ---
       document.getElementById('loginForm').addEventListener('submit', function(e) {
           e.preventDefault();
           const feedbackEl = document.getElementById('feedback');
           feedbackEl.textContent = '';
           feedbackEl.className = '';
   
           const formData = new FormData(this);
           const data = Object.fromEntries(formData.entries());
   
           fetch('../api/auth/login.php', {
               method: 'POST',
               headers: { 'Content-Type': 'application/json' },
               body: JSON.stringify(data)
           })
           .then(response => response.json())
           .then(result => {
               // --- CORRECTION DE LA LOGIQUE DE REDIRECTION ---
               if (result.success && result.action === 'redirect' && result.url) {
                   // Si le serveur nous donne un succès ET une URL de redirection,
                   // nous y allons sans poser de questions.
                   window.location.href = result.url;
               } else {
                   // Si l'une de ces conditions n'est pas remplie, c'est une erreur.
                   feedbackEl.textContent = result.message || "Email ou mot de passe incorrect.";
                   feedbackEl.className = 'feedback-error';
               }
           })
           .catch(error => {
               console.error('Error:', error);
               feedbackEl.textContent = "Erreur de communication avec le serveur.";
               feedbackEl.className = 'feedback-error';
           });
       });