// Register Page JavaScript
let emailVerified = false;

document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const toggleRegPassword = document.getElementById('toggleRegPassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const regPasswordInput = document.getElementById('regPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    // Send verification code (avoid redundant if email already used)
    const sendCodeBtn = document.getElementById('sendCodeBtn');
    const verifyCodeRow = document.getElementById('verifyCodeRow');
    const verificationCodeInput = document.getElementById('verificationCode');
    const verifyCodeBtn = document.getElementById('verifyCodeBtn');
    const verifyStatus = document.getElementById('verifyStatus');
    if (sendCodeBtn) {
        sendCodeBtn.addEventListener('click', async function() {
            const email = document.getElementById('email').value.trim();
            if (!email) {
                Swal.fire({ icon: 'warning', title: 'Enter email first', text: 'Please enter your email address.', confirmButtonColor: '#0a1628' });
                return;
            }
            const { exists, error } = await checkEmailExists(email);
            if (error || exists === null) {
                Swal.fire({
                    icon: 'error',
                    title: 'Email check failed',
                    text: error || 'Please try again.',
                    confirmButtonColor: '#0a1628'
                });
                return;
            }
            if (exists) {
                Swal.fire({ icon: 'error', title: 'Email already used', text: 'This email is already registered. Sign in or use another email.', confirmButtonColor: '#0a1628' });
                return;
            }
            sendCodeBtn.disabled = true;
            sendCodeBtn.textContent = 'Sending...';
            const { ok, message } = await requestVerificationCode(email);
            sendCodeBtn.disabled = false;
            sendCodeBtn.textContent = 'Send code';
            if (ok) {
                verifyCodeRow.style.display = 'block';
                verificationCodeInput.value = '';
                emailVerified = false;
                verifyStatus.style.display = 'none';
                Swal.fire({ icon: 'success', title: 'Code sent', text: message, timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Could not send', text: message, confirmButtonColor: '#0a1628' });
            }
        });
    }
    if (verifyCodeBtn && verificationCodeInput) {
        verifyCodeBtn.addEventListener('click', async function() {
            const email = document.getElementById('email').value.trim();
            const code = verificationCodeInput.value.trim();
            if (!code || code.length !== 6) {
                Swal.fire({ icon: 'warning', title: 'Enter 6-digit code', confirmButtonColor: '#0a1628' });
                return;
            }
            verifyCodeBtn.disabled = true;
            const { valid } = await verifyEmailCode(email, code);
            verifyCodeBtn.disabled = false;
            if (valid) {
                emailVerified = true;
                verifyStatus.style.display = 'inline';
                Swal.fire({ icon: 'success', title: 'Verified', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Invalid or expired', text: 'Request a new code and try again.', confirmButtonColor: '#0a1628' });
            }
        });
    }

    // Toggle password visibility
    toggleRegPassword.addEventListener('click', function() {
        const type = regPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        regPasswordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Handle register form submission
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const fullName = document.getElementById('fullName').value.trim();
        const studentId = document.getElementById('studentId').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('regPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const accountType = document.getElementById('accountType').value;

        // Password rules: at least 10 chars, upper, lower, numbers, NO special characters
        const hasMinLength = password.length >= 10;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);
        if (!hasMinLength || !hasUpper || !hasLower || !hasNumber || hasSpecial) {
            const msgs = [];
            if (!hasMinLength) msgs.push('At least 10 characters');
            if (!hasUpper) msgs.push('May uppercase letter');
            if (!hasLower) msgs.push('May lowercase letter');
            if (!hasNumber) msgs.push('May numbers');
            if (hasSpecial) msgs.push('Walang special character (letters and numbers only)');
            Swal.fire({
                icon: 'error',
                title: 'Invalid Password',
                html: msgs.join('<br>'),
                confirmButtonColor: '#0a1628'
            });
            return;
        }

        if (password !== confirmPassword) {
            Swal.fire({
                icon: 'error',
                title: 'Password Mismatch',
                text: 'Passwords do not match',
                confirmButtonColor: '#0a1628'
            });
            return;
        }

        if (!accountType) {
            Swal.fire({
                icon: 'error',
                title: 'Account Type Required',
                text: 'Please select an account type',
                confirmButtonColor: '#0a1628'
            });
            return;
        }

        // Show loading state
        const submitBtn = registerForm.querySelector('.btn-login-custom');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="loading"></span> Registering...';
        submitBtn.disabled = true;

        const verificationCode = (document.getElementById('verificationCode') || {}).value || '';
        const payload = {
            fullName: fullName,
            studentId: studentId,
            email: email,
            password: password,
            accountType: accountType
        };
        if (emailVerified && verificationCode) payload.verificationCode = verificationCode;

        fetch('api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload)
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Registration request failed');
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Registration Successful!',
                    text: 'Your account has been created successfully',
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
                    window.location.href = 'index.html';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Registration Failed',
                    text: data.message || 'Failed to create account. Please try again.',
                    confirmButtonColor: '#0a1628'
                });
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                text: error.message || 'Could not complete registration. Please try again.',
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
