
<?php
require_once "config/database.php";

// Fetch homepage statistics
$totalDonations = 0;
$availableFood = 0;
$totalUsers = 0;

try {
    $totalDonations = (int) $conn->query(
        "SELECT COUNT(*) FROM food_listings"
    )->fetchColumn();

    $availableFood = (int) $conn->query(
        "SELECT COUNT(*) FROM food_listings
         WHERE status = 'available'
         AND expiry_time > NOW()"
    )->fetchColumn();

    $totalUsers = (int) $conn->query(
        "SELECT COUNT(*) FROM users"
    )->fetchColumn();

    // Fetch latest available donations
    $stmt = $conn->prepare(
        "SELECT f.*, u.name AS donor_name
         FROM food_listings f
         JOIN users u ON f.donor_id = u.id
         WHERE f.status = 'available'
         AND f.expiry_time > NOW()
         ORDER BY f.created_at DESC
         LIMIT 3"
    );

    $stmt->execute();
    $foodListings = $stmt->fetchAll();

} catch (PDOException $e) {
    $foodListings = [];
}

// Securely display database values
function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="description"
          content="FoodLoop connects food donors with people and organizations to reduce food waste.">

    <title>FoodLoop | Don't Waste Food, Share Hope</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Main CSS -->
    <link rel="stylesheet"
          href="assets/css/style.css">

</head>

<body>

<!-- ================= NAVBAR ================= -->

<header class="header" id="header">

    <nav class="navbar container">

        <a href="index.php" class="logo">

            <span class="logo-icon">
                <i class="fa-solid fa-seedling"></i>
            </span>

            Food<span>Loop</span>

        </a>

        <button class="menu-toggle"
                id="menuToggle"
                aria-label="Toggle navigation"
                aria-expanded="false">

            <i class="fa-solid fa-bars"></i>

        </button>

        <ul class="nav-links" id="navLinks">

            <li>
                <a href="#home" class="nav-link active">Home</a>
            </li>

            <li>
                <a href="#about" class="nav-link">About</a>
            </li>

            <li>
                <a href="#how-it-works" class="nav-link">How It Works</a>
            </li>

            <li>
                <a href="#donations" class="nav-link">Donations</a>
            </li>

            <li>
                <a href="#contact" class="nav-link">Contact</a>
            </li>

            <li class="mobile-auth">
                <a href="auth/login.php" class="btn btn-outline">
                    Login
                </a>
            </li>

            <li class="mobile-auth">
                <a href="auth/register.php" class="btn btn-primary">
                    Get Started
                </a>
            </li>

        </ul>

        <div class="nav-actions">

            <a href="auth/login.php" class="login-btn">
                Login
            </a>

            <a href="auth/register.php" class="btn btn-primary">
                Join FoodLoop
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </div>

    </nav>

</header>


<!-- ================= HERO SECTION ================= -->

<main>

<section class="hero" id="home">

    <div class="hero-overlay"></div>

    <div class="container hero-container">

        <div class="hero-content reveal">

            <span class="hero-tag">
                <i class="fa-solid fa-heart"></i>
                Together Against Food Waste
            </span>

            <h1>
                Good Food Deserves
                <span>Another Chance.</span>
            </h1>

            <p>
                Connect surplus food with people who need it.
                Make a difference in your community, one meal
                at a time.
            </p>

            <div class="hero-buttons">

                <a href="auth/register.php"
                   class="btn btn-primary btn-large">

                    Donate Food
                    <i class="fa-solid fa-arrow-right"></i>

                </a>

                <a href="#how-it-works"
                   class="btn btn-white btn-large">

                    <i class="fa-regular fa-circle-play"></i>
                    Explore More

                </a>

            </div>

            <div class="hero-trust">

                <div class="trust-icon">
                    <i class="fa-solid fa-leaf"></i>
                </div>

                <div>
                    <strong>Make Every Meal Count</strong>
                    <span>Reduce waste. Spread happiness.</span>
                </div>

            </div>

        </div>

        <div class="hero-visual reveal">

            <div class="hero-image-card">

                <img
                    src="https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=1000&q=85"
                    alt="Fresh healthy food ready to share">

                <div class="floating-card">

                    <div class="floating-icon">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>

                    <div>
                        <strong>Share More, Waste Less</strong>
                        <span>Small actions create big change.</span>
                    </div>

                </div>

            </div>

            <div class="hero-decoration decoration-one"></div>
            <div class="hero-decoration decoration-two"></div>

        </div>

    </div>

</section>


<!-- ================= STATISTICS ================= -->

<section class="stats-section">

    <div class="container stats-grid">

        <div class="stat-card reveal">

            <div class="stat-icon">
                <i class="fa-solid fa-box-open"></i>
            </div>

            <div>
                <h2><?= number_format($totalDonations) ?>+</h2>
                <p>Total Donations</p>
            </div>

        </div>

        <div class="stat-card reveal">

            <div class="stat-icon">
                <i class="fa-solid fa-bowl-food"></i>
            </div>

            <div>
                <h2><?= number_format($availableFood) ?>+</h2>
                <p>Available Listings</p>
            </div>

        </div>

        <div class="stat-card reveal">

            <div class="stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>

            <div>
                <h2><?= number_format($totalUsers) ?>+</h2>
                <p>Registered Members</p>
            </div>

        </div>

        <div class="stat-card reveal">

            <div class="stat-icon">
                <i class="fa-solid fa-earth-asia"></i>
            </div>

            <div>
                <h2>100%</h2>
                <p>Community Driven</p>
            </div>

        </div>

    </div>

</section>


<!-- ================= ABOUT SECTION ================= -->

<section class="about-section section-padding" id="about">

    <div class="container about-grid">

        <div class="about-image reveal">

            <img
                src="https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&w=1000&q=85"
                alt="Community volunteers helping others">

            <div class="about-badge">

                <i class="fa-solid fa-heart"></i>

                <div>
                    <strong>Food for Everyone</strong>
                    <span>Creating a better tomorrow</span>
                </div>

            </div>

        </div>

        <div class="about-content reveal">

            <span class="section-tag">
                <i class="fa-solid fa-leaf"></i>
                ABOUT FOODLOOP
            </span>

            <h2>
                Turning Food Waste Into
                <span>Food Hope.</span>
            </h2>

            <p>
                Every day, perfectly good food goes to waste
                while many communities struggle to access
                nutritious meals.

            </p>

            <p>
                FoodLoop provides a simple platform where
                restaurants, college canteens and organizations
                can share surplus food with verified receivers.
            </p>

            <div class="about-points">

                <div>
                    <i class="fa-solid fa-circle-check"></i>
                    Reduce unnecessary food waste
                </div>

                <div>
                    <i class="fa-solid fa-circle-check"></i>
                    Connect donors and receivers
                </div>

                <div>
                    <i class="fa-solid fa-circle-check"></i>
                    Encourage community participation
                </div>

            </div>

            <a href="auth/register.php" class="btn btn-primary">
                Become a Member
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </div>

    </div>

</section>


<!-- ================= HOW IT WORKS ================= -->

<section class="how-section section-padding" id="how-it-works">

    <div class="container">

        <div class="section-heading reveal">

            <span class="section-tag">
                SIMPLE PROCESS
            </span>

            <h2>
                How <span>FoodLoop</span> Works
            </h2>

            <p>
                Three simple steps to turn surplus food
                into something meaningful.
            </p>

        </div>

        <div class="steps-grid">

            <div class="step-card reveal">

                <div class="step-number">01</div>

                <div class="step-icon">
                    <i class="fa-solid fa-utensils"></i>
                </div>

                <h3>List Surplus Food</h3>

                <p>
                    Donors register and upload details
                    about their available surplus food.
                </p>

            </div>

            <div class="step-card reveal">

                <div class="step-number">02</div>

                <div class="step-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>

                <h3>Discover & Claim</h3>

                <p>
                    Receivers explore available food
                    and claim suitable donations.
                </p>

            </div>

            <div class="step-card reveal">

                <div class="step-number">03</div>

                <div class="step-icon">
                    <i class="fa-solid fa-handshake"></i>
                </div>

                <h3>Collect & Share</h3>

                <p>
                    Coordinate pickup and help food
                    reach the people who need it.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- ================= DONATIONS SECTION ================= -->

<section class="donations-section section-padding" id="donations">

    <div class="container">

        <div class="donation-heading reveal">

            <div>

                <span class="section-tag">
                    <i class="fa-solid fa-bowl-food"></i>
                    FRESH OPPORTUNITIES
                </span>

                <h2>
                    Latest Food <span>Donations</span>
                </h2>

                <p>
                    Explore available food shared by our donors.
                </p>

            </div>

            <a href="auth/register.php" class="view-all">
                Explore More
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </div>


        <div class="food-grid">

            <?php if (!empty($foodListings)): ?>

                <?php foreach ($foodListings as $food): ?>

                    <article class="food-card reveal">

                        <div class="food-image">

                            <?php if (!empty($food['image'])): ?>

                                <img
                                    src="uploads/<?= e(basename($food['image'])) ?>"
                                    alt="<?= e($food['food_name']) ?>">

                            <?php else: ?>

                                <div class="food-placeholder">
                                    <i class="fa-solid fa-bowl-food"></i>
                                </div>

                            <?php endif; ?>

                            <span class="available-label">
                                <i class="fa-solid fa-circle"></i>
                                Available
                            </span>

                        </div>

                        <div class="food-details">

                            <span class="food-category">
                                <?= e($food['category']) ?>
                            </span>

                            <h3><?= e($food['food_name']) ?></h3>

                            <p>
                                <?= e($food['description'] ?? '') ?>
                            </p>

                            <div class="food-info">

                                <span>
                                    <i class="fa-solid fa-box"></i>
                                    <?= e($food['quantity']) ?> units
                                </span>

                                <span>
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= e($food['location']) ?>
                                </span>

                            </div>

                            <div class="food-footer">

                                <span>
                                    <i class="fa-regular fa-clock"></i>
                                    Pickup before
                                    <?= date(
                                        'h:i A',
                                        strtotime($food['expiry_time'])
                                    ) ?>
                                </span>

                                <a href="auth/login.php"
                                   class="claim-btn">

                                    Claim
                                    <i class="fa-solid fa-arrow-right"></i>

                                </a>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">

                    <i class="fa-solid fa-bowl-food"></i>

                    <h3>No Donations Available Yet</h3>

                    <p>
                        Be the first donor to share surplus food
                        with your community.
                    </p>

                    <a href="auth/register.php"
                       class="btn btn-primary">

                        Donate Food
                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- ================= CTA SECTION ================= -->

<section class="cta-section">

    <div class="container cta-container reveal">

        <div>

            <span class="cta-tag">
                BE PART OF THE CHANGE
            </span>

            <h2>
                Your Extra Food Can Make
                Someone's Day.
            </h2>

            <p>
                Join FoodLoop and help create a community
                where good food never goes to waste.
            </p>

        </div>

        <a href="auth/register.php"
           class="btn btn-white btn-large">

            Join FoodLoop Today
            <i class="fa-solid fa-arrow-right"></i>

        </a>

    </div>

</section>


<!-- ================= CONTACT ================= -->

<section class="contact-section section-padding" id="contact">

    <div class="container contact-content reveal">

        <span class="section-tag">GET IN TOUCH</span>

        <h2>
            Let's Make a <span>Difference Together.</span>
        </h2>

        <p>
            Have a question or want to collaborate?
            Join our community and help reduce food waste.
        </p>

        <a href="mailto:foodloop@example.com"
           class="contact-link">

            <i class="fa-solid fa-envelope"></i>
            Contact FoodLoop

        </a>

    </div>

</section>

</main>


<!-- ================= FOOTER ================= -->

<footer class="footer">

    <div class="container footer-grid">

        <div class="footer-about">

            <a href="index.php" class="logo footer-logo">

                <span class="logo-icon">
                    <i class="fa-solid fa-seedling"></i>
                </span>

                Food<span>Loop</span>

            </a>

            <p>
                Don't Waste Food, Share Hope.
                Connecting surplus food with those who need it.
            </p>

            <div class="social-links">

                <a href="#" aria-label="Facebook">
                    <i class="fa-brands fa-facebook-f"></i>
                </a>

                <a href="#" aria-label="Instagram">
                    <i class="fa-brands fa-instagram"></i>
                </a>

                <a href="#" aria-label="LinkedIn">
                    <i class="fa-brands fa-linkedin-in"></i>
                </a>

            </div>

        </div>


        <div class="footer-column">

            <h3>Quick Links</h3>

            <a href="#home">Home</a>
            <a href="#about">About Us</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#donations">Donations</a>

        </div>


        <div class="footer-column">

            <h3>Account</h3>

            <a href="auth/login.php">Login</a>
            <a href="auth/register.php">Register</a>
            <a href="auth/register.php">Become a Donor</a>
            <a href="auth/register.php">Become a Receiver</a>

        </div>


        <div class="footer-column">

            <h3>Contact</h3>

            <p>
                <i class="fa-solid fa-envelope"></i>
                foodloop@example.com
            </p>

            <p>
                <i class="fa-solid fa-location-dot"></i>
                Community Based Platform
            </p>

        </div>

    </div>

    <div class="footer-bottom">

        <div class="container">

            <p>
                © <span id="currentYear"></span> FoodLoop.
                All Rights Reserved.
            </p>

            <p>Made with <i class="fa-solid fa-heart"></i> for a better world.</p>

        </div>

    </div>

</footer>


<!-- JavaScript -->
<script src="assets/js/script.js"></script>

</body>
</html>
