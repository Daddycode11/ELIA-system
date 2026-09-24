<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$site = app_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= escape($site['site_name']) ?> | <?= escape($site['system_name']) ?></title>

<?php require __DIR__ . '/includes/styles.php'; ?>
<link rel="stylesheet" href="<?= escape(url('assets/css/landing.css')) ?>">
</head>
<body><a class="visually-hidden-focusable landing-skip" href="#main-content">Skip to content</a>

<nav class="navbar navbar-expand-xl py-3" data-bs-theme="dark" aria-label="Main navigation">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="<?= escape(url('/')) ?>">
      <?= escape($site['site_name']) ?> <span class="brand-campus">| OMSU</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navMain">
      <ul class="navbar-nav align-items-xl-center gap-xl-3">
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="#initiatives">Initiatives</a></li>
        <li class="nav-item"><a class="nav-link" href="#documents">Documents</a></li>
        <li class="nav-item"><a class="nav-link" href="#process">How It Works</a></li>
        <?php $authLinksInNavbar = true; require __DIR__ . '/includes/auth-links.php'; ?>
      </ul>
    </div>
  </div>
</nav>

<main id="main-content" tabindex="-1">
<!-- HERO -->
<header class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-7">
        <div class="eyebrow">External Linkages and International Affairs Office</div>
        <h1 class="mt-2 mb-3"><?= escape($site['system_name']) ?></h1>
        <p class="lead mb-4">
          A centralized platform for managing conference and meeting requests, Referendum/BOT
          submissions, pre-departure and post-travel requirements, and international partnership
          records — built for <?= escape($site['campus']) ?>.
        </p>
        <div class="d-flex flex-wrap gap-3">
          <a href="<?= escape(url('client/register.php')) ?>" class="btn btn-gold btn-lg px-4">Get Started</a>
          <a href="#about" class="btn btn-outline-light-custom btn-lg px-4">Learn More</a>
        </div>
      </div>
      <div class="col-lg-5 text-center">
        <img src="<?= escape(url('assets/img/elia-logo.png')) ?>" alt="OMSC External Linkages and International Affairs Office logo" class="img-fluid hero-logo">
      </div>
    </div>
  </div>
</header>

<!-- CAMPUS STRIP -->
<div class="campus-strip campus-photo">
  <div class="container strip-text">
    <div>
      <div class="fw-semibold campus-title">Occidental Mindoro State College</div>
      <div class="campus-location">San Jose, Occidental Mindoro — Philippines</div>
    </div>
  </div>
</div>

<!-- STATS -->
<div class="stats-band">
  <div class="container">
    <div class="row g-0">
      <div class="col-6 col-md-3 stat-item">
        <span class="stat-num">40+</span>
        <div class="stat-label">Partner Institutions</div>
      </div>
      <div class="col-6 col-md-3 stat-item">
        <span class="stat-num">2022–2025</span>
        <div class="stat-label">Years of Active Internationalization</div>
      </div>
      <div class="col-6 col-md-3 stat-item">
        <span class="stat-num">ATU-Net</span>
        <div class="stat-label">Full Consortium Membership</div>
      </div>
      <div class="col-6 col-md-3 stat-item">
        <span class="stat-num">Top 3</span>
        <div class="stat-label">MIMAROPA, Webometrics Jan 2025</div>
      </div>
    </div>
  </div>
</div>

<!-- ABOUT -->
<section id="about">
  <div class="container">
    <div class="row">
      <div class="col-lg-7">
        <div class="section-label">About the Office</div>
        <h2 class="mb-0">Organizing internationalization records in one place</h2>
        <div class="divider-gold"></div>
        <p class="text-secondary">
          The ELIA Office manages OMSC's institutional partnerships, coordinates international
          collaborations, and maintains records of MOUs/MOAs, conferences, mobility programs, and
          travel documentation. Much of this has historically relied on manual records, spreadsheets,
          and scattered files — making retrieval, tracking, and reporting slower than they need to be.
        </p>
        <p class="text-secondary mb-0">
          This system brings those transactions into a single, role-based platform — so requests,
          requirements, and their status are always up to date and easy to find.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section id="features" class="features-section">
  <div class="container">
    <div class="section-label">What the System Offers</div>
    <h2 class="mb-0">Built around two roles</h2>
    <div class="divider-gold"></div>

    <div class="row g-4 mt-2">
      <div class="col-lg-6">
        <div class="panel">
          <h3>Admin (ELIA Personnel)</h3>
          <ul>
            <li>Centralize all ELIA-related requests, documents, and transactions in a single database</li>
            <li>Manage conference/meeting assessment requests and their required documents</li>
            <li>Process Referendum/BOT requests and supporting certificates</li>
            <li>Monitor pre-departure and post-travel requirements</li>
            <li>Maintain MOU/MOA records and ELIA certifications</li>
            <li>Track transaction and requirement completion status</li>
            <li>Generate reports for monitoring and accreditation</li>
            <li>Manage user accounts and role-based access</li>
          </ul>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="panel client-panel">
          <h3>Client (Faculty &amp; Authorized Users)</h3>
          <ul>
            <li>Submit requests for conferences/meetings, Referendum/BOT, and other ELIA transactions</li>
            <li>Upload required documents and supporting requirements</li>
            <li>Monitor the status of submitted requests in real time</li>
            <li>Access relevant ELIA information and announcements</li>
            <li>Receive notifications on updates and required actions</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- RECENT INITIATIVES -->
<section id="initiatives">
  <div class="container">
    <div class="section-label">Global Engagement</div>
    <h2 class="mb-0">Recent international initiatives</h2>
    <div class="divider-gold"></div>

    <div class="row g-3 mt-2">
      <div class="col-md-6 col-lg-3">
        <div class="init-card">
          <div class="init-year">November 2024</div>
          <h3>Full Membership, ATU-Net</h3>
          <p>OMSC became a full-fledged member of the Asia Technological University Network, opening access to research and mobility opportunities across Asia.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="init-card">
          <div class="init-year">February 2025</div>
          <h3>Official Partner, UNIIC</h3>
          <p>OMSC joined the University Incubator Consortium, an Asia–Africa alliance supporting start-ups and knowledge exchange.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="init-card">
          <div class="init-year">March 2024</div>
          <h3>MOA with Simon Fraser University</h3>
          <p>A Canadian partnership providing OMSC students English language training and study-abroad experience.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="init-card">
          <div class="init-year">January 2025</div>
          <h3>Top 3 in MIMAROPA, Webometrics</h3>
          <p>OMSC ranked 3rd among MIMAROPA SUCs and 18,005th globally in the Webometrics Ranking of World Universities.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- RESOURCES -->
<section id="documents" class="features-section">
  <div class="container">
    <div class="section-label">Transparency &amp; Reports</div>
    <h2 class="mb-0">Published documents</h2>
    <div class="divider-gold"></div>

    <div class="row g-3 mt-2">
      <div class="col-md-6">
        <a href="<?= escape(url('assets/docs/International-Linkages-and-Partnerships.pdf')) ?>" class="resource-card" target="_blank" rel="noopener noreferrer">
          <div class="resource-icon">PDF</div><span class="visually-hidden">Opens in a new tab: </span>
          <div>
            <h3 class="r-title">International Linkages and Partnerships</h3>
            <div class="r-sub">Full list of partner institutions, 2021–2025</div>
          </div>
        </a>
      </div>
      <div class="col-md-6">
        <a href="<?= escape(url('assets/docs/ELIA-ACCOMPLISHMENT-INTERNATIONAL-2022-2025.pdf')) ?>" class="resource-card" target="_blank" rel="noopener noreferrer">
          <div class="resource-icon">PDF</div><span class="visually-hidden">Opens in a new tab: </span>
          <div>
            <h3 class="r-title">ELIA Accomplishment Report</h3>
            <div class="r-sub">International initiatives, 2022–2025</div>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- PROCESS -->
<section id="process">
  <div class="container">
    <div class="section-label">How It Works</div>
    <h2 class="mb-0">From request to record</h2>
    <div class="divider-gold"></div>
  </div>
  <div class="container p-0">
    <div class="process-row">
      <div class="process-step">
        <span class="step-num">1</span>
        <div class="fw-semibold mb-1">Submit a Request</div>
        <div class="text-secondary process-description">Client logs in and files a request — conference/meeting, Referendum/BOT, or travel-related.</div>
      </div>
      <div class="process-step">
        <span class="step-num">2</span>
        <div class="fw-semibold mb-1">Upload Documents</div>
        <div class="text-secondary process-description">Required letters, forms, and certificates are attached directly to the request.</div>
      </div>
      <div class="process-step">
        <span class="step-num">3</span>
        <div class="fw-semibold mb-1">ELIA Review</div>
        <div class="text-secondary process-description">ELIA personnel verify requirements and update the transaction status.</div>
      </div>
      <div class="process-step">
        <span class="step-num">4</span>
        <div class="fw-semibold mb-1">Track &amp; Notify</div>
        <div class="text-secondary process-description">Client is notified of status changes until the transaction is complete.</div>
      </div>
    </div>
  </div>
</section>

</main>
<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="row g-4">
      <div class="col-md-5">
        <h2 class="footer-heading"><?= escape($site['site_name']) ?> — <?= escape($site['office_name']) ?></h2>
        <p class="mb-0 footer-address">
          <?= escape($site['campus']) ?><br>
          San Jose, Occidental Mindoro, Philippines
        </p>
      </div>
      <div class="col-md-3">
        <h2 class="footer-heading">Quick Links</h2>
        <ul class="list-unstyled footer-list">
          <li class="mb-2"><a href="#about">About the Office</a></li>
          <li class="mb-2"><a href="#features">Features</a></li>
          <?php $authLinksInNavbar = false; require __DIR__ . '/includes/auth-links.php'; ?>
        </ul>
      </div>
      <div class="col-md-4">
        <h2 class="footer-heading">Contact</h2>
        <ul class="list-unstyled footer-list">
          <li class="mb-2"><a href="mailto:<?= escape($site['support_email']) ?>"><?= escape($site['support_email']) ?></a></li>
          <li class="mb-2"><a href="tel:+63434570231"><?= escape($site['support_phone']) ?></a></li>
          <li class="mb-2"><a href="https://omsc.edu.ph">omsc.edu.ph</a></li>
        </ul>
      </div>
    </div>
    <hr class="footer-divider">
    <div class="footer-copyright">
      &copy; <?= date('Y') ?> <?= escape($site['office_name']) ?> Office — Occidental Mindoro State College.
    </div>
  </div>
</footer>

<script src="<?= escape(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
</body>
</html>