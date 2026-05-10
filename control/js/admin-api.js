// SWAP Admin API - Communication with PHP Backend
const API = {
    baseUrl: '../backend/',
    
    async request(endpoint, method = 'POST', data = null) {
        let url = this.baseUrl + endpoint;
        
        const options = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };
        
        if (data && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
            options.body = JSON.stringify(data);
        } else if (data && method === 'GET') {
            const params = new URLSearchParams(data).toString();
            url += '?' + params;
        }
        
        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                return { success: false, message: 'خطأ في الاستجابة: ' + response.status };
            }
            const result = await response.json();
            return result;
        } catch (error) {
            return { success: false, message: 'خطأ في الاتصال: ' + error.message };
        }
    },
    
    // Session Management
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
        sessionStorage.removeItem('swap_user_role');
    },
    
    // Admin Stats
    async getAdminStats(year = null) {
        return await this.request('admin/admin-stats.php', 'GET', { year });
    },
    
    // Admin Students
    async getAdminStudents(year = null) {
        return await this.request('admin/admin-students.php', 'GET', { year });
    },
    
    async deleteStudent(studentId) {
        return await this.request('admin/admin-students.php', 'DELETE', { student_id: studentId });
    },
    
    // Admin Requests
    async getAdminRequests(year = null) {
        return await this.request('admin/admin-requests.php', 'GET', { year });
    },
    
    async deleteRequest(requestId) {
        return await this.request('admin/admin-requests.php', 'DELETE', { request_id: requestId });
    },
    
    async updateRequestStatus(requestId, status) {
        return await this.request('admin/admin-requests.php', 'PUT', { request_id: requestId, status: status });
    },
    
    // Admin Logs
    async getAdminLogs(year = null) {
        return await this.request('admin/admin-logs.php', 'GET', { year });
    }
};

window.API = API;