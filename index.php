<?php
session_start();

// Check if user is already logged in as alumni
if (isset($_SESSION['alumni_id'])) {
    header("Location: /unreconnect/alumni/homepage.php?page=home");
    exit();
}

// Check for login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    // Include database connection
    require_once 'admin/db_connect.php';
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Authenticate alumni
    $stmt = $conn->prepare("SELECT id, password FROM alumni WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($alumni = $result->fetch_assoc()) {
        if (password_verify($password, $alumni['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['alumni_id'] = $alumni['id'];
            $_SESSION['last_activity'] = time();
            
            // Redirect to alumni dashboard
            header("Location: /unreconnect/alumni/homepage.php?page=home");
            exit();
        }
    }
    
    // If we get here, login failed
    $login_error = "Invalid email or password";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>UNREConnect - Home</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet" />

  <style>
    body { font-family: 'Montserrat', sans-serif; margin:0; padding:0; color:#212529; }

    a { text-decoration: none; }
    a:hover, a:focus { text-decoration: none; }

    /* Fixed Navbar */
    nav.navbar { 
        background-color: rgba(0,0,0,0.25) !important; 
        font-weight:600; 
        transition: background 0.3s ease, padding 0.3s ease; 
        position: fixed; 
        top: 0;
        left: 0;
        width: 100%; 
        z-index: 1030;
        padding: 10px 0;
    }
    nav.navbar.scrolled { background-color: rgba(0,77,37,0.85) !important; }
    nav.navbar .navbar-brand { display:flex; align-items:center; }
    nav.navbar .navbar-brand img { height:50px; margin-right:10px; }
    nav.navbar .navbar-brand span { color:#ffd700; font-weight:900; font-size:1.5rem; letter-spacing:0.05em; text-transform:uppercase; }
    nav.navbar .nav-link { color:#fff !important; transition: color 0.3s; text-decoration: none !important; }
    nav.navbar .nav-link:hover { color:#ffd700 !important; text-decoration: none !important; }
    nav.navbar .dropdown-menu { border-radius:0.3rem; border-color:#004d25; box-shadow:0 4px 15px rgba(0,77,37,0.2); }
    nav.navbar .dropdown-item:hover { background-color:#ffd700; color:#004d25 !important; }

    /* Hero Section Fullscreen */
    body { padding-top: 70px; } /* Add padding to body to account for fixed navbar */
    .hero-section { 
        position: relative; 
        height:100vh; 
        width:100%; 
        display:flex; 
        align-items:center; 
        justify-content:center; 
        text-align:center; 
        color:white; 
        overflow:hidden; 
        margin-top: -70px; /* Compensate for fixed navbar */
    }
    .hero-slideshow { position:absolute; inset:0; z-index:0; width:100%; height:100%; }
    .hero-slideshow img { position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; opacity:0; transition: opacity 1.5s ease-in-out; }
    .hero-slideshow img.active { opacity:1; }

    .hero-overlay { position:absolute; inset:0; background: linear-gradient(180deg, rgba(0,77,37,0.6) 0%, rgba(0,0,0,0.4) 100%); z-index:1; }

    /* Animated Hero Text */
    .hero-content { position: relative; z-index:2; max-width:700px; padding:30px; border-radius:12px; background-color: rgba(0,0,0,0.5); box-shadow: 0px 4px 20px rgba(0,0,0,0.3); }
    .hero-content h1, .hero-content p { opacity:0; transform:translateY(30px); }
    .hero-content h1.animate { animation: fadeUp 1s forwards 0.5s; }
    .hero-content p.animate { animation: fadeUp 1s forwards 1.2s; }
    @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }

    /* Button */
    .btn-gold { background-color:#ffd700; color:#004d25; font-weight:700; padding:0.75rem 2.5rem; border-radius:50px; box-shadow:0 4px 8px rgba(255,215,0,0.5); transition:all 0.3s ease; }
    .btn-gold:hover { background-color:#e6c200; color:#003311; box-shadow:0 6px 12px rgba(230,194,0,0.7); }

    /* Footer */
    footer { background-color:#004d25; color:#ffd700; padding:1rem 0; text-align:center; font-size:0.9rem; margin-top:4rem; }

    @media (max-width:768px) { .hero-content h1 { font-size:2.8rem; } .hero-content p { font-size:1.1rem; } .btn-gold { padding:0.6rem 2rem; font-size:1rem; } }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand" href="index.php">
      <img src="admin/assets/img/logo.png" alt="UNRE Logo">
      <span>UNREConnect</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarContent">
      <ul class="navbar-nav me-2">
        <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#gallery">Gallery</a></li>
        <li class="nav-item"><a class="nav-link" href="#events">Events</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Login</a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="/unreconnect/admin_login.php">Admin Login</a></li>
            <li><a class="dropdown-item" href="/unreconnect/alumni_login.php">Alumni Login</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero Section Fullscreen -->
<?php
// Database connection for jobs
$db_connect_path = __DIR__ . '/admin/db_connect.php';
$recent_jobs = [];

if (file_exists($db_connect_path)) {
    require_once $db_connect_path;
    $result = $conn->query("SELECT * FROM job_listings WHERE `delete` IS NULL ORDER BY date_created DESC LIMIT 6");
    if ($result) {
        $recent_jobs = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<section class="hero-section">
  <div class="hero-slideshow">
    <img src="/unreconnect/admin/assets/uploads/gallery/b.jpg" class="active" alt="Campus">
    <img src="/unreconnect/admin/assets/uploads/gallery/d.jpg" alt="Graduation">
    <img src="/unreconnect/admin/assets/uploads/gallery/a.jpg" alt="Events">
  </div>
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <h1 class="animate">Welcome to UNREConnect</h1>
    <p class="animate">Reconnect with your alma mater and network with fellow alumni.</p>
    <a href="/unreconnect/register.php" class="btn btn-gold">Create Account</a>
  </div>
</section>

<!-- Job Listings Section -->
<?php if (!empty($recent_jobs)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold" style="color:#004d25;">Latest Job Opportunities</h2>
            <p class="text-muted">Explore the latest career opportunities from our partners</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($recent_jobs as $job): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <?php if(!empty($job['company_logo'])): ?>
                                    <img src="<?= htmlspecialchars($job['company_logo']) ?>" alt="<?= htmlspecialchars($job['company']) ?>" class="rounded me-3" style="width: 50px; height: 50px; object-fit: contain;">
                                <?php else: ?>
                                    <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                        <i class="fas fa-building"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h5 class="mb-0"><?= htmlspecialchars($job['job_title']) ?></h5>
                                    <p class="text-muted mb-0"><?= htmlspecialchars($job['company']) ?></p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <?php if(!empty($job['location'])): ?>
                                    <span class="badge bg-light text-dark me-2 mb-2">
                                        <i class="fas fa-map-marker-alt text-primary me-1"></i> 
                                        <?= htmlspecialchars($job['location']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if(!empty($job['job_type'])): ?>
                                    <span class="badge bg-light text-dark me-2 mb-2">
                                        <i class="fas fa-briefcase text-primary me-1"></i> 
                                        <?= htmlspecialchars($job['job_type']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-muted small mb-3">
                                <?= substr(strip_tags(html_entity_decode($job['description'])), 0, 120) ?>...
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="far fa-clock me-1"></i> 
                                    <?= date('M d, Y', strtotime($job['date_created'])) ?>
                                </small>
                                <a href="alumni/jobs.php#job-<?= $job['job_listings_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    View Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="/unreconnect/alumni/jobs.php" class="btn" style="background-color: #004d25; color: white;">
                View All Jobs <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- About Section -->
<section id="about" class="container my-5">
  <h2 class="text-center mb-4 fw-bold" style="color: #004d25;">About UNREConnect</h2>
  <p class="lead text-center text-secondary mx-auto" style="max-width:720px;">
    UNREConnect is a dynamic platform designed to foster ongoing connections between the University of Natural Resources and Environment and its esteemed alumni. Through this platform, alumni can engage, network, and access university resources.
  </p>
</section>

<!-- Gallery Section -->
<section id="gallery" class="container my-5">
  <h2 class="text-center mb-4 fw-bold" style="color:#004d25;">Gallery</h2>
  <div class="row g-4 justify-content-center">
    <div class="col-md-4 col-sm-6"><div class="card shadow-sm border-0"><img src="/unreconnect/admin/assets/uploads/gallery/b.jpg" class="card-img-top rounded" alt="Campus"><div class="card-body"><p class="card-text text-muted small">UNRE Campus grounds and facilities.</p></div></div></div>
    <div class="col-md-4 col-sm-6"><div class="card shadow-sm border-0"><img src="/unreconnect/admin/assets/uploads/gallery/d.jpg" class="card-img-top rounded" alt="Graduation"><div class="card-body"><p class="card-text text-muted small">Graduation ceremony highlights.</p></div></div></div>
    <div class="col-md-4 col-sm-6"><div class="card shadow-sm border-0"><img src="/unreconnect/admin/assets/uploads/gallery/a.jpg" class="card-img-top rounded" alt="Events"><div class="card-body"><p class="card-text text-muted small">Events and seminars at UNRE.</p></div></div></div>
  </div>
</section>

<!-- Events Section -->
<section id="events" class="container my-5">
  <h2 class="text-center mb-4 fw-bold" style="color:#004d25;">Upcoming Events</h2>
  <div class="list-group shadow-sm rounded">
    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
      <div><h5 class="mb-1">Alumni Homecoming 2025</h5><small class="text-muted">March 15, 2025</small></div>
      <span class="badge bg-success rounded-pill">Open</span>
    </a>
    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
      <div><h5 class="mb-1">Research Symposium</h5><small class="text-muted">June 10, 2025</small></div>
      <span class="badge bg-warning text-dark rounded-pill">Registration Closing</span>
    </a>
  </div>
</section>

<!-- Contact Section -->
<section id="contact" class="container my-5">
  <h2 class="text-center mb-4 fw-bold" style="color:#004d25;">Contact Us</h2>
  <form class="mx-auto" style="max-width:480px;">
    <div class="mb-3"><label for="contactName" class="form-label fw-semibold">Name</label><input type="text" class="form-control" id="contactName" placeholder="Your full name" required></div>
    <div class="mb-3"><label for="contactEmail" class="form-label fw-semibold">Email address</label><input type="email" class="form-control" id="contactEmail" placeholder="name@example.com" required></div>
    <div class="mb-3"><label for="contactMessage" class="form-label fw-semibold">Message</label><textarea class="form-control" id="contactMessage" rows="4" placeholder="Your message here..." required></textarea></div>
    <button type="submit" class="btn btn-gold w-100">Send Message</button>
  </form>
</section>

<!-- Signup Modal -->
<div class="modal fade" id="signupModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow">
      <div class="modal-header">
        <h5 class="modal-title">Create Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="signup-form-body">
        <div class="text-center py-4"><div class="spinner-border text-success"></div><p class="mt-3 mb-0">Loading form...</p></div>
      </div>
    </div>
  </div>
</div>

<!-- Footer -->
<footer>
  <div class="container">&copy; <?php echo date("Y"); ?> University of Natural Resources and Environment - UNREConnect. All rights reserved.</div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
  $('#signupModal').on('show.bs.modal', function(){ $('#signup-form-body').load('/unreconnect/alumni/signup.php'); });

  // Slideshow with smooth fade
  const slides = document.querySelectorAll('.hero-slideshow img');
  let current = 0;
  setInterval(()=>{
    slides[current].classList.remove('active');
    current = (current + 1) % slides.length;
    slides[current].classList.add('active');
  }, 5000);

  // Animate hero text
  window.addEventListener('load', ()=>{
    document.querySelector('.hero-content h1').classList.add('animate');
    document.querySelector('.hero-content p').classList.add('animate');
  });

  // Navbar background on scroll
  window.addEventListener('scroll', ()=>{
    document.querySelector('nav.navbar').classList.toggle('scrolled', window.scrollY > 50);
  });
</script>
</body>
</html>
