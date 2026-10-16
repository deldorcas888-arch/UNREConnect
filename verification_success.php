<?php
session_start();
require_once __DIR__ . '/admin/db_connect.php';
require_once __DIR__ . '/alumni/includes/verification.php';

$page_title = 'Email Verification';
$message = '';
$is_success = false;
$show_form = false;
$email = '';
$redirect_url = 'alumni_login.php';

// Check for success/error messages in session
if (isset($_SESSION['verification_message'])) {
    $message = $_SESSION['verification_message'];
    $is_success = $_SESSION['verification_success'] ?? false;
    $email = $_SESSION['verification_email'] ?? '';
    $show_form = !$is_success;
    
    // Clear the session variables
    unset($_SESSION['verification_message']);
    unset($_SESSION['verification_success']);
    unset($_SESSION['verification_email']);
}
// Process token if provided
elseif (isset($_GET['token'])) {
    try {
        // Process verification
        $verification = new VerificationHandler($conn);
        $result = $verification->verifyEmail($_GET['token']);
        
        $is_success = $result['success'];
        $message = $result['message'];
        $email = $result['email'] ?? '';
        
        // Store in session for redirection
        $_SESSION['verification_message'] = $message;
        $_SESSION['verification_success'] = $is_success;
        $_SESSION['verification_email'] = $email;
        
        // Redirect to prevent resubmission on refresh
        if ($is_success) {
            $redirect_url .= '?verify=1';
        }
        
        header("Location: $redirect_url");
        exit();
        
    } catch (Exception $e) {
        $message = 'An error occurred while processing your request. Please try again.';
        error_log('Verification error: ' . $e->getMessage());
        $show_form = true;
    }
} else {
    $message = 'Invalid verification link. Please use the link sent to your email.';
    $show_form = true;
}

// Include header after setting variables
include('includes/header.php');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-<?php echo $is_success ? 'success' : 'warning'; ?> text-white py-3">
                    <div class="d-flex align-items-center">
                        <i class="fas <?php echo $is_success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> fa-2x me-3"></i>
                        <div>
                            <h4 class="mb-0">
                                <?php echo $is_success ? 'Email Verified Successfully!' : 'Verification Required'; ?>
                            </h4>
                            <p class="mb-0 small opacity-75">
                                <?php echo $is_success ? 'Your account is now active' : 'Please verify your email to continue'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if ($is_success): ?>
                        <div class="text-center py-4">
                            <div class="position-relative d-inline-block mb-4">
                                <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; margin: 0 auto;">
                                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                                </div>
                                <div class="position-absolute top-0 start-100 translate-middle">
                                    <span class="badge rounded-pill bg-success">Verified</span>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-3">Email Verified Successfully!</h3>
                            <p class="text-muted mb-4">Thank you for verifying your email address.</p>
                        </div>
                        
                        <div class="alert alert-success border-0 bg-light-success">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 text-success" style="font-size: 1.5rem;"></i>
                                <div>
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $message; ?>
                            </p>
                        </div>
                        
                        <p>Thank you for verifying your email address. Your account is now active and ready to use.</p>
                        
                        <div class="d-grid gap-2 mt-4">
                            <a href="alumni_login.php?verify=1" class="btn btn-success btn-lg py-2">
                                <i class="fas fa-sign-in-alt me-2"></i> Continue to Login
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-2"></i> Back to Home
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="bg-light-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 120px; height: 120px;">
                                <i class="fas fa-envelope-open-text text-warning" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="fw-bold mb-3">Verification Required</h3>
                            <p class="text-muted">Please verify your email to continue using your account</p>
                        </div>
                        
                        <div class="alert alert-warning border-0 bg-light-warning">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle me-3 text-warning" style="font-size: 1.5rem;"></i>
                                <div>
                                    <?php echo nl2br(htmlspecialchars($message)); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($show_form && !empty($email)): ?>
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-envelope me-2"></i>Need a new verification email?</h5>
                                <p>We can send a new verification link to your email address.</p>
                                
                                <form id="resendForm" class="mt-3">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-paper-plane me-2"></i> Resend Verification Email
                                    </button>
                                </form>
                                <div id="resendMessage" class="mt-2"></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info mt-4">
                            <h5><i class="fas fa-question-circle me-2"></i>Need Help?</h5>
                            <p class="mb-0">
                                If you're having trouble verifying your email, please contact our support team at
                                <a href="mailto:support@unreconnect.edu.pg">support@unreconnect.edu.pg</a>.
                            </p>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                            <a href="index.php" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-home me-2"></i> Back to Home
                            </a>
                            <a href="alumni/signup.php" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i> Register Again
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add animation to the verification icon
function animateVerificationIcon() {
    const icon = document.querySelector('.fa-check-circle');
    if (icon) {
        icon.style.transform = 'scale(0.8)';
        setTimeout(() => {
            icon.style.transition = 'all 0.5s ease-in-out';
            icon.style.transform = 'scale(1.1)';
            setTimeout(() => {
                icon.style.transform = 'scale(1)';
            }, 500);
        }, 100);
    }
}

// Handle contact support click
document.addEventListener('DOMContentLoaded', function() {
    // Animate the verification icon on page load
    animateVerificationIcon();
    
    // Handle contact support click
    const contactSupport = document.getElementById('contactSupport');
    if (contactSupport) {
        contactSupport.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Please email support@unreconnect.com for assistance with email verification.');
        });
    }
    
    // Handle form submission
    const resendForm = document.getElementById('resendForm');
    if (resendForm) {
        resendForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(resendForm);
            const email = formData.get('email');
            const submitBtn = resendForm.querySelector('button[type="submit"]');
            const messageDiv = document.getElementById('resendMessage');
            
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
            
            try {
                const response = await fetch('../api/resend_verification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'A new verification email has been sent to ' + email, messageDiv);
                } else {
                    showAlert('danger', result.message || 'Failed to resend verification email.', messageDiv);
                }
            } catch (error) {
                showAlert('danger', 'An error occurred. Please try again later.', messageDiv);
                console.error('Error:', error);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
    
    function showAlert(type, message, container) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} mt-2 mb-0`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
            ${message}
        `;
        
        // Clear existing alerts
        container.innerHTML = '';
        container.appendChild(alertDiv);
    }
});
</script>

<?php include('includes/footer.php'); ?>
