<?php
// Start session
session_start();

// Include database connection and alumni matcher
require_once 'admin/db_connect.php';
require_once 'includes/alumni_matcher.php';

// Initialize variables
$errors = [];
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $firstname = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $graduation_year = (int)($_POST['graduation_year'] ?? 0);
    $program = trim($_POST['program'] ?? '');
    
    // Professional information
    $company = trim($_POST['company'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $is_current = isset($_POST['is_current']) ? 1 : 0;
    $end_date = !$is_current && !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $description = trim($_POST['description'] ?? '');

    // Validate input
    $required_fields = [
        'firstname' => 'First name',
        'lastname' => 'Last name',
        'gender' => 'Gender',
        'dob' => 'Date of birth',
        'email' => 'Email address',
        'contact_number' => 'Contact number',
        'address' => 'Complete address',
        'username' => 'Username',
        'program' => 'Program/course',
        'graduation_year' => 'Graduation year'
    ];
    
    foreach ($required_fields as $field => $label) {
        if (empty($$field)) {
            $errors[] = "$label is required";
        }
    }
    
    // Additional validations
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if ($graduation_year < 1950 || $graduation_year > (date('Y') + 5)) {
        $errors[] = 'Please enter a valid graduation year';
    }
    
    // Validate date of birth (must be at least 16 years old)
    if (!empty($dob)) {
        $dobDate = new DateTime($dob);
        $today = new DateTime();
        $minDob = (new DateTime())->modify('-100 years');
        $maxDob = (new DateTime())->modify('-16 years');
        
        if ($dobDate < $minDob || $dobDate > $maxDob) {
            $errors[] = 'Please enter a valid date of birth (you must be at least 16 years old)';
        }
    }
    
    // Validate dates if provided
    if (!empty($start_date)) {
        $startDate = new DateTime($start_date);
        $today = new DateTime();
        
        if ($startDate > $today) {
            $errors[] = 'Start date cannot be in the future';
        }
        
        if (!empty($end_date) && !$is_current) {
            $endDate = new DateTime($end_date);
            
            if ($endDate < $startDate) {
                $errors[] = 'End date cannot be before start date';
            }
            
            if ($endDate > $today) {
                $errors[] = 'End date cannot be in the future';
            }
        }
    }

    if (empty($errors)) {
        // First, prepare user data for alumni matching
        $userData = [
            'firstname' => $firstname,
            'middlename' => $middlename,
            'lastname' => $lastname,
            'dob' => $dob,
            'email' => $email,
            'contact' => $contact_number,
            'graduation_year' => $graduation_year,
            'program' => $program
        ];
        
        // Check if this user matches any alumni records
        $matchingAlumni = findMatchingAlumni($conn, $userData);
        error_log('Found ' . count($matchingAlumni) . ' potential alumni matches');
        
        // Check if email or username already exists
        $stmt = $conn->prepare("SELECT users_id, email, username, account_status FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $existingUser = $result->fetch_assoc();
            $stmt->close();
            
            // If we have a matching alumni record, update the existing account
            if (!empty($matchingAlumni)) {
                error_log('Found matching alumni record, checking for auto-approval');
                $autoApprove = shouldAutoApprove($matchingAlumni);
                error_log('Auto-approval status: ' . ($autoApprove ? 'Approved' : 'Not Approved'));
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                try {
                    $conn->begin_transaction();
                    
                    // Prepare the update query
                    $updateFields = array();
                    $updateTypes = '';
                    $updateParams = array();
                    
                    // Always update these fields
                    $updateFields[] = "firstname = ?";
                    $updateFields[] = "middlename = ?";
                    $updateFields[] = "lastname = ?";
                    $updateFields[] = "gender = ?";
                    $updateFields[] = "dob = ?";
                    $updateFields[] = "contact = ?";
                    $updateFields[] = "address = ?";
                    $updateFields[] = "password = ?";
                    
                    $updateTypes .= 'ssssssss';
                    $updateParams = array_merge($updateParams, array(
                        $firstname, $middlename, $lastname,
                        $gender, $dob, $contact_number, $address,
                        $hashed_password
                    ));
                    
                    // If auto-approving, update status and verification
                    if ($autoApprove) {
                        $updateFields[] = "account_status = 'Approved'";
                        $updateFields[] = "verification_status = 'Verified'";
                        $updateFields[] = "email_verified = 1";
                        $updateFields[] = "email_verified_at = NOW()";
                        
                        // Log the auto-approval
                        error_log("Auto-approving account for user ID: " . $existingUser['users_id'] . 
                                " with match score: " . $matchingAlumni['match_score']);
                    }
                    
                    // Add user ID to parameters for WHERE clause
                    $updateTypes .= 'i';
                    $updateParams[] = $existingUser['users_id'];
                    
                    // Build the update query
                    $updateQuery = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE users_id = ?";
                    
                    // Log the query and parameters for debugging
                    error_log("Update Query: " . $updateQuery);
                    error_log("Parameters: " . print_r($updateParams, true));
                    error_log("Parameter Types: " . $updateTypes);
                    
                    // Prepare the statement
                    $updateUser = $conn->prepare($updateQuery);
                    if ($updateUser === false) {
                        throw new Exception("Failed to prepare update query: " . $conn->error);
                    }
                    
                    // Bind parameters using call_user_func_array with references
                    $paramReferences = array($updateTypes);
                    foreach ($updateParams as $key => $value) {
                        $paramReferences[] = &$updateParams[$key];
                    }
                    
                    $bindResult = call_user_func_array(array($updateUser, 'bind_param'), $paramReferences);
                    if ($bindResult === false) {
                        throw new Exception("Failed to bind parameters: " . $updateUser->error);
                    }
                    
                    if (!$updateUser->execute()) {
                        throw new Exception("Failed to update user: " . $updateUser->error);
                    }
                    
                    // Log the update
                    error_log("Updated user account for ID: " . $existingUser['users_id'] . 
                             ". Auto-approved: " . ($autoApprove ? 'Yes' : 'No'));
                    
                    $updateUser->close();
                    
                    // Update or insert into alumni_profiles table
                    $profileStmt = $conn->prepare("INSERT INTO alumni_profiles (
                        users_id, student_id, program, graduation_year, 
                        employment_status, current_company, position, 
                        industry, location, phone, bio, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, 'Other', ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        program = VALUES(program),
                        graduation_year = VALUES(graduation_year),
                        current_company = VALUES(current_company),
                        position = VALUES(position),
                        industry = VALUES(industry),
                        location = VALUES(location),
                        phone = VALUES(phone),
                        bio = VALUES(bio),
                        updated_at = NOW()");
                    $profileStmt->bind_param(
                        "ississssss", 
                        $existingUser['users_id'], $username, $program, $graduation_year,
                        $company, $position, $industry, $location, $contact_number, $description
                    );
                    $profileStmt->execute();
                    $profileStmt->close();
                    
                    // Insert employment history if company is provided
                    if (!empty($company) && !empty($position) && !empty($start_date)) {
                        $stmt = $conn->prepare("INSERT INTO alumni_employment (
                            user_id, company, position, industry, location, 
                            start_date, end_date, is_current, description, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        
                        $stmt->bind_param(
                            "issssssss",
                            $existingUser['users_id'], $company, $position, $industry, $location,
                            $start_date, $end_date, $is_current, $description
                        );
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Set success message based on approval status
                    if ($autoApprove) {
                        $_SESSION['registration_success'] = [
                            'status' => 'approved',
                            'message' => 'Your account has been approved! You can now login with your credentials.'
                        ];
                    } else {
                        $_SESSION['registration_success'] = [
                            'status' => 'pending',
                            'message' => 'Your information has been updated. Your account is pending administrative approval.'
                        ];
                    }
                    
                    header('Location: registration_success.php');
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = 'An error occurred during account update. Please try again.';
                    error_log('Account update error: ' . $e->getMessage());
                }
            } else {
                // If we get here, the account exists but didn't match alumni records
                // Instead of showing an error, we'll treat this as a new registration attempt
                // but with auto-approval disabled
                $autoApprove = false;
                
                // Clear any existing errors to prevent the duplicate account message
                $errors = array_filter($errors, function($error) {
                    return strpos($error, 'Email or username already exists') === false;
                });
                
                // Continue with new account creation
                $accountStatus = $autoApprove ? 'Approved' : 'Pending';
                $verificationStatus = $autoApprove ? 'Verified' : 'Pending';
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $verification_code = md5(uniqid(rand(), true));
                $current_date = date('Y-m-d H:i:s');
                
                // Start transaction for new account creation
                $conn->begin_transaction();
                    
                    try {
                        // Insert into users table
                        $stmt = $conn->prepare("INSERT INTO users (
                            firstname, middlename, lastname, gender, dob, 
                            email, contact, address, username, password, 
                            role, account_status, verification_status, verification_code, date_created
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'alumnus', ?, ?, ?, ?)");
                        
                        $stmt->bind_param(
                            "ssssssssssssss", 
                            $firstname, $middlename, $lastname, $gender, $dob,
                            $email, $contact_number, $address, $username, $hashed_password,
                            $accountStatus, $verificationStatus, $verification_code, $current_date
                        );
                        
                        $stmt->execute();
                        $user_id = $conn->insert_id;
                        $stmt->close();
                        
                        // Insert into alumni_profiles table
                        $stmt = $conn->prepare("INSERT INTO alumni_profiles (
                            users_id, student_id, program, graduation_year, 
                            employment_status, current_company, position, 
                            industry, location, phone, bio, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, 'Other', ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        
                        $stmt->bind_param(
                            "ississssss", 
                            $user_id, $username, $program, $graduation_year,
                            $company, $position, $industry, $location, $contact_number, $description
                        );
                        $stmt->execute();
                        $stmt->close();
                        
                        // Insert employment history if company is provided
                        if (!empty($company) && !empty($position) && !empty($start_date)) {
                            $stmt = $conn->prepare("INSERT INTO alumni_employment (
                                user_id, company, position, industry, location, 
                                start_date, end_date, is_current, description, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                            
                            $stmt->bind_param(
                                "issssssss",
                                $user_id, $company, $position, $industry, $location,
                                $start_date, $end_date, $is_current, $description
                            );
                            $stmt->execute();
                            $stmt->close();
                        }
                        
                        // Commit transaction
                        $conn->commit();
                        
                        // Set success message
                        $message = $autoApprove 
                            ? 'Your account has been created and approved! You can now login with your credentials.'
                            : 'Thank you for registering! Your account is pending approval. You will receive an email once approved.';
                        
                        $_SESSION['registration_success'] = [
                            'status' => $autoApprove ? 'approved' : 'pending',
                            'message' => $message
                        ];
                        
                        header('Location: registration_success.php');
                        exit();
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $errors[] = 'An error occurred during registration. Please try again.';
                        error_log('Registration error: ' . $e->getMessage());
                    }
                }
            }
        } else {
            // No existing account found, proceed with new registration
            $stmt->close();
            
            // Determine account status and verification status based on matches
            $accountStatus = $autoApprove ? 'Approved' : 'Pending';
            $verificationStatus = $autoApprove ? 'Verified' : 'Pending';
            
            // Hash password for new registration
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verification_code = md5(uniqid(rand(), true));
            $current_date = date('Y-m-d H:i:s');
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert into users table
                $stmt = $conn->prepare("INSERT INTO users (
                    firstname, middlename, lastname, gender, dob, 
                    email, contact, address, username, password, 
                    role, account_status, verification_status, verification_code, date_created
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'alumnus', ?, ?, ?, ?)");
                
                $stmt->bind_param(
                    "ssssssssssssss", 
                    $firstname, $middlename, $lastname, $gender, $dob,
                    $email, $contact_number, $address, $username, $hashed_password,
                    $accountStatus, $verificationStatus, $verification_code, $current_date
                );
                
                $stmt->execute();
                $user_id = $conn->insert_id;
                $stmt->close();
                
                // Insert into alumni_profiles table
                $stmt = $conn->prepare("INSERT INTO alumni_profiles (
                    users_id, student_id, program, graduation_year, 
                    employment_status, current_company, position, 
                    industry, location, phone, bio, created_at, updated_at
                ) VALUES (?, ?, ?, ?, 'Other', ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $stmt->bind_param(
                    "ississssss", 
                    $user_id, $username, $program, $graduation_year,
                    $company, $position, $industry, $location, $contact_number, $description
                );
                $stmt->execute();
                $stmt->close();
                
                // Insert employment history if company is provided
                if (!empty($company) && !empty($position) && !empty($start_date)) {
                    $stmt = $conn->prepare("INSERT INTO alumni_employment (
                        user_id, company, position, industry, location, 
                        start_date, end_date, is_current, description, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    
                    $stmt->bind_param(
                        "issssssss",
                        $user_id, $company, $position, $industry, $location,
                        $start_date, $end_date, $is_current, $description
                    );
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Set success message based on approval status
                if ($autoApprove) {
                    $_SESSION['registration_success'] = [
                        'status' => 'approved',
                        'message' => 'Your account has been created successfully! You can now login with your credentials.'
                    ];
                } else {
                    $_SESSION['registration_success'] = [
                        'status' => 'pending',
                        'message' => 'Thank you for registering! Your account is pending approval. You will receive an email once approved.'
                    ];
                }
                
                header('Location: registration_success.php');
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'An error occurred during registration. Please try again.';
                error_log('Registration error: ' . $e->getMessage());
            }
        } // End if-else for existing user check
    } // End if (empty($errors))
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Account | UNREConnect</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --unre-green: #004d25;
            --unre-light-green: #e9f5ec;
            --unre-gold: #ffd700;
            --unre-dark: #212529;
            --unre-light: #f8f9fa;
            --unre-gray: #6c757d;
            --unre-border: #dee2e6;
            --unre-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            --unre-transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f5f5;
            color: var(--unre-dark);
            line-height: 1.6;
        }
        
        .registration-container {
            max-width: 800px;
            margin: 4rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: var(--unre-shadow);
            overflow: hidden;
        }
        
        .registration-header {
            background: var(--unre-green);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .registration-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .registration-header p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        .registration-form {
            padding: 2.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--unre-dark);
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--unre-border);
            border-radius: 6px;
            padding: 0.75rem 1rem;
            transition: var(--unre-transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--unre-green);
            box-shadow: 0 0 0 0.25rem rgba(0, 77, 37, 0.25);
        }
        
        .btn-primary {
            background-color: var(--unre-green);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--unre-transition);
        }
        
        .btn-primary:hover {
            background-color: #003d1e;
            transform: translateY(-2px);
        }
        
        .btn-outline-secondary {
            border-color: var(--unre-green);
            color: var(--unre-green);
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--unre-transition);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--unre-light-green);
            color: var(--unre-green);
        }
        
        .form-text {
            color: var(--unre-gray);
            font-size: 0.85rem;
        }
        
        .invalid-feedback {
            font-size: 0.85rem;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--unre-gray);
        }
        
        .login-link a {
            color: var(--unre-green);
            font-weight: 600;
            text-decoration: none;
            transition: var(--unre-transition);
        }
        
        .login-link a:hover {
            color: #003d1e;
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--unre-border);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .form-section h5 {
            color: var(--unre-green);
            margin-bottom: 1.5rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .form-section h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--unre-gold);
        }
        
        @media (max-width: 768px) {
            .registration-container {
                margin: 2rem 1rem;
                border-radius: 8px;
            }
            
            .registration-form {
                padding: 1.5rem;
            }
            
            .registration-header h1 {
                font-size: 1.75rem;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--unre-green);">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="/unreconnect/admin/assets/img/logo.png" alt="UNREConnect Logo" height="40" class="me-2">
                <span>UNREConnect</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="alumni_login.php"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Registration Form -->
    <div class="container py-5">
        <div class="registration-container fade-in">
            <div class="registration-header">
                <h1>Create Your Account</h1>
                <p>Join the UNREConnect alumni network today</p>
            </div>
            
            <div class="registration-form">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i> Please fix the following issues:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registrationForm" novalidate>
                    <div class="form-section">
                        <h5><i class="fas fa-user-circle me-2"></i> Personal Information</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="firstname" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="firstname" name="firstname" value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" required>
                                <div class="invalid-feedback">Please enter your first name</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="middlename" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middlename" name="middlename" value="<?= htmlspecialchars($_POST['middlename'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="lastname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="lastname" name="lastname" value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" required>
                                <div class="invalid-feedback">Please enter your last name</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="" disabled selected>Select gender</option>
                                    <option value="Male" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                                    <option value="Prefer not to say" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Prefer not to say') ? 'selected' : '' ?>>Prefer not to say</option>
                                </select>
                                <div class="invalid-feedback">Please select your gender</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dob" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" required>
                                <div class="invalid-feedback">Please enter your date of birth</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                            <div class="form-text">We'll send a verification link to this email</div>
                        </div>
                        <div class="mb-3">
                            <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" required>
                            <div class="invalid-feedback">Please enter your contact number</div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Complete Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="2" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            <div class="invalid-feedback">Please enter your complete address</div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h5><i class="fas fa-graduation-cap me-2"></i> Academic Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="program" class="form-label">Program/Course <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="program" name="program" value="<?= htmlspecialchars($_POST['program'] ?? '') ?>" required>
                                <div class="invalid-feedback">Please enter your program/course</div>
                                <div class="form-text">E.g., Bachelor of Science in Information Technology</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="graduation_year" class="form-label">Year of Graduation <span class="text-danger">*</span></label>
                                <select class="form-select" id="graduation_year" name="graduation_year" required>
                                    <option value="" disabled selected>Select year</option>
                                    <?php
                                    $current_year = date('Y');
                                    for ($year = $current_year; $year >= 1950; $year--):
                                        $selected = (isset($_POST['graduation_year']) && (int)$_POST['graduation_year'] === $year) ? 'selected' : '';
                                        echo "<option value=\"$year\" $selected>$year</option>";
                                    endfor;
                                    ?>
                                </select>
                                <div class="invalid-feedback">Please select your graduation year</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5><i class="fas fa-briefcase me-2"></i> Professional Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company" class="form-label">Current/Most Recent Company</label>
                                <input type="text" class="form-control" id="company" name="company" value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
                                <div class="form-text">Leave blank if not applicable</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="position" class="form-label">Position/Job Title</label>
                                <input type="text" class="form-control" id="position" name="position" value="<?= htmlspecialchars($_POST['position'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="industry" class="form-label">Industry</label>
                                <input type="text" class="form-control" id="industry" name="industry" value="<?= htmlspecialchars($_POST['industry'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Work Location</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4 pt-2">
                                    <input class="form-check-input" type="checkbox" id="is_current" name="is_current" value="1" <?= (isset($_POST['is_current']) && $_POST['is_current'] == '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_current">
                                        I currently work here
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3" id="end_date_container" style="display: none;">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Job Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h5><i class="fas fa-lock me-2"></i> Account Security</h5>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                                <div class="invalid-feedback">Please choose a username</div>
                            </div>
                            <div class="form-text">This will be your unique identifier on UNREConnect</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimum 8 characters with at least 1 number and 1 special character</div>
                                <div class="invalid-feedback">Password must be at least 8 characters long</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Passwords do not match</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a> <span class="text-danger">*</span>
                        </label>
                        <div class="invalid-feedback">You must agree to the terms and conditions</div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Home
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i> Create Account
                        </button>
                    </div>
                </form>
                
                <div class="login-link">
                    Already have an account? <a href="alumni_login.php">Log in here</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Terms and Privacy Modals -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Terms of Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By accessing or using UNREConnect, you agree to be bound by these Terms of Service.</p>
                    
                    <h6>2. User Responsibilities</h6>
                    <p>You are responsible for maintaining the confidentiality of your account and password and for restricting access to your computer.</p>
                    
                    <h6>3. Code of Conduct</h6>
                    <p>You agree not to use the service to post or transmit any unlawful, threatening, abusive, or harmful content.</p>
                    
                    <h6>4. Privacy</h6>
                    <p>Your use of UNREConnect is also governed by our Privacy Policy.</p>
                    
                    <h6>5. Modifications</h6>
                    <p>We reserve the right to modify these terms at any time. Your continued use of the service constitutes acceptance of those changes.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Information We Collect</h6>
                    <p>We collect personal information that you provide when you register, such as your name, email, and academic information.</p>
                    
                    <h6>2. How We Use Your Information</h6>
                    <p>We use your information to provide and improve our services, communicate with you, and for alumni networking purposes.</p>
                    
                    <h6>3. Information Sharing</h6>
                    <p>We do not sell your personal information. We may share information with other alumni for networking purposes, with your consent.</p>
                    
                    <h6>4. Data Security</h6>
                    <p>We implement appropriate security measures to protect your personal information.</p>
                    
                    <h6>5. Your Rights</h6>
                    <p>You have the right to access, correct, or delete your personal information at any time.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>UNREConnect</h5>
                    <p>Connecting alumni, students, and faculty of the University of Natural Resources and Environment.</p>
                </div>
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="alumni_login.php" class="text-white">Login</a></li>
                        <li><a href="#" class="text-white">About Us</a></li>
                        <li><a href="#" class="text-white">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Connect With Us</h5>
                    <div class="social-links">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?= date('Y') ?> UNREConnect. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Toggle end date field based on current employment status
        function toggleEndDate() {
            const isCurrent = document.getElementById('is_current').checked;
            const endDateContainer = document.getElementById('end_date_container');
            const endDateField = document.getElementById('end_date');
            
            if (isCurrent) {
                endDateContainer.style.display = 'none';
                endDateField.disabled = true;
                endDateField.value = '';
            } else {
                endDateContainer.style.display = 'block';
                endDateField.disabled = false;
            }
        }

        // Initialize form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize end date visibility
            toggleEndDate();
            document.getElementById('is_current').addEventListener('change', toggleEndDate);
            
            // Set max date for date of birth (must be at least 16 years old)
            const today = new Date();
            const minDob = new Date(today.getFullYear() - 100, today.getMonth(), today.getDate());
            const maxDob = new Date(today.getFullYear() - 16, today.getMonth(), today.getDate());
            
            document.getElementById('dob').max = maxDob.toISOString().split('T')[0];
            document.getElementById('dob').min = minDob.toISOString().split('T')[0];
            
            // Set max date for start and end dates to today
            const todayStr = today.toISOString().split('T')[0];
            document.getElementById('start_date').max = todayStr;
            document.getElementById('end_date').max = todayStr;
        });

        // Toggle password visibility
        (function() {
            'use strict';
            
            // Toggle password visibility
            const togglePasswordButtons = document.querySelectorAll('.toggle-password');
            togglePasswordButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (targetInput.type === 'password') {
                        targetInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        targetInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
            
            // Simple form submission handler
            const form = document.getElementById('registrationForm');
            if (form) {
                form.addEventListener('submit', function() {
                    // Add loading state to submit button
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Creating Account...';
                    }
                });
            }
        })();
    </script>
</body>
</html>
```