 // LE SCRIPT EST INCHANGÉ ET RESTE ENTIÈREMENT FONCTIONNEL
 document.addEventListener('DOMContentLoaded', () => {
    checkSessionAndLoad();
    setupEspacesTagInput(); // Initialiser la nouvelle fonctionnalité
});

async function checkSessionAndLoad() {
try {
const response = await fetch('../api/auth/verify_session.php');
const result = await response.json();
if (!result.success) { 
    window.location.href = 'login.html'; 
    return; 
}

populateUserInfo(result.userData);
setupProfileMenu();
loadPageData();
setupTabNavigation();
setupFormSubmissions();
} catch (error) { 
console.error('Erreur:', error);
window.location.href = 'login.html'; 
}
}

function getInitials(name) {
if (!name || typeof name !== 'string') return '--';
const words = name.trim().split(' ');
if (words.length === 1) return words[0].substring(0, 2).toUpperCase();
return (words[0][0] + words[words.length - 1][0]).toUpperCase();
}

function populateUserInfo(userData) {
if (!userData) return;

// Barre de navigation
const initialsEl = document.getElementById('profile-initials');
if(initialsEl) initialsEl.textContent = getInitials(userData.nom);

// Menu dropdown
const nameEl = document.getElementById('dropdown-user-name');
const emailEl = document.getElementById('dropdown-user-email');
if (nameEl) nameEl.textContent = userData.nom;
if (emailEl) emailEl.textContent = userData.email;

// Page de profil
const pageInitials = document.getElementById('profile-page-initials');
const pageName = document.getElementById('profile-page-name');
const etablissement = document.getElementById('profile-page-etablissement');

/***** DÉBUT DE LA CORRECTION *****/
// On utilise nom_complet en priorité. S'il n'existe pas, on utilise nom.
const displayName = userData.nom_complet || userData.nom;

if (pageInitials) pageInitials.textContent = getInitials(displayName);
if (pageName) pageName.textContent = displayName;
/***** FIN DE LA CORRECTION *****/

if (etablissement) etablissement.textContent = userData.nom_etablissement;
}

function setupProfileMenu() {
    const profileButton = document.getElementById('profile-button');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', (e) => { 
            e.stopPropagation(); 
            profileDropdown.classList.toggle('show'); 
        });
    }
    
    window.addEventListener('click', () => { 
        if (profileDropdown && profileDropdown.classList.contains('show')) {
            profileDropdown.classList.remove('show'); 
        }
    });
}

function loadPageData() {
    fetch('../api/profile/get_profile_data.php')
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const data = result.data;
                
                document.getElementById('nom_complet').value = data.user.nom_complet || '';
                document.getElementById('email').value = data.user.email || '';
                document.getElementById('etablissement_name').textContent = data.user.nom_etablissement || '';
                document.getElementById('complexe_name').textContent = data.user.complexe || '';
                document.getElementById('espaces').value = (data.espaces || []).join('\n') || '';
                
                const tagContainer = document.getElementById('espaces-tag-container');
                tagContainer.querySelectorAll('.tag').forEach(tag => tag.remove());
                
                // On peuple le nouveau système de tags au lieu de la textarea
                const espaces = data.espaces || [];
                espaces.forEach(espace => {
                    createEspaceTag(espace);
                });
                // On met à jour la textarea cachée
                updateHiddenEspacesTextarea();
                
                document.getElementById('holidays').value = data.calendar.holidays || '';
                document.getElementById('vacations').value = data.calendar.vacations || '';
                
                const formateursTbody = document.getElementById('formateurs-table-body');
                formateursTbody.innerHTML = '';
                
                (data.formateurs || []).forEach(f => {
                    const row = `
                        <tr>
                            <td>${f.nom || ''}</td><td>${f.matricule || ''}</td>
                            <td><input name="formateurs[${f.matricule}][masse_horaire]" type="number" class="form-control" value="${f.masse_horaire || ''}"></td>
                            <td><input name="formateurs[${f.matricule}][email]" type="email" class="form-control" value="${f.email || ''}"></td>
                            <input type="hidden" name="formateurs[${f.matricule}][nom]" value="${f.nom || ''}"><input type="hidden" name="formateurs[${f.matricule}][matricule]" value="${f.matricule || ''}">
                        </tr>`;
                    formateursTbody.innerHTML += row;
                });
            } else {
                alert("Erreur: Impossible de charger les données du profil.");
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des données:', error);
            alert("Une erreur s'est produite lors du chargement des données du profil.");
        });
}

function setupEspacesTagInput() {
    const tagContainer = document.getElementById('espaces-tag-container');
    const input = document.getElementById('espaces-input');

    // Mettre le focus sur l'input quand on clique dans le conteneur
    tagContainer.addEventListener('click', () => {
        input.focus();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault(); // Empêche la soumission du formulaire
            const value = input.value.trim();
            if (value) {
                createEspaceTag(value);
                input.value = ''; // Réinitialise l'input
                updateHiddenEspacesTextarea();
            }
        }
    });
}

function createEspaceTag(label) {
    const tagContainer = document.getElementById('espaces-tag-container');
    const input = document.getElementById('espaces-input');
    const tag = document.createElement('div');
    tag.className = 'tag';
    
    const tagText = document.createElement('span');
    tagText.textContent = label;
    
    const removeBtn = document.createElement('span');
    removeBtn.className = 'tag-remove';
    removeBtn.innerHTML = '×'; // Croix pour supprimer
    removeBtn.addEventListener('click', () => {
        tag.remove();
        updateHiddenEspacesTextarea();
    });
    
    tag.appendChild(tagText);
    tag.appendChild(removeBtn);
    
    // Insérer le tag avant le champ de saisie
    tagContainer.insertBefore(tag, input);
}

function updateHiddenEspacesTextarea() {
    const tagContainer = document.getElementById('espaces-tag-container');
    const tags = tagContainer.querySelectorAll('.tag');
    const hiddenTextarea = document.getElementById('espaces');
    
    // On récupère le texte de chaque tag et on les joint avec un retour à la ligne
    // pour que le formulaire soit soumis exactement comme avant.
    const values = Array.from(tags).map(tag => tag.querySelector('span').textContent);
    hiddenTextarea.value = values.join('\n');
}

function setupTabNavigation() {
    const tabLinks = document.querySelectorAll('.sidebar-link');
    const contentPanels = document.querySelectorAll('.panel');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            tabLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            const targetId = link.dataset.target;
            contentPanels.forEach(panel => panel.classList.remove('active'));
            const targetPanel = document.getElementById(targetId);
            if (targetPanel) {
                targetPanel.classList.add('active');
            }
        });
    });
}

function showFeedback(elementId, message, isSuccess) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.textContent = message;
    el.className = isSuccess ? 'feedback-message feedback-success' : 'feedback-message feedback-error';
    setTimeout(() => { el.textContent = ''; }, 4000);
}

function setupFormSubmissions() {
    document.getElementById('userInfoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = { nom_complet: document.getElementById('nom_complet').value, email: document.getElementById('email').value };
        fetch('../api/profile/update_user_info.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(res => res.json())
        .then(result => {
            showFeedback('userInfoFeedback', result.message, result.success);
            if (result.success) {
                populateUserInfo({ nom: data.nom_complet, email: data.email, nom_complet: data.nom_complet, nom_etablissement: document.getElementById('profile-page-etablissement').textContent });
            }
        });
    });
    
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_new_password').value;
        if (newPassword !== confirmPassword) {
            showFeedback('passwordFeedback', 'Les mots de passe ne correspondent pas.', false);
            return;
        }
        const data = { new_password: newPassword, confirm_new_password: confirmPassword };
        fetch('../api/profile/update_password.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(res => res.json())
        .then(result => {
            showFeedback('passwordFeedback', result.message, result.success);
            if (result.success) this.reset();
        });
    });
    
    document.getElementById('espacesForm').addEventListener('submit', function(e) {
        e.preventDefault();
        // La mise à jour de la textarea se fait déjà lors de l'ajout/suppression
        // Il n'y a rien de plus à faire ici, l'ancienne logique fonctionnera.
        const data = { 
            espaces: document.getElementById('espaces').value.split('\n')
                .map(s => s.trim())
                .filter(s => s.length > 0)
        };
        
        fetch('../api/profile/update_establishment_info.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(result => showFeedback('espacesFeedback', result.message, result.success));
    });
    
    document.getElementById('calendarForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = { holidays: document.getElementById('holidays').value, vacations: document.getElementById('vacations').value };
        fetch('../api/profile/update_establishment_info.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(res => res.json())
        .then(result => showFeedback('calendarFeedback', result.message, result.success));
    });
    
    document.getElementById('formateursForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const formateursMap = {};
        for (let [key, value] of formData.entries()) {
            const match = key.match(/formateurs\[(.+?)]\[(.+?)]/);
            if (match) {
                const matricule = match[1]; 
                const property = match[2];
                if (!formateursMap[matricule]) formateursMap[matricule] = {};
                formateursMap[matricule][property] = value;
            }
        }
        const data = { formateurs: Object.values(formateursMap) };
        fetch('../api/profile/update_formateurs.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(res => res.json())
        .then(result => showFeedback('formateursFeedback', result.message, result.success));
    });
}