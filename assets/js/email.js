/**
 * Email verification helpers (code via Gmail/inbox).
 * - checkEmailExists: avoid redundant registration if email already used
 * - requestVerificationCode: send 6-digit code to email (backend uses PHP mail() or SMTP)
 * - verifyEmailCode: validate code before completing registration
 *
 * For production: configure PHP mail() or SMTP in php.ini / your server.
 * Optional: integrate EmailJS (https://www.emailjs.com/) from backend to send via Gmail SMTP.
 */

const API = 'api';
 
async function checkEmailExists(email) {
    try {
        const res = await fetch(`${API}/check_email.php?email=${encodeURIComponent(email)}`);
        const data = await res.json();
        if (!res.ok || !data.success) {
            return { exists: null, error: data.message || 'Failed to validate email' };
        }
        return { exists: !!data.exists };
    } catch (err) {
        return { exists: null, error: 'Could not reach server' };
    }
}

async function requestVerificationCode(email) {
    const res = await fetch(`${API}/send_verification_code.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
    });
    let data;
    try {
        data = await res.json();
    } catch (e) {
        const text = await res.text();
        return { ok: false, message: text ? `Server returned non-JSON response: ${text.slice(0, 120)}` : 'Server returned non-JSON response' };
    }
    return { ok: !!data.success, message: data.message || (data.success ? 'Code sent' : 'Failed') };
}

async function verifyEmailCode(email, code) {
    const res = await fetch(`${API}/verify_email_code.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, code })
    });
    const data = await res.json();
    return data.success ? { valid: data.valid } : { valid: false, error: data.message };
}

// Export for use in register.js / other pages
if (typeof window !== 'undefined') {
    window.checkEmailExists = checkEmailExists;
    window.requestVerificationCode = requestVerificationCode;
    window.verifyEmailCode = verifyEmailCode;
}
