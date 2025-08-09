/**
 * Sécurité Frontend - Fonctions de protection et validation
 * À inclure dans toutes les pages HTML
 */

// Configuration de sécurité
const SECURITY_CONFIG = {
    // Rate limiting côté client
    MAX_REQUESTS_PER_MINUTE: 60,
    // Validation des entrées
    MAX_INPUT_LENGTH: 1000,
    // Protection XSS
    ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'a'],
    // Protection CSRF
    CSRF_TOKEN_NAME: 'csrf_token'
};

/**
 * Classe de sécurité frontend
 */
class FrontendSecurity {
    constructor() {
        this.requestCount = 0;
        this.lastRequestTime = 0;
        this.csrfToken = this.getCsrfToken();
        this.initSecurity();
    }

    /**
     * Initialisation des protections de sécurité
     */
    initSecurity() {
        // Protection contre les attaques par clic droit
        this.preventRightClick();
        
        // Protection contre les raccourcis clavier dangereux
        this.preventDangerousShortcuts();
        
        // Protection contre la copie de contenu sensible
        this.preventCopying();
        
        // Validation automatique des formulaires
        this.initFormValidation();
        
        // Protection contre les injections dans les champs
        this.sanitizeInputs();
        
        // Détection de tentatives d'injection
        this.detectInjectionAttempts();
    }

    /**
     * Empêcher le clic droit pour protéger le contenu
     */
    preventRightClick() {
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.logSecurityEvent('Tentative de clic droit bloquée');
            return false;
        });
    }

    /**
     * Empêcher les raccourcis clavier dangereux
     */
    preventDangerousShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+U (voir le code source)
            if (e.ctrlKey && e.key === 'u') {
                e.preventDefault();
                this.logSecurityEvent('Tentative d\'accès au code source bloquée');
                return false;
            }
            
            // F12 (outils de développement)
            if (e.key === 'F12') {
                e.preventDefault();
                this.logSecurityEvent('Tentative d\'ouverture des outils de développement bloquée');
                return false;
            }
            
            // Ctrl+Shift+I (inspecter)
            if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                e.preventDefault();
                this.logSecurityEvent('Tentative d\'inspection bloquée');
                return false;
            }
        });
    }

    /**
     * Empêcher la copie de contenu sensible
     */
    preventCopying() {
        document.addEventListener('copy', (e) => {
            // Permettre la copie mais logger l'événement
            this.logSecurityEvent('Tentative de copie détectée');
        });
    }

    /**
     * Validation automatique des formulaires
     */
    initFormValidation() {
        document.addEventListener('DOMContentLoaded', () => {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', (e) => {
                    if (!this.validateForm(form)) {
                        e.preventDefault();
                        this.showSecurityAlert('Formulaire invalide détecté');
                        return false;
                    }
                });
            });
        });
    }

    /**
     * Validation d'un formulaire
     */
    validateForm(form) {
        const inputs = form.querySelectorAll('input, textarea, select');
        let isValid = true;

        inputs.forEach(input => {
            if (!this.validateInput(input)) {
                isValid = false;
                this.highlightInvalidInput(input);
            }
        });

        return isValid;
    }

    /**
     * Validation d'un champ de saisie
     */
    validateInput(input) {
        const value = input.value;
        const type = input.type;
        const name = input.name;

        // Validation de longueur
        if (value.length > SECURITY_CONFIG.MAX_INPUT_LENGTH) {
            this.logSecurityEvent(`Entrée trop longue: ${name}`);
            return false;
        }

        // Validation par type
        switch (type) {
            case 'email':
                return this.validateEmail(value);
            case 'password':
                return this.validatePassword(value);
            case 'text':
                return this.validateText(value);
            case 'number':
                return this.validateNumber(value);
            default:
                return true;
        }
    }

    /**
     * Validation d'email
     */
    validateEmail(email) {
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return emailRegex.test(email);
    }

    /**
     * Validation de mot de passe
     */
    validatePassword(password) {
        // Au moins 8 caractères, une majuscule, une minuscule, un chiffre
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;
        return passwordRegex.test(password);
    }

    /**
     * Validation de texte
     */
    validateText(text) {
        // Empêcher les scripts et caractères dangereux
        const dangerousPatterns = [
            /<script/i,
            /javascript:/i,
            /on\w+\s*=/i,
            /data:text\/html/i
        ];

        return !dangerousPatterns.some(pattern => pattern.test(text));
    }

    /**
     * Validation de nombre
     */
    validateNumber(value) {
        return !isNaN(value) && value >= 0;
    }

    /**
     * Nettoyage des entrées utilisateur
     */
    sanitizeInputs() {
        document.addEventListener('input', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                e.target.value = this.sanitizeString(e.target.value);
            }
        });
    }

    /**
     * Nettoyer une chaîne de caractères
     */
    sanitizeString(str) {
        // Supprimer les balises HTML dangereuses
        str = str.replace(/<script[^>]*>.*?<\/script>/gi, '');
        str = str.replace(/<iframe[^>]*>.*?<\/iframe>/gi, '');
        str = str.replace(/<object[^>]*>.*?<\/object>/gi, '');
        str = str.replace(/<embed[^>]*>/gi, '');
        
        // Échapper les caractères spéciaux
        str = str.replace(/&/g, '&amp;');
        str = str.replace(/</g, '&lt;');
        str = str.replace(/>/g, '&gt;');
        str = str.replace(/"/g, '&quot;');
        str = str.replace(/'/g, '&#x27;');
        
        return str;
    }

    /**
     * Détection de tentatives d'injection
     */
    detectInjectionAttempts() {
        const injectionPatterns = [
            /<script/i,
            /javascript:/i,
            /vbscript:/i,
            /onload/i,
            /onerror/i,
            /onclick/i,
            /onmouseover/i,
            /eval\(/i,
            /document\./i,
            /window\./i,
            /alert\(/i,
            /confirm\(/i,
            /prompt\(/i
        ];

        document.addEventListener('input', (e) => {
            const value = e.target.value;
            const hasInjection = injectionPatterns.some(pattern => pattern.test(value));
            
            if (hasInjection) {
                this.logSecurityEvent('Tentative d\'injection détectée', {
                    field: e.target.name,
                    value: value.substring(0, 50)
                });
                this.showSecurityAlert('Caractères non autorisés détectés');
                e.target.value = this.sanitizeString(value);
            }
        });
    }

    /**
     * Rate limiting côté client
     */
    checkRateLimit() {
        const now = Date.now();
        const timeWindow = 60000; // 1 minute

        if (now - this.lastRequestTime < timeWindow) {
            this.requestCount++;
            if (this.requestCount > SECURITY_CONFIG.MAX_REQUESTS_PER_MINUTE) {
                this.logSecurityEvent('Rate limit dépassé côté client');
                return false;
            }
        } else {
            this.requestCount = 1;
            this.lastRequestTime = now;
        }

        return true;
    }

    /**
     * Ajouter le token CSRF aux requêtes AJAX
     */
    addCsrfToRequest(xhr) {
        if (this.csrfToken) {
            xhr.setRequestHeader('X-CSRF-Token', this.csrfToken);
        }
    }

    /**
     * Récupérer le token CSRF
     */
    getCsrfToken() {
        // Chercher dans les meta tags
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.getAttribute('content');
        }

        // Chercher dans les inputs cachés
        const hiddenInput = document.querySelector('input[name="csrf_token"]');
        if (hiddenInput) {
            return hiddenInput.value;
        }

        return null;
    }

    /**
     * Logger les événements de sécurité
     */
    logSecurityEvent(message, data = {}) {
        const event = {
            timestamp: new Date().toISOString(),
            message: message,
            url: window.location.href,
            userAgent: navigator.userAgent,
            data: data
        };

        // Envoyer au serveur si possible
        if (typeof fetch !== 'undefined') {
            fetch('/api/security/log', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify(event)
            }).catch(() => {
                // Fallback: stocker localement
                this.storeSecurityEvent(event);
            });
        } else {
            this.storeSecurityEvent(event);
        }

        console.warn('Security Event:', event);
    }

    /**
     * Stocker un événement de sécurité localement
     */
    storeSecurityEvent(event) {
        const events = JSON.parse(localStorage.getItem('security_events') || '[]');
        events.push(event);
        
        // Garder seulement les 100 derniers événements
        if (events.length > 100) {
            events.splice(0, events.length - 100);
        }
        
        localStorage.setItem('security_events', JSON.stringify(events));
    }

    /**
     * Afficher une alerte de sécurité
     */
    showSecurityAlert(message) {
        // Créer une alerte personnalisée
        const alert = document.createElement('div');
        alert.className = 'security-alert';
        alert.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: #ff4444;
                color: white;
                padding: 15px;
                border-radius: 5px;
                z-index: 10000;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                max-width: 300px;
            ">
                <strong>⚠️ Sécurité</strong><br>
                ${message}
            </div>
        `;
        
        document.body.appendChild(alert);
        
        // Supprimer après 5 secondes
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }

    /**
     * Mettre en évidence un champ invalide
     */
    highlightInvalidInput(input) {
        input.style.borderColor = '#ff4444';
        input.style.backgroundColor = '#fff5f5';
        
        setTimeout(() => {
            input.style.borderColor = '';
            input.style.backgroundColor = '';
        }, 3000);
    }

    /**
     * Validation côté client pour les requêtes AJAX
     */
    validateAjaxRequest(url, method, data) {
        // Vérifier le rate limiting
        if (!this.checkRateLimit()) {
            throw new Error('Trop de requêtes. Veuillez patienter.');
        }

        // Valider l'URL
        if (!this.isValidUrl(url)) {
            throw new Error('URL invalide');
        }

        // Valider les données
        if (data && typeof data === 'object') {
            for (let key in data) {
                if (data.hasOwnProperty(key)) {
                    if (!this.validateInputValue(data[key])) {
                        throw new Error(`Donnée invalide: ${key}`);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Valider une URL
     */
    isValidUrl(url) {
        try {
            const urlObj = new URL(url, window.location.origin);
            return urlObj.origin === window.location.origin;
        } catch {
            return false;
        }
    }

    /**
     * Valider une valeur d'entrée
     */
    validateInputValue(value) {
        if (typeof value !== 'string') {
            return true;
        }

        return this.validateText(value) && value.length <= SECURITY_CONFIG.MAX_INPUT_LENGTH;
    }
}

// Initialiser la sécurité frontend
const frontendSecurity = new FrontendSecurity();

// Exposer les fonctions de sécurité globalement
window.SecurityUtils = {
    validateEmail: (email) => frontendSecurity.validateEmail(email),
    validatePassword: (password) => frontendSecurity.validatePassword(password),
    sanitizeString: (str) => frontendSecurity.sanitizeString(str),
    logSecurityEvent: (message, data) => frontendSecurity.logSecurityEvent(message, data),
    showSecurityAlert: (message) => frontendSecurity.showSecurityAlert(message)
};

// Protection contre la modification des objets de sécurité
Object.freeze(window.SecurityUtils); 