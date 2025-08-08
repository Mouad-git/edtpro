/**
 * Sécurité des Formulaires - Protection et validation avancée
 * Fonctions pour sécuriser tous les formulaires de l'application
 */

class FormSecurity {
    constructor() {
        this.csrfToken = this.getCsrfToken();
        this.submissionAttempts = new Map();
        this.maxAttempts = 5;
        this.lockoutTime = 300000; // 5 minutes
        this.initFormSecurity();
    }

    /**
     * Initialisation de la sécurité des formulaires
     */
    initFormSecurity() {
        document.addEventListener('DOMContentLoaded', () => {
            this.secureAllForms();
            this.addPasswordStrengthMeter();
            this.addRealTimeValidation();
            this.addCaptchaProtection();
        });
    }

    /**
     * Sécuriser tous les formulaires
     */
    secureAllForms() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            this.secureForm(form);
        });
    }

    /**
     * Sécuriser un formulaire spécifique
     */
    secureForm(form) {
        // Ajouter la classe de sécurité
        form.classList.add('secure-form');

        // Ajouter le token CSRF
        this.addCsrfToken(form);

        // Validation avant soumission
        form.addEventListener('submit', (e) => {
            if (!this.validateFormSubmission(form, e)) {
                e.preventDefault();
                return false;
            }
        });

        // Protection contre les soumissions multiples
        this.preventMultipleSubmissions(form);

        // Validation en temps réel
        this.addRealTimeFormValidation(form);

        // Protection contre les attaques par timing
        this.addTimingProtection(form);
    }

    /**
     * Ajouter le token CSRF au formulaire
     */
    addCsrfToken(form) {
        // Vérifier si le token existe déjà
        const existingToken = form.querySelector('input[name="csrf_token"]');
        if (!existingToken && this.csrfToken) {
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = 'csrf_token';
            tokenInput.value = this.csrfToken;
            form.appendChild(tokenInput);
        }
    }

    /**
     * Validation de soumission de formulaire
     */
    validateFormSubmission(form, event) {
        const formId = form.id || form.action || 'unknown';
        
        // Vérifier le rate limiting
        if (this.isFormLocked(formId)) {
            this.showSecurityAlert('Formulaire temporairement bloqué. Veuillez patienter.');
            return false;
        }

        // Validation des champs
        const inputs = form.querySelectorAll('input, textarea, select');
        let isValid = true;
        let invalidFields = [];

        inputs.forEach(input => {
            if (!this.validateInput(input)) {
                isValid = false;
                invalidFields.push(input.name || input.id);
                this.highlightInvalidInput(input);
            }
        });

        if (!isValid) {
            this.showSecurityAlert(`Champs invalides: ${invalidFields.join(', ')}`);
            this.logSecurityEvent('Soumission de formulaire avec champs invalides', {
                form: formId,
                fields: invalidFields
            });
            return false;
        }

        // Vérifier les tentatives de soumission
        this.recordSubmissionAttempt(formId);

        // Validation des données sensibles
        if (!this.validateSensitiveData(form)) {
            this.showSecurityAlert('Données sensibles détectées');
            return false;
        }

        return true;
    }

    /**
     * Valider un champ de saisie
     */
    validateInput(input) {
        const value = input.value;
        const type = input.type;
        const name = input.name || input.id;

        // Validation de base
        if (input.required && !value.trim()) {
            return false;
        }

        // Validation par type
        switch (type) {
            case 'email':
                return this.validateEmail(value);
            case 'password':
                return this.validatePassword(value);
            case 'tel':
                return this.validatePhone(value);
            case 'url':
                return this.validateUrl(value);
            case 'number':
                return this.validateNumber(value);
            case 'text':
            case 'textarea':
                return this.validateText(value);
            default:
                return true;
        }
    }

    /**
     * Validation d'email avancée
     */
    validateEmail(email) {
        if (!email) return true; // Champ optionnel
        
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        const isValid = emailRegex.test(email);
        
        // Vérifications supplémentaires
        if (isValid) {
            // Vérifier la longueur
            if (email.length > 254) return false;
            
            // Vérifier les caractères dangereux
            const dangerousPatterns = [
                /<script/i,
                /javascript:/i,
                /vbscript:/i,
                /on\w+\s*=/i
            ];
            
            return !dangerousPatterns.some(pattern => pattern.test(email));
        }
        
        return false;
    }

    /**
     * Validation de mot de passe avancée
     */
    validatePassword(password) {
        if (!password) return true; // Champ optionnel
        
        // Au moins 8 caractères
        if (password.length < 8) return false;
        
        // Au moins une majuscule
        if (!/[A-Z]/.test(password)) return false;
        
        // Au moins une minuscule
        if (!/[a-z]/.test(password)) return false;
        
        // Au moins un chiffre
        if (!/\d/.test(password)) return false;
        
        // Caractères spéciaux autorisés
        const allowedSpecialChars = /[@$!%*?&]/;
        if (!allowedSpecialChars.test(password)) return false;
        
        return true;
    }

    /**
     * Validation de numéro de téléphone
     */
    validatePhone(phone) {
        if (!phone) return true; // Champ optionnel
        
        // Supprimer les espaces et caractères spéciaux
        const cleanPhone = phone.replace(/[\s\-\(\)]/g, '');
        
        // Vérifier que ce sont des chiffres
        return /^\d{10,15}$/.test(cleanPhone);
    }

    /**
     * Validation d'URL
     */
    validateUrl(url) {
        if (!url) return true; // Champ optionnel
        
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    /**
     * Validation de nombre
     */
    validateNumber(value) {
        if (!value) return true; // Champ optionnel
        
        const num = parseFloat(value);
        return !isNaN(num) && num >= 0;
    }

    /**
     * Validation de texte
     */
    validateText(text) {
        if (!text) return true; // Champ optionnel
        
        // Vérifier la longueur
        if (text.length > 1000) return false;
        
        // Vérifier les caractères dangereux
        const dangerousPatterns = [
            /<script/i,
            /javascript:/i,
            /vbscript:/i,
            /on\w+\s*=/i,
            /data:text\/html/i,
            /eval\(/i,
            /document\./i,
            /window\./i
        ];
        
        return !dangerousPatterns.some(pattern => pattern.test(text));
    }

    /**
     * Validation des données sensibles
     */
    validateSensitiveData(form) {
        const sensitivePatterns = [
            /password/i,
            /credit.?card/i,
            /ssn/i,
            /social.?security/i,
            /passport/i,
            /driver.?license/i
        ];

        const formData = new FormData(form);
        for (let [key, value] of formData.entries()) {
            // Vérifier si le nom du champ contient des mots sensibles
            if (sensitivePatterns.some(pattern => pattern.test(key))) {
                // Vérifier que la valeur n'est pas en clair dans le DOM
                const input = form.querySelector(`[name="${key}"]`);
                if (input && input.type !== 'password') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Rate limiting pour les formulaires
     */
    isFormLocked(formId) {
        const attempts = this.submissionAttempts.get(formId);
        if (!attempts) return false;

        const now = Date.now();
        const recentAttempts = attempts.filter(time => now - time < this.lockoutTime);

        if (recentAttempts.length >= this.maxAttempts) {
            return true;
        }

        return false;
    }

    /**
     * Enregistrer une tentative de soumission
     */
    recordSubmissionAttempt(formId) {
        const attempts = this.submissionAttempts.get(formId) || [];
        attempts.push(Date.now());
        
        // Nettoyer les anciennes tentatives
        const now = Date.now();
        const recentAttempts = attempts.filter(time => now - time < this.lockoutTime);
        
        this.submissionAttempts.set(formId, recentAttempts);
    }

    /**
     * Empêcher les soumissions multiples
     */
    preventMultipleSubmissions(form) {
        let isSubmitting = false;

        form.addEventListener('submit', (e) => {
            if (isSubmitting) {
                e.preventDefault();
                this.showSecurityAlert('Formulaire en cours de soumission...');
                return false;
            }

            isSubmitting = true;
            const submitButton = form.querySelector('input[type="submit"], button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Soumission...';
            }

            // Réactiver après 10 secondes
            setTimeout(() => {
                isSubmitting = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Soumettre';
                }
            }, 10000);
        });
    }

    /**
     * Validation en temps réel
     */
    addRealTimeFormValidation(form) {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                this.validateInputRealTime(input);
            });

            input.addEventListener('input', () => {
                this.clearValidationMessage(input);
            });
        });
    }

    /**
     * Validation en temps réel d'un champ
     */
    validateInputRealTime(input) {
        const isValid = this.validateInput(input);
        
        if (!isValid) {
            this.showValidationMessage(input, 'Champ invalide');
        } else {
            this.clearValidationMessage(input);
        }
    }

    /**
     * Afficher un message de validation
     */
    showValidationMessage(input, message) {
        // Supprimer l'ancien message
        this.clearValidationMessage(input);

        const messageDiv = document.createElement('div');
        messageDiv.className = 'validation-message error';
        messageDiv.textContent = message;
        
        input.parentNode.insertBefore(messageDiv, input.nextSibling);
        input.classList.add('input-invalid');
    }

    /**
     * Effacer le message de validation
     */
    clearValidationMessage(input) {
        const existingMessage = input.parentNode.querySelector('.validation-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        input.classList.remove('input-invalid');
    }

    /**
     * Indicateur de force du mot de passe
     */
    addPasswordStrengthMeter() {
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        
        passwordInputs.forEach(input => {
            const meter = document.createElement('div');
            meter.className = 'password-strength';
            input.parentNode.insertBefore(meter, input.nextSibling);

            input.addEventListener('input', () => {
                const strength = this.calculatePasswordStrength(input.value);
                this.updatePasswordMeter(meter, strength);
            });
        });
    }

    /**
     * Calculer la force du mot de passe
     */
    calculatePasswordStrength(password) {
        let score = 0;
        
        if (password.length >= 8) score += 1;
        if (/[a-z]/.test(password)) score += 1;
        if (/[A-Z]/.test(password)) score += 1;
        if (/\d/.test(password)) score += 1;
        if (/[@$!%*?&]/.test(password)) score += 1;
        
        if (score <= 2) return 'weak';
        if (score <= 3) return 'medium';
        if (score <= 4) return 'strong';
        return 'very-strong';
    }

    /**
     * Mettre à jour l'indicateur de force
     */
    updatePasswordMeter(meter, strength) {
        meter.className = `password-strength ${strength}`;
    }

    /**
     * Protection par timing
     */
    addTimingProtection(form) {
        let startTime = Date.now();
        
        form.addEventListener('submit', (e) => {
            const submissionTime = Date.now() - startTime;
            
            // Si la soumission est trop rapide (< 1 seconde), c'est suspect
            if (submissionTime < 1000) {
                this.logSecurityEvent('Soumission de formulaire suspecte (trop rapide)', {
                    time: submissionTime
                });
            }
        });
    }

    /**
     * Protection par captcha
     */
    addCaptchaProtection() {
        const forms = document.querySelectorAll('form[data-captcha="true"]');
        
        forms.forEach(form => {
            if (!form.querySelector('.captcha-container')) {
                this.addCaptchaToForm(form);
            }
        });
    }

    /**
     * Ajouter un captcha au formulaire
     */
    addCaptchaToForm(form) {
        const captchaContainer = document.createElement('div');
        captchaContainer.className = 'captcha-container';
        
        const captchaText = this.generateCaptchaText();
        const captchaDisplay = document.createElement('div');
        captchaDisplay.className = 'captcha-text';
        captchaDisplay.textContent = captchaText;
        
        const captchaInput = document.createElement('input');
        captchaInput.type = 'text';
        captchaInput.name = 'captcha';
        captchaInput.placeholder = 'Entrez le code ci-dessus';
        captchaInput.required = true;
        
        captchaContainer.appendChild(captchaDisplay);
        captchaContainer.appendChild(captchaInput);
        
        form.appendChild(captchaContainer);
        
        // Validation du captcha
        form.addEventListener('submit', (e) => {
            if (captchaInput.value !== captchaText) {
                e.preventDefault();
                this.showSecurityAlert('Code captcha incorrect');
                return false;
            }
        });
    }

    /**
     * Générer un texte captcha
     */
    generateCaptchaText() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let result = '';
        for (let i = 0; i < 6; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    /**
     * Récupérer le token CSRF
     */
    getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.getAttribute('content');
        }

        const hiddenInput = document.querySelector('input[name="csrf_token"]');
        if (hiddenInput) {
            return hiddenInput.value;
        }

        return null;
    }

    /**
     * Logger un événement de sécurité
     */
    logSecurityEvent(message, data = {}) {
        const event = {
            timestamp: new Date().toISOString(),
            message: message,
            url: window.location.href,
            userAgent: navigator.userAgent,
            data: data
        };

        console.warn('Form Security Event:', event);
        
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
    }

    /**
     * Stocker un événement de sécurité
     */
    storeSecurityEvent(event) {
        const events = JSON.parse(localStorage.getItem('form_security_events') || '[]');
        events.push(event);
        
        if (events.length > 50) {
            events.splice(0, events.length - 50);
        }
        
        localStorage.setItem('form_security_events', JSON.stringify(events));
    }

    /**
     * Afficher une alerte de sécurité
     */
    showSecurityAlert(message) {
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
}

// Initialiser la sécurité des formulaires
const formSecurity = new FormSecurity();

// Exposer les fonctions de sécurité des formulaires
window.FormSecurityUtils = {
    validateEmail: (email) => formSecurity.validateEmail(email),
    validatePassword: (password) => formSecurity.validatePassword(password),
    validatePhone: (phone) => formSecurity.validatePhone(phone),
    validateUrl: (url) => formSecurity.validateUrl(url),
    validateNumber: (value) => formSecurity.validateNumber(value),
    validateText: (text) => formSecurity.validateText(text),
    secureForm: (form) => formSecurity.secureForm(form)
};

// Protection contre la modification
Object.freeze(window.FormSecurityUtils); 