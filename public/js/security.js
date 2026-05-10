// Security utilities for SWAP
const Security = {
    // Simple hash function (for demo - not for production)
    hash: (str) => {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return 'hashed_' + Math.abs(hash).toString(16);
    },
    
    // Verify password with hash
    verifyPassword: (inputPassword, storedHash) => {
        return Security.hash(inputPassword) === storedHash;
    },
    
    // Validate password strength
    validatePassword: (password) => {
        const issues = [];
        if (password.length < 6) issues.push('يجب أن تكون 6 أحرف على الأقل');
        if (!/[A-Z]/.test(password)) issues.push('تحتوي على حرف كبير');
        if (!/[0-9]/.test(password)) issues.push('تحتوي على رقم');
        return { valid: issues.length === 0, issues };
    },
    
    // Sanitize input to prevent XSS
    sanitize: (str) => {
        if (typeof str !== 'string') return '';
        return str.replace(/[<>\"'&]/g, (char) => {
            const entities = {
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#x27;',
                '&': '&amp;'
            };
            return entities[char] || char;
        });
    },
    
    // Rate limiting check (simple implementation)
    checkRateLimit: (key, maxAttempts = 5, windowMs = 60000) => {
        const now = Date.now();
        const attempts = JSON.parse(localStorage.getItem('rate_limit_' + key) || '[]');
        const recentAttempts = attempts.filter(t => now - t < windowMs);
        
        if (recentAttempts.length >= maxAttempts) {
            return { blocked: true, remainingTime: windowMs - (now - recentAttempts[0]) };
        }
        
        recentAttempts.push(now);
        localStorage.setItem('rate_limit_' + key, JSON.stringify(recentAttempts));
        return { blocked: false };
    },
    
    // Generate secure token
    generateToken: () => {
        return 'token_' + Date.now() + '_' + Math.random().toString(36).substr(2);
    },
    
    // Store session securely
    setSession: (user) => {
        const session = {
            user: {
                id: user.id,
                name: user.name,
                university_id: user.university_id,
                year: user.year,
                email: user.email
            },
            token: Security.generateToken(),
            created: Date.now()
        };
        localStorage.setItem('swap_session', JSON.stringify(session));
        return session;
    },
    
    // Verify session
    getSession: () => {
        const session = JSON.parse(localStorage.getItem('swap_session') || 'null');
        if (!session) return null;
        
        // Session expires after 24 hours
        if (Date.now() - session.created > 86400000) {
            localStorage.removeItem('swap_session');
            return null;
        }
        return session;
    },
    
    // Clear session
    clearSession: () => {
        localStorage.removeItem('swap_session');
    }
};

// Make available globally
window.Security = Security;