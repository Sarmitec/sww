// SWAP API - Communication with PHP Backend
const API = {
     get baseUrl() {
         // Simple fixed path - SWAP is in /SWAP/ folder
         return '/SWAP/backend/';
     },
    
async request(endpoint, method = 'POST', data = null) {
         let url = this.baseUrl + endpoint;
         
         const options = {
             method: method,
             headers: { 'Content-Type': 'application/json' },
             credentials: 'include'
         };
         
         if (data && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
             options.body = JSON.stringify(data);
         } else if (data && Object.keys(data).length > 0 && method === 'GET') {
             const params = new URLSearchParams(data).toString();
             url += '?' + params;
         }
         
         console.log('API Request:', method, url, data);
        
        try {
            const response = await fetch(url, options);
            
            if (!response.ok) {
                let errorMsg = 'HTTP ' + response.status;
                try {
                    const errorData = await response.json();
                    errorMsg = errorData.message || errorMsg;
                } catch (e) {
                    errorMsg = response.statusText || errorMsg;
                }
                return { success: false, message: errorMsg };
            }
            
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            return { 
                success: false, 
                message: 'خطأ في الاتصال: ' + error.message 
            };
        }
    },
    
    // Login
    async login(university_id, password) {
        return await this.request('login.php', 'POST', { university_id, password });
    },
    
    // Register
    async register(name, university_id, year, email, password, confirm_password) {
        return await this.request('register.php', 'POST', { name, university_id, year, email, password, confirm_password });
    },
    
    // Verify Email
    async verifyEmail(email, code) {
        return await this.request('verify.php', 'POST', { email, code });
    },
    
    // Resend Verification Code
    async resendVerification(email) {
        return await this.request('resend-verify.php', 'POST', { email });
    },
    
    // Logout
    async logout() {
        return await this.request('logout.php', 'POST');
    },
    
    // Get Requests
    async getRequests(year) {
        return await this.request('requests.php', 'GET', { year });
    },
    
    // Get My Request
    async getMyRequest(student_id) {
        return await this.request('requests.php', 'GET', { student_id });
    },
    
    // Submit Request
    async submitRequest(student_id, current_section, desired_section) {
        return await this.request('requests.php', 'POST', { 
            student_id, current_section, desired_section 
        });
    },
    
    // Cancel Request
    async cancelRequest(request_id, student_id) {
        return await this.request('requests.php', 'DELETE', { request_id, student_id });
    },
    
    // Get Profile
    async getProfile(student_id) {
        return await this.request('profile.php', 'GET', { student_id });
    },
    
    // Update Profile
    async updateProfile(student_id, phone, facebook, email) {
        return await this.request('profile.php', 'PUT', { student_id, phone, facebook, email });
    },
    
    // Get Contact Info
    async getContact(student_id) {
        return await this.request('contact.php', 'GET', { student_id });
    },
    
    // Upload Profile Image
    async uploadProfileImage(student_id, file) {
        const formData = new FormData();
        formData.append('student_id', student_id);
        formData.append('profile_image', file);
        
        try {
            const response = await fetch(this.baseUrl + 'upload.php', {
                method: 'POST',
                body: formData
            });
            return await response.json();
        } catch (error) {
            console.error('Upload Error:', error);
            return { success: false, message: 'حدث خطأ في رفع الصورة' };
        }
    },
    
// ====== Owner API Methods ======
     // Get all users (owner)
     async getOwnerUsers(year) {
         // أرسل السنة فقط إذا كانت رقم صحيح بين 1 و5
         let params = {};
         // استخدم parseInt للتأكد من أن القيمة رقمية صحيحة
         const yearNum = parseInt(year);
         if (!isNaN(yearNum) && yearNum >= 1 && yearNum <= 5) {
             params.year = yearNum;
         }
         console.log('getOwnerUsers called with year:', year, 'parsed:', yearNum, 'params:', params);
         return await this.request('owner/owner-users.php', 'GET', params);
     },
    
    // Create user (owner)
    async createOwnerUser(userData) {
        return await this.request('owner/owner-users.php', 'POST', userData);
    },
    
    // Update user role (owner)
    async updateOwnerUserRole(student_id, role) {
        return await this.request('owner/owner-users.php', 'PUT', { student_id, role });
    },
    
    // Delete user (owner)
    async deleteOwnerUser(student_id) {
        return await this.request('owner/owner-users.php', 'DELETE', { student_id });
    },
    
    // Get owner stats
    async getOwnerStats(year) {
        return await this.request('owner/owner-stats.php', 'GET', year ? { year } : {});
    },
    
    // Get all requests (owner)
    async getOwnerRequests(year) {
        return await this.request('owner/owner-requests.php', 'GET', year ? { year } : {});
    },
    
    // Update request status (owner)
    async updateOwnerRequest(request_id, status) {
        return await this.request('owner/owner-requests.php', 'PUT', { request_id, status });
    },
    
    // Delete request (owner)
    async deleteOwnerRequest(request_id) {
        return await this.request('owner/owner-requests.php', 'DELETE', { request_id });
    },
    
    // Get logs (owner)
    async getOwnerLogs(year, limit) {
        const params = {};
        if (year) params.year = year;
        if (limit) params.limit = limit;
        return await this.request('owner/owner-logs.php', 'GET', params);
    },
    
    // Session Management
    setSession(user, token) {
        sessionStorage.setItem('swap_token', token);
        if (user.role) {
            sessionStorage.setItem('swap_user_role', user.role);
        }
    },
    
    async getSession() {
        const token = sessionStorage.getItem('swap_token');
        if (!token) return null;
        
        try {
            const result = await this.request('session.php', 'GET');
            return result.success ? result.student : null;
        } catch (e) {
            return null;
        }
    },
    
    clearSession() {
        sessionStorage.removeItem('swap_token');
    }
};

// Make available globally
window.API = API;
