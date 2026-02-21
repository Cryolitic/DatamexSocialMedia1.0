// Login Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    function getLimiterKey(username) {
        return `loginLimiter:${(username || '').toLowerCase()}`;
    }

    function getLimiter(username) {
        try {
            return JSON.parse(localStorage.getItem(getLimiterKey(username)) || 'null');
        } catch {
            return null;
        }
    }

    function setLimiter(username, limiter) {
        localStorage.setItem(getLimiterKey(username), JSON.stringify(limiter));
    }

    function clearLimiter(username) {
        localStorage.removeItem(getLimiterKey(username));
    }

    function isLocked(username) {
        const limiter = getLimiter(username);
        if (!limiter || !limiter.lockUntil) return { locked: false };
        const until = new Date(limiter.lockUntil).getTime();
        const now = Date.now();
        if (until > now) {
            return { locked: true, seconds: Math.ceil((until - now) / 1000), level: limiter.lockLevel || 0 };
        }
        return { locked: false, limiter };
    }

    // Requirement:
    // - 3 fails => 60 seconds lock
    // - after 60 seconds, if wrong again => lock 1 day
    function recordFailure(username) {
        const current = getLimiter(username) || { attempts: 0, lockLevel: 0, lockUntil: null };
        const lockCheck = isLocked(username);
        if (lockCheck.locked) return;

        if (current.lockLevel === 1 && current.lockUntil) {
            // temp lock already happened; next failure after unlock => 1 day lock
            current.lockLevel = 2;
            current.attempts = 0;
            current.lockUntil = new Date(Date.now() + 86400 * 1000).toISOString();
            setLimiter(username, current);
            return;
        }

        current.attempts = (current.attempts || 0) + 1;
        if (current.attempts >= 3) {
            current.lockLevel = 1;
            current.attempts = 0;
            current.lockUntil = new Date(Date.now() + 60 * 1000).toISOString();
        }
        setLimiter(username, current);
    }

    function recordSuccess(username) {
        clearLimiter(username);
    }

    // Toggle password visibility
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Handle login form submission
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        // Default credentials
        const defaultUsers = {
            'admin1': { password: 'password123', isAdmin: true, name: 'Admin User' },
            'user1': { password: 'password123', isAdmin: false, name: 'Regular User' }
        };

        // Show loading state
        const submitBtn = loginForm.querySelector('.btn-login-custom');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="loading"></span> Signing in...';
        submitBtn.disabled = true;

        // Client-side lockout (covers demo/local users too)
        const lockState = isLocked(username);
        if (lockState.locked) {
            Swal.fire({
                icon: 'error',
                title: lockState.level === 2 ? 'Locked for 1 day' : 'Locked for 60 seconds',
                text: `Please wait ${lockState.seconds} seconds before trying again.`,
                confirmButtonColor: '#0a1628'
            });
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            return;
        }

        // Check default credentials first
        if (defaultUsers[username.toLowerCase()] && defaultUsers[username.toLowerCase()].password === password) {
            const userData = defaultUsers[username.toLowerCase()];
            setTimeout(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Welcome!',
                    text: 'Login successful',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    recordSuccess(username);
                    localStorage.setItem('user', JSON.stringify({
                        id: username.toLowerCase() === 'admin1' ? 1 : 2,
                        username: username.toLowerCase(),
                        name: userData.name,
                        email: username.toLowerCase() + '@dcsa.edu',
                        avatar: 'assets/images/default-avatar.png',
                        accountType: username.toLowerCase() === 'admin1' ? 'admin' : 'student',
                        isAdmin: userData.isAdmin
                    }));
                    localStorage.setItem('isAdmin', userData.isAdmin);
                    window.location.href = 'home.html';
                });
            }, 500);
            return;
        }

        // Check registered users from localStorage
        const registeredUsers = JSON.parse(localStorage.getItem('registeredUsers') || '[]');
        const registeredUser = registeredUsers.find(u => 
            (u.studentId === username || u.email === username) && u.password === password
        );

        if (registeredUser) {
            setTimeout(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Welcome!',
                    text: 'Login successful',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    recordSuccess(username);
                    localStorage.setItem('user', JSON.stringify({
                        id: registeredUser.id,
                        username: registeredUser.studentId,
                        name: registeredUser.fullName,
                        email: registeredUser.email,
                        avatar: 'assets/images/default-avatar.png',
                        accountType: registeredUser.accountType || 'student',
                        isAdmin: registeredUser.accountType === 'admin'
                    }));
                    localStorage.setItem('isAdmin', registeredUser.accountType === 'admin');
                    window.location.href = 'home.html';
                });
            }, 500);
            return;
        }

        // Try API call
        fetch('api/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: username,
                password: password,
                remember: false
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Welcome!',
                    text: 'Login successful',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    if (data.user) {
                        recordSuccess(username);
                        localStorage.setItem('user', JSON.stringify(data.user));
                        localStorage.setItem('isAdmin', data.user.isAdmin || false);
                    }
                    window.location.href = 'home.html';
                });
            } else {
                // If backend says lock, mirror it in client limiter for consistent behavior
                if (data.lock && data.lock.until) {
                    setLimiter(username, {
                        attempts: 0,
                        lockLevel: data.lock.level || 0,
                        lockUntil: new Date(data.lock.until).toISOString()
                    });
                } else {
                    recordFailure(username);
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Login Failed',
                    text: data.message || 'Invalid username or password',
                    confirmButtonColor: '#0a1628'
                });
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            recordFailure(username);
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: 'Invalid username or password. Try: admin1/password123 or user1/password123',
                confirmButtonColor: '#0a1628'
            });
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    // Check if user is already logged in
    if (localStorage.getItem('user')) {
        window.location.href = 'home.html';
    }
});
