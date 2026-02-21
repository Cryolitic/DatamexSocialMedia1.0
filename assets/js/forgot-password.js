// Forgot Password Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');

    // Handle forgot password form submission
    forgotPasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const studentId = document.getElementById('forgotStudentId').value.trim();
        const email = document.getElementById('forgotEmail').value.trim();

        // Show loading state
        const submitBtn = forgotPasswordForm.querySelector('.btn-login-custom');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="loading"></span> Sending...';
        submitBtn.disabled = true;

        // Simulate API call
        fetch('api/forgot_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                studentId: studentId,
                email: email
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Reset Link Sent!',
                    html: 'We have sent a password reset link to your email address.<br>Please check your inbox.',
                    confirmButtonColor: '#0a1628'
                }).then(() => {
                    window.location.href = 'index.html';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to send reset link. Please check your credentials.',
                    confirmButtonColor: '#0a1628'
                });
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Demo mode - check if user exists
            const registeredUsers = JSON.parse(localStorage.getItem('registeredUsers') || '[]');
            const defaultUsers = ['admin1', 'user1'];
            
            const userExists = registeredUsers.find(u => 
                (u.studentId === studentId && u.email === email) ||
                (defaultUsers.includes(studentId.toLowerCase()) && email.includes('@'))
            );

            if (userExists || defaultUsers.includes(studentId.toLowerCase())) {
                Swal.fire({
                    icon: 'success',
                    title: 'Reset Link Sent!',
                    html: 'We have sent a password reset link to your email address.<br>Please check your inbox.<br><br><small>(Demo Mode: In production, this would send an actual email)</small>',
                    confirmButtonColor: '#0a1628'
                }).then(() => {
                    window.location.href = 'index.html';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Account Not Found',
                    text: 'No account found with the provided Student ID and Email. Please check your credentials.',
                    confirmButtonColor: '#0a1628'
                });
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    });
});
