/**
 * Sécurité AJAX - Protection des requêtes HTTP
 * Fonctions pour sécuriser toutes les requêtes AJAX
 */

class AjaxSecurity {
    constructor() {
        this.csrfToken = this.getCsrfToken();
        this.requestQueue = [];
        this.maxConcurrentRequests = 5;
        this.activeRequests = 0;
        this.initSecurity();
    }

    /**
     * Initialisation des protections AJAX
     */
    initSecurity() {
        // Intercepter toutes les requêtes fetch
        this.interceptFetch();
        
        // Intercepter toutes les requêtes XMLHttpRequest
        this.interceptXHR();
        
        // Protection contre les attaques par timing
        this.addTimingProtection();
    }

    /**
     * Intercepter les requêtes fetch
     */
    interceptFetch() {
        const originalFetch = window.fetch;
        const self = this;

        window.fetch = function(url, options = {}) {
            // Validation de sécurité
            if (!self.validateRequest(url, options)) {
                return Promise.reject(new Error('Requête bloquée par la sécurité'));
            }

            // Ajouter les headers de sécurité
            options = self.addSecurityHeaders(options);

            // Rate limiting
            if (!self.checkRateLimit()) {
                return Promise.reject(new Error('Trop de requêtes. Veuillez patienter.'));
            }

            // Log de la requête
            self.logRequest(url, options);

            // Exécuter la requête avec protection
            return self.executeSecureRequest(() => originalFetch(url, options));
        };
    }

    /**
     * Intercepter les requêtes XMLHttpRequest
     */
    interceptXHR() {
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;
        const self = this;

        XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
            // Validation de sécurité
            if (!self.validateXHRRequest(method, url)) {
                throw new Error('Requête XHR bloquée par la sécurité');
            }

            // Stocker les informations pour la validation
            this._secureMethod = method;
            this._secureUrl = url;

            return originalOpen.call(this, method, url, async, user, password);
        };

        XMLHttpRequest.prototype.send = function(data) {
            // Validation des données
            if (data && !self.validateRequestData(data)) {
                throw new Error('Données de requête invalides');
            }

            // Rate limiting
            if (!self.checkRateLimit()) {
                throw new Error('Trop de requêtes. Veuillez patienter.');
            }

            // Log de la requête
            self.logRequest(this._secureUrl, { method: this._secureMethod, data: data });

            // Ajouter les headers de sécurité
            this.setRequestHeader('X-CSRF-Token', self.csrfToken);
            this.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            return originalSend.call(this, data);
        };
    }

    /**
     * Valider une requête
     */
    validateRequest(url, options) {
        // Vérifier l'URL
        if (!this.isValidUrl(url)) {
            this.logSecurityEvent('URL invalide détectée', { url: url });
            return false;
        }

        // Vérifier la méthode HTTP
        const method = options.method || 'GET';
        if (!this.isValidMethod(method)) {
            this.logSecurityEvent('Méthode HTTP invalide', { method: method });
            return false;
        }

        // Vérifier les données
        if (options.body) {
            if (!this.validateRequestData(options.body)) {
                this.logSecurityEvent('Données de requête invalides');
                return false;
            }
        }

        return true;
    }

    /**
     * Valider une requête XHR
     */
    validateXHRRequest(method, url) {
        return this.isValidUrl(url) && this.isValidMethod(method);
    }

    /**
     * Valider une URL
     */
    isValidUrl(url) {
        try {
            const urlObj = new URL(url, window.location.origin);
            // Vérifier que l'URL est sur le même domaine
            return urlObj.origin === window.location.origin;
        } catch {
            return false;
        }
    }

    /**
     * Valider une méthode HTTP
     */
    isValidMethod(method) {
        const allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        return allowedMethods.includes(method.toUpperCase());
    }

    /**
     * Valider les données de requête
     */
    validateRequestData(data) {
        if (typeof data === 'string') {
            // Vérifier les injections dans les chaînes
            return this.validateString(data);
        } else if (data instanceof FormData) {
            // Vérifier les données de formulaire
            return this.validateFormData(data);
        } else if (typeof data === 'object') {
            // Vérifier les objets JSON
            return this.validateObject(data);
        }
        return true;
    }

    /**
     * Valider une chaîne de caractères
     */
    validateString(str) {
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

        return !dangerousPatterns.some(pattern => pattern.test(str));
    }

    /**
     * Valider les données de formulaire
     */
    validateFormData(formData) {
        for (let [key, value] of formData.entries()) {
            if (typeof value === 'string' && !this.validateString(value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Valider un objet
     */
    validateObject(obj) {
        for (let key in obj) {
            if (obj.hasOwnProperty(key)) {
                if (typeof obj[key] === 'string' && !this.validateString(obj[key])) {
                    return false;
                } else if (typeof obj[key] === 'object' && !this.validateObject(obj[key])) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Ajouter les headers de sécurité
     */
    addSecurityHeaders(options) {
        options.headers = options.headers || {};
        
        // Token CSRF
        if (this.csrfToken) {
            options.headers['X-CSRF-Token'] = this.csrfToken;
        }

        // Header pour identifier les requêtes AJAX
        options.headers['X-Requested-With'] = 'XMLHttpRequest';

        // Content-Type par défaut
        if (!options.headers['Content-Type'] && options.body) {
            if (options.body instanceof FormData) {
                // Ne pas définir Content-Type pour FormData (sera défini automatiquement)
            } else {
                options.headers['Content-Type'] = 'application/json';
            }
        }

        return options;
    }

    /**
     * Rate limiting pour les requêtes AJAX
     */
    checkRateLimit() {
        const now = Date.now();
        const timeWindow = 60000; // 1 minute
        const maxRequests = 60; // 60 requêtes par minute

        // Nettoyer les anciennes requêtes
        this.requestQueue = this.requestQueue.filter(time => now - time < timeWindow);

        // Vérifier la limite
        if (this.requestQueue.length >= maxRequests) {
            this.logSecurityEvent('Rate limit AJAX dépassé');
            return false;
        }

        // Ajouter la requête actuelle
        this.requestQueue.push(now);
        return true;
    }

    /**
     * Exécuter une requête sécurisée
     */
    async executeSecureRequest(requestFunction) {
        // Vérifier le nombre de requêtes concurrentes
        if (this.activeRequests >= this.maxConcurrentRequests) {
            throw new Error('Trop de requêtes simultanées');
        }

        this.activeRequests++;

        try {
            const response = await requestFunction();
            
            // Vérifier la réponse
            if (!this.validateResponse(response)) {
                throw new Error('Réponse invalide reçue');
            }

            return response;
        } finally {
            this.activeRequests--;
        }
    }

    /**
     * Valider une réponse
     */
    validateResponse(response) {
        // Vérifier le type de contenu
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            // Pour les réponses JSON, on ne peut pas les valider ici
            // car elles sont asynchrones
            return true;
        }

        return true;
    }

    /**
     * Logger une requête
     */
    logRequest(url, options) {
        const logData = {
            url: url,
            method: options.method || 'GET',
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent
        };

        this.logSecurityEvent('Requête AJAX', logData);
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

        // Envoyer au serveur
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

        console.warn('AJAX Security Event:', event);
    }

    /**
     * Stocker un événement de sécurité
     */
    storeSecurityEvent(event) {
        const events = JSON.parse(localStorage.getItem('ajax_security_events') || '[]');
        events.push(event);
        
        // Garder seulement les 50 derniers événements
        if (events.length > 50) {
            events.splice(0, events.length - 50);
        }
        
        localStorage.setItem('ajax_security_events', JSON.stringify(events));
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
     * Protection contre les attaques par timing
     */
    addTimingProtection() {
        // Ajouter un délai aléatoire pour éviter les attaques par timing
        const originalFetch = window.fetch;
        const self = this;

        window.fetch = function(...args) {
            return new Promise((resolve, reject) => {
                // Délai aléatoire entre 10ms et 50ms
                const delay = Math.random() * 40 + 10;
                
                setTimeout(() => {
                    originalFetch.apply(this, args)
                        .then(resolve)
                        .catch(reject);
                }, delay);
            });
        };
    }

    /**
     * Fonction utilitaire pour les requêtes sécurisées
     */
    secureRequest(url, options = {}) {
        return new Promise((resolve, reject) => {
            // Validation
            if (!this.validateRequest(url, options)) {
                reject(new Error('Requête invalide'));
                return;
            }

            // Rate limiting
            if (!this.checkRateLimit()) {
                reject(new Error('Rate limit dépassé'));
                return;
            }

            // Headers de sécurité
            options = this.addSecurityHeaders(options);

            // Exécuter la requête
            fetch(url, options)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response;
                })
                .then(resolve)
                .catch(error => {
                    this.logSecurityEvent('Erreur de requête AJAX', { error: error.message });
                    reject(error);
                });
        });
    }

    /**
     * Fonction pour les requêtes GET sécurisées
     */
    secureGet(url, options = {}) {
        options.method = 'GET';
        return this.secureRequest(url, options);
    }

    /**
     * Fonction pour les requêtes POST sécurisées
     */
    securePost(url, data, options = {}) {
        options.method = 'POST';
        if (data) {
            if (typeof data === 'object' && !(data instanceof FormData)) {
                options.body = JSON.stringify(data);
            } else {
                options.body = data;
            }
        }
        return this.secureRequest(url, options);
    }
}

// Initialiser la sécurité AJAX
const ajaxSecurity = new AjaxSecurity();

// Exposer les fonctions de sécurité AJAX
window.SecureAjax = {
    get: (url, options) => ajaxSecurity.secureGet(url, options),
    post: (url, data, options) => ajaxSecurity.securePost(url, data, options),
    request: (url, options) => ajaxSecurity.secureRequest(url, options)
};

// Protection contre la modification
Object.freeze(window.SecureAjax); 