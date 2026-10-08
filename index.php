<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/announcements.php';
require_once __DIR__ . '/includes/resources.php';

$site = app_config();
$authUser = current_user();
$hasSeal = is_file(__DIR__ . '/assets/img/omsc-seal.png'); // optional: put the OMSC seal here to show it beside the ELIA logo

/* ---------- Dynamic content (the page still renders if a table is missing) ---------- */
$announcements = [];
$resources = [];
$requirementTypes = [];
$partnerStats = ['partners' => 0, 'countries' => 0, 'agreements' => 0];
$partnerRows = [];

try {
    $announcements = announcement_public_list(5);
    $resources = resource_published_list();
} catch (Throwable $exception) {
    error_log((string) $exception);
}

try {
    $rows = database()->query(
        "SELECT t.id AS type_id, t.name AS type_name, t.description AS type_description,
                q.requirement_name, q.description, q.is_required
         FROM request_types t
         JOIN requirement_templates q ON q.request_type_id = t.id
         WHERE t.status = 'active' AND t.checklist_ready = 1 AND q.status = 'active' AND q.stage = 'submission'
         ORDER BY t.id, q.sort_order, q.id"
    )->fetchAll();
    foreach ($rows as $row) {
        $requirementTypes[$row['type_id']]['name'] = $row['type_name'];
        $requirementTypes[$row['type_id']]['description'] = (string) $row['type_description'];
        $requirementTypes[$row['type_id']]['items'][] = $row;
    }
} catch (Throwable $exception) {
    error_log((string) $exception);
}

try {
    $active = "a.status = 'signed' AND a.is_archived = 0 AND p.is_archived = 0 AND a.start_date <= CURDATE() AND (a.end_date IS NULL OR a.end_date >= CURDATE())";
    $stats = database()->query("SELECT COUNT(DISTINCT p.id) AS partners, COUNT(DISTINCT p.country) AS countries, COUNT(*) AS agreements FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id WHERE $active")->fetch();
    if ($stats) { $partnerStats = array_map('intval', $stats); }
    $partnerRows = database()->query("SELECT DISTINCT p.name, p.country FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id WHERE $active ORDER BY p.country, p.name LIMIT 12")->fetchAll();
} catch (Throwable $exception) {
    error_log((string) $exception);
}

// Shown only when no announcements have been published yet.
$fallbackHighlights = [
    ['November 2024', 'Full membership, ATU-Net', 'OMSC became a full member of the Asia Technological University Network, opening research and mobility opportunities across Asia.'],
    ['February 2025', 'Official partner, UNIIC', 'OMSC joined the University Incubator Consortium, an Asia–Africa alliance supporting start-ups and knowledge exchange.'],
    ['March 2024', 'MOA with Simon Fraser University', 'A Canadian partnership giving OMSC students English language training and study-abroad experience.'],
    ['January 2025', 'Top 3 in MIMAROPA, Webometrics', 'OMSC ranked 3rd among MIMAROPA SUCs in the Webometrics Ranking of World Universities.'],
];
$categories = announcement_categories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="ELIA Portal of Occidental Mindoro State College: submit international travel and linkage requests, upload requirements, and track their status.">
<title><?= escape($site['site_name']) ?> Portal | Occidental Mindoro State College</title>
<?php require __DIR__ . '/includes/styles.php'; ?>
<style>
:root{
  --navy:#0B2545; --navy-deep:#071A33; --navy-soft:#16365F;
  --gold:#C99A2E; --gold-soft:#E9D8A6; --sky:#3F7CAC;
  --paper:#F7F6F2; --white:#fff; --ink:#17202B; --muted:#546071; --line:#E3E1D9;
  --radius:10px;
}
html{scroll-behavior:smooth;scroll-padding-top:5.5rem}
body.el-body{font-family:'Inter',system-ui,sans-serif;color:var(--ink);background:var(--paper);line-height:1.6}
.el-body h1,.el-body h2,.el-body h3{font-family:'Source Serif 4',Georgia,serif;color:var(--navy);letter-spacing:-.01em}
.el-body a{color:var(--navy-soft)}
.el-body :focus-visible{outline:3px solid var(--gold);outline-offset:3px;border-radius:4px}
.el-skip{position:absolute;left:1rem;top:-4rem;background:var(--navy-deep);color:#fff !important;padding:.6rem 1rem;z-index:2000;border-radius:0 0 6px 6px}
.el-skip:focus{top:0}
.el-wrap{width:100%;max-width:1180px;margin:0 auto;padding:0 1.25rem}

/* ---------- Header ---------- */
.el-header{position:sticky;top:0;z-index:1030;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
.el-header .el-wrap{display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:4.5rem}
.el-brand{display:flex;align-items:center;gap:.8rem;text-decoration:none}
.el-brand img{height:48px;width:48px;object-fit:contain;display:block}
.el-brand-text{line-height:1.2}
.el-brand-text strong{display:block;color:var(--navy);font-size:1.05rem}
.el-brand-text span{display:block;color:var(--muted);font-size:.8rem}
.el-nav{display:flex;align-items:center;gap:.25rem}
.el-nav a.el-link{color:var(--navy);text-decoration:none;font-weight:500;font-size:.93rem;padding:.5rem .75rem;border-radius:6px}
.el-nav a.el-link:hover{background:var(--paper)}
.el-toggle{display:none;background:none;border:1px solid var(--line);border-radius:8px;padding:.45rem .6rem;color:var(--navy)}
.el-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;font-weight:600;font-size:.95rem;padding:.7rem 1.35rem;border-radius:8px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background-color .15s,border-color .15s,color .15s}
.el-btn-navy{background:var(--navy);color:#fff !important}
.el-btn-navy:hover{background:var(--navy-soft)}
.el-btn-gold{background:var(--gold);color:var(--navy-deep) !important}
.el-btn-gold:hover{background:#d9ab3f}
.el-btn-glass{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.4);color:#fff !important}
.el-btn-glass:hover{background:rgba(255,255,255,.2)}
.el-btn-lg{padding:.9rem 1.7rem;font-size:1rem}
.el-logout{background:none;border:0;color:var(--muted);font-size:.9rem;padding:.5rem .6rem}

/* ---------- Hero ---------- */
.el-hero{position:relative;isolation:isolate;color:#fff;min-height:clamp(540px,80vh,720px);display:flex;align-items:center;padding:4rem 0 7rem;overflow:hidden}
.el-hero-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center 40%;z-index:-2}
.el-hero::before{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(100deg,rgba(7,26,51,.94) 0%,rgba(7,26,51,.82) 42%,rgba(7,26,51,.35) 100%)}
.el-hero-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,.75fr);gap:3rem;align-items:center}
.el-hero-kicker{display:inline-flex;align-items:center;gap:.6rem;font-size:.95rem;color:var(--gold-soft);margin-bottom:1.1rem}
.el-hero-kicker::before{content:"";width:2.2rem;height:2px;background:var(--gold)}
.el-hero h1{color:#fff;font-size:clamp(2.1rem,4.6vw,3.5rem);line-height:1.08;max-width:20ch;margin:0 0 1.25rem}
.el-hero p.el-lede{font-size:1.15rem;color:#DCE4EF;max-width:46ch;margin-bottom:2rem}
.el-hero-actions{display:flex;flex-wrap:wrap;gap:.9rem;align-items:center}
.el-hero-note{margin-top:1.25rem;font-size:.92rem;color:#C4D0E0}
.el-hero-note a{color:#fff;text-underline-offset:3px}
.el-anim{animation:el-rise .7s .05s both cubic-bezier(.2,.7,.2,1)}

/* Sample tracker (the one memorable element) */
.el-tracker{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.26);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-radius:18px;padding:1.5rem;box-shadow:0 24px 60px rgba(0,0,0,.28)}
.el-tracker-top{display:flex;justify-content:space-between;align-items:center;gap:1rem;font-size:.82rem;color:#C4D0E0;margin-bottom:.4rem}
.el-tracker-top b{background:rgba(255,255,255,.14);padding:.15rem .6rem;border-radius:99px;font-weight:600;color:#fff}
.el-tracker h2{color:#fff;font-size:1.15rem;margin:0 0 .15rem}
.el-tracker .el-ref{font-size:.85rem;color:#C4D0E0;margin-bottom:1.1rem}
.el-steps{list-style:none;margin:0;padding:0;position:relative}
.el-steps li{position:relative;display:flex;gap:.9rem;padding:.55rem 0;font-size:.95rem;color:#C4D0E0;opacity:0;animation:el-step .5s both}
.el-steps li:nth-child(1){animation-delay:.35s}.el-steps li:nth-child(2){animation-delay:.55s}.el-steps li:nth-child(3){animation-delay:.75s}.el-steps li:nth-child(4){animation-delay:.95s}.el-steps li:nth-child(5){animation-delay:1.15s}
.el-steps li::before{content:"";flex:none;width:1.35rem;height:1.35rem;border-radius:50%;border:2px solid rgba(255,255,255,.4);margin-top:.1rem;position:relative;z-index:1;background:transparent}
.el-steps li:not(:last-child)::after{content:"";position:absolute;left:.62rem;top:1.8rem;bottom:-.5rem;width:2px;background:rgba(255,255,255,.22)}
.el-steps li.done{color:#fff}
.el-steps li.done::before{background:var(--gold);border-color:var(--gold);box-shadow:inset 0 0 0 3px var(--navy-soft)}
.el-steps li.now{color:#fff;font-weight:600}
.el-steps li.now::before{border-color:#fff;box-shadow:0 0 0 4px rgba(255,255,255,.2)}
.el-steps small{display:block;font-weight:400;color:#C4D0E0}

/* ---------- Quick access ---------- */
.el-quick{position:relative;z-index:2;margin-top:-3.6rem}
.el-quick-grid{display:grid;grid-template-columns:repeat(3,1fr);background:#fff;border-radius:14px;box-shadow:0 18px 50px rgba(11,37,69,.16);overflow:hidden}
.el-quick a{display:block;padding:1.5rem 1.6rem;text-decoration:none;color:var(--ink);border-right:1px solid var(--line);transition:background-color .15s}
.el-quick a:last-child{border-right:0}
.el-quick a:hover{background:#FBFAF6}
.el-quick strong{display:block;font-family:'Source Serif 4',Georgia,serif;font-size:1.15rem;color:var(--navy);margin-bottom:.2rem}
.el-quick span{color:var(--muted);font-size:.93rem}

/* ---------- Sections ---------- */
.el-section{padding:clamp(3.5rem,8vw,6rem) 0}
.el-section.alt{background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.el-h2{font-size:clamp(1.65rem,3.2vw,2.3rem);line-height:1.15;margin:0 0 .9rem;max-width:22ch}
.el-rule{width:3rem;height:3px;background:var(--gold);margin:0 0 1.4rem;border:0}
.el-text{color:var(--muted);max-width:60ch}
.el-split{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:clamp(2rem,6vw,5rem);align-items:start}

.el-process{list-style:none;counter-reset:step;margin:0;padding:0}
.el-process li{counter-increment:step;position:relative;padding:0 0 1.6rem 3.4rem}
.el-process li::before{content:counter(step);position:absolute;left:0;top:0;width:2.3rem;height:2.3rem;border-radius:50%;background:var(--navy);color:#fff;display:grid;place-items:center;font-weight:600;font-size:.95rem}
.el-process li:not(:last-child)::after{content:"";position:absolute;left:1.1rem;top:2.5rem;bottom:.2rem;width:2px;background:var(--line)}
.el-process strong{display:block;color:var(--navy);margin-bottom:.1rem}
.el-process span{color:var(--muted);font-size:.96rem}

.el-roles{display:grid;grid-template-columns:1fr 1fr;gap:2rem}
.el-role{padding-top:1.4rem;border-top:4px solid var(--navy)}
.el-role.client{border-top-color:var(--gold)}
.el-role h3{font-size:1.35rem;margin-bottom:.3rem}
.el-role p{color:var(--muted);margin-bottom:1rem}
.el-role ul{list-style:none;padding:0;margin:0}
.el-role li{position:relative;padding:.4rem 0 .4rem 1.7rem;border-bottom:1px solid var(--line);font-size:.97rem}
.el-role li::before{content:"";position:absolute;left:.1rem;top:.85rem;width:.75rem;height:.4rem;border-left:2px solid var(--gold);border-bottom:2px solid var(--gold);transform:rotate(-45deg)}

.el-acc details{border-bottom:1px solid var(--line)}
.el-acc details:first-child{border-top:1px solid var(--line)}
.el-acc summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.15rem .25rem;font-weight:600;color:var(--navy);font-size:1.05rem}
.el-acc summary::-webkit-details-marker{display:none}
.el-acc summary::after{content:"+";font-size:1.5rem;font-weight:400;color:var(--gold);line-height:1;transition:transform .2s}
.el-acc details[open] summary::after{transform:rotate(45deg)}
.el-acc .el-acc-body{padding:0 .25rem 1.4rem}
.el-acc ul{margin:0;padding-left:1.1rem;color:var(--ink)}
.el-acc li{padding:.2rem 0}
.el-acc li small{display:block;color:var(--muted)}
.el-tag{font-size:.78rem;color:var(--muted);margin-left:.4rem}

.el-news{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(0,1fr);gap:clamp(2rem,5vw,4rem)}
.el-feed-item{display:grid;grid-template-columns:7.5rem 1fr;gap:1.2rem;padding:1.15rem 0;border-top:1px solid var(--line)}
.el-feed-item:last-child{border-bottom:1px solid var(--line)}
.el-feed-date{font-size:.85rem;color:var(--muted)}
.el-feed-date b{display:block;color:var(--gold);font-weight:600}
.el-feed-item h3{font-size:1.15rem;margin:0 0 .25rem}
.el-feed-item h3 a{color:var(--navy);text-decoration:none}
.el-feed-item h3 a:hover{text-decoration:underline}
.el-feed-item p{margin:0;color:var(--muted);font-size:.96rem}
.el-docs h3{font-size:1.15rem;margin-bottom:.8rem}
.el-doc{display:flex;gap:.9rem;align-items:center;padding:.85rem 0;border-top:1px solid var(--line);text-decoration:none;color:var(--ink) !important}
.el-doc:last-child{border-bottom:1px solid var(--line)}
.el-doc:hover strong{text-decoration:underline}
.el-doc i{font-style:normal;flex:none;width:2.6rem;height:2.6rem;border-radius:8px;background:var(--navy);color:#fff;display:grid;place-items:center;font-size:.72rem;font-weight:700}
.el-doc strong{display:block;color:var(--navy);font-size:.97rem;line-height:1.3}
.el-doc span{font-size:.84rem;color:var(--muted)}

.el-partners{background:var(--navy);color:#fff;padding:clamp(3.5rem,8vw,5.5rem) 0}
.el-partners h2{color:#fff}
.el-partners .el-text{color:#C4D0E0}
.el-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin:2rem 0 2.2rem;border-top:1px solid rgba(255,255,255,.18);padding-top:1.8rem}
.el-stat b{display:block;font-family:'Source Serif 4',Georgia,serif;font-size:clamp(2.2rem,5vw,3.2rem);line-height:1;color:var(--gold-soft);font-weight:600}
.el-stat span{color:#C4D0E0;font-size:.95rem}
.el-chips{display:flex;flex-wrap:wrap;gap:.6rem;list-style:none;padding:0;margin:0}
.el-chips li{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);padding:.4rem .85rem;border-radius:99px;font-size:.9rem}
.el-chips small{color:#C4D0E0;margin-left:.3rem}
.el-high{display:grid;grid-template-columns:repeat(2,1fr);gap:1.6rem 2.5rem;margin-top:2rem}
.el-high h3{color:#fff;font-size:1.1rem;margin:0 0 .2rem}
.el-high p{color:#C4D0E0;margin:0;font-size:.94rem}
.el-high small{color:var(--gold-soft)}

/* ---------- Footer ---------- */
.el-footer{background:var(--navy-deep);color:#C4D0E0;padding:3rem 0 1.5rem;font-size:.95rem}
.el-footer h2{font-family:'Inter',sans-serif;font-size:.95rem;color:#fff;margin:0 0 .8rem;letter-spacing:0}
.el-footer a{color:#C4D0E0;text-decoration:none}
.el-footer a:hover{color:#fff;text-decoration:underline}
.el-footer ul{list-style:none;margin:0;padding:0}
.el-footer li{margin-bottom:.45rem}
.el-footer-grid{display:grid;grid-template-columns:1.6fr 1fr 1.2fr;gap:2rem}
.el-copy{border-top:1px solid rgba(255,255,255,.14);margin-top:2rem;padding-top:1.2rem;font-size:.85rem}

@keyframes el-rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes el-step{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  .el-anim,.el-steps li{animation:none;opacity:1}
}
@media (max-width:991px){
  .el-hero-grid{grid-template-columns:1fr}
  .el-tracker{display:none}
  .el-split,.el-news,.el-roles{grid-template-columns:1fr}
  .el-toggle{display:inline-flex}
  .el-nav{display:none;position:absolute;left:0;right:0;top:100%;background:#fff;flex-direction:column;align-items:stretch;padding:.75rem 1.25rem 1.25rem;border-bottom:1px solid var(--line);box-shadow:0 12px 24px rgba(11,37,69,.1)}
  .el-nav.open{display:flex}
  .el-nav a.el-btn{margin-top:.5rem}
}
@media (max-width:700px){
  .el-quick-grid{grid-template-columns:1fr}
  .el-quick a{border-right:0;border-bottom:1px solid var(--line)}
  .el-quick a:last-child{border-bottom:0}
  .el-stats{grid-template-columns:1fr;gap:1.2rem}
  .el-high,.el-footer-grid{grid-template-columns:1fr}
  .el-feed-item{grid-template-columns:1fr;gap:.3rem}
  .el-brand-text span{display:none}
  .el-hero{padding-bottom:6rem}
}
</style>
</head>
<body class="el-body">
<a class="el-skip" href="#main-content">Skip to content</a>

<header class="el-header">
  <div class="el-wrap">
    <a class="el-brand" href="<?= escape(url('/')) ?>">
      <?php if ($hasSeal): ?><img src="<?= escape(url('assets/img/omsc-seal.png')) ?>" alt="" width="48" height="48"><?php endif; ?>
      <img src="<?= escape(url('assets/img/elia-logo.png')) ?>" alt="ELIA logo" width="48" height="48">
      <span class="el-brand-text"><strong><?= escape($site['site_name']) ?> Portal</strong><span>Occidental Mindoro State College</span></span>
    </a>
    <button class="el-toggle" type="button" id="el-toggle" aria-expanded="false" aria-controls="el-nav" aria-label="Toggle navigation">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    <nav class="el-nav" id="el-nav" aria-label="Main navigation">
      <a class="el-link" href="#about">About</a>
      <a class="el-link" href="#services">Services</a>
      <a class="el-link" href="#requirements">Requirements</a>
      <a class="el-link" href="#announcements">Announcements</a>
      <a class="el-link" href="#partners">Partners</a>
      <?php if ($authUser): ?>
        <a class="el-btn el-btn-navy" href="<?= escape(url(dashboard_path($authUser['role']))) ?>">Dashboard</a>
        <form method="post" action="<?= escape(url('actions/logout.php')) ?>"><?= csrf_field() ?><button class="el-logout" type="submit">Sign out</button></form>
      <?php else: ?>
        <a class="el-btn el-btn-navy" href="<?= escape(url('login.php')) ?>">Sign in</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main id="main-content" tabindex="-1">

<section class="el-hero" aria-labelledby="hero-title">
  <img class="el-hero-img" src="<?= escape(url('assets/img/OMSC_thumbnail.jpg')) ?>" alt="" fetchpriority="high">
  <div class="el-wrap">
    <div class="el-hero-grid">
      <div class="el-anim">
        <div class="el-hero-kicker">External Linkages and International Affairs</div>
        <h1 id="hero-title">International partnerships and travel requests, handled in one portal.</h1>
        <p class="el-lede">Submit your request, upload the required documents, and follow every step of the ELIA Office review at Occidental Mindoro State College.</p>
        <div class="el-hero-actions">
          <?php if ($authUser): ?>
            <a class="el-btn el-btn-gold el-btn-lg" href="<?= escape(url(dashboard_path($authUser['role']))) ?>">Open my dashboard</a>
          <?php else: ?>
            <a class="el-btn el-btn-gold el-btn-lg" href="<?= escape(url('login.php')) ?>">Admin sign in</a>
            <a class="el-btn el-btn-glass el-btn-lg" href="<?= escape(url('login.php')) ?>">Client portal</a>
          <?php endif; ?>
        </div>
        <?php if (!$authUser): ?>
          <p class="el-hero-note">Client portal is for faculty, staff, and students. New here? <a href="<?= escape(url('register.php')) ?>">Create an account</a>.</p>
        <?php endif; ?>
      </div>

      <aside class="el-tracker" aria-label="Sample of a request being tracked">
        <div class="el-tracker-top"><span>Sample request</span><b>Illustration</b></div>
        <h2>Faculty exchange program</h2>
        <div class="el-ref">ELIA-2026-000123 &middot; Tokyo, Japan</div>
        <ol class="el-steps">
          <li class="done">Request submitted</li>
          <li class="done">Documents verified</li>
          <li class="now">Under ELIA review<small>You are notified of every update</small></li>
          <li>Approved</li>
          <li>Completed</li>
        </ol>
      </aside>
    </div>
  </div>
</section>

<div class="el-quick">
  <div class="el-wrap">
    <div class="el-quick-grid">
      <a href="<?= escape(url($authUser ? dashboard_path($authUser['role']) : 'register.php')) ?>"><strong>Submit a request</strong><span>Conference, Referendum/BOT, and other ELIA transactions.</span></a>
      <a href="#requirements"><strong>Check requirements</strong><span>See the documents needed before you file.</span></a>
      <a href="#announcements"><strong>Get forms and updates</strong><span>Download forms and read the latest announcements.</span></a>
    </div>
  </div>
</div>

<section class="el-section" id="about" aria-labelledby="about-title">
  <div class="el-wrap el-split">
    <div>
      <h2 class="el-h2" id="about-title">One office, one record of every international transaction</h2>
      <hr class="el-rule">
      <p class="el-text">The ELIA Office manages OMSC's institutional partnerships, coordinates international collaborations, and keeps records of MOU/MOA agreements, conferences, mobility programs, and travel documents.</p>
      <p class="el-text mb-0">This portal replaces scattered spreadsheets, paper files, and messages with one role-based system, so every request, requirement, and status is up to date and easy to find.</p>
    </div>
    <ol class="el-process" aria-label="How a request moves through the portal">
      <li><strong>Submit a request</strong><span>Sign in and file a conference, Referendum/BOT, or travel-related request.</span></li>
      <li><strong>Upload documents</strong><span>Attach the required letters, forms, and certificates to the request.</span></li>
      <li><strong>ELIA review</strong><span>ELIA personnel verify each requirement and update the status.</span></li>
      <li><strong>Track and get notified</strong><span>Receive a notification for every change until the transaction is complete.</span></li>
    </ol>
  </div>
</section>

<section class="el-section alt" id="services" aria-labelledby="services-title">
  <div class="el-wrap">
    <h2 class="el-h2" id="services-title">Built for two kinds of users</h2>
    <hr class="el-rule">
    <div class="el-roles">
      <div class="el-role">
        <h3>ELIA personnel (Admin)</h3>
        <p>Manage the full cycle of every transaction from one workspace.</p>
        <ul>
          <li>Keep all requests, documents, and records in one database</li>
          <li>Review conference/meeting and Referendum/BOT requests</li>
          <li>Verify documents and monitor pre-departure and post-travel requirements</li>
          <li>Maintain MOU/MOA and partner records</li>
          <li>Generate reports for monitoring and accreditation</li>
          <li>Manage user accounts and role-based access</li>
        </ul>
      </div>
      <div class="el-role client">
        <h3>Faculty, staff, and students (Client)</h3>
        <p>File and follow your requests without visiting the office for status checks.</p>
        <ul>
          <li>Submit conference, Referendum/BOT, and other ELIA requests</li>
          <li>Upload required documents and replace those marked for revision</li>
          <li>See the status of each request as it changes</li>
          <li>Read ELIA announcements and download forms</li>
          <li>Receive notifications when action is needed</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="el-section" id="requirements" aria-labelledby="req-title">
  <div class="el-wrap el-split">
    <div>
      <h2 class="el-h2" id="req-title">Know what to prepare before you file</h2>
      <hr class="el-rule">
      <p class="el-text">Open a transaction to see the documents the ELIA Office asks for. Requirements marked "if applicable" are needed only in some cases.</p>
      <p class="mt-4"><a class="el-btn el-btn-navy" href="<?= escape(url($authUser ? dashboard_path($authUser['role']) : 'register.php')) ?>"><?= $authUser ? 'Go to dashboard' : 'Create an account to file' ?></a></p>
    </div>
    <div class="el-acc">
      <?php foreach ($requirementTypes as $type): ?>
        <details>
          <summary><?= escape($type['name']) ?></summary>
          <div class="el-acc-body">
            <?php if ($type['description'] !== ''): ?><p class="el-text small"><?= escape($type['description']) ?></p><?php endif; ?>
            <ul>
              <?php foreach ($type['items'] as $item): ?>
                <li><?= escape($item['requirement_name']) ?><?php if (!$item['is_required']): ?><span class="el-tag">if applicable</span><?php endif; ?>
                  <?php if ((string) $item['description'] !== ''): ?><small><?= escape($item['description']) ?></small><?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </details>
      <?php endforeach; ?>
      <?php if (!$requirementTypes): ?>
        <p class="el-text mb-0">The ELIA Office is finalizing its requirement checklists. They will appear here once published.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="el-section alt" id="announcements" aria-labelledby="news-title">
  <div class="el-wrap">
    <h2 class="el-h2" id="news-title">Announcements and downloads</h2>
    <hr class="el-rule">
    <div class="el-news">
      <div>
        <?php if ($announcements): ?>
          <?php foreach ($announcements as $item): ?>
            <article class="el-feed-item">
              <div class="el-feed-date"><b><?= escape($categories[$item['category']] ?? 'Announcement') ?></b><?= escape(announcement_date($item['published_at'])) ?></div>
              <div>
                <h3><a href="<?= escape(url('announcements.php?id=' . (int) $item['id'])) ?>"><?= escape($item['title']) ?></a></h3>
                <p><?= escape(announcement_excerpt($item, 200)) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
          <p class="mt-3 mb-0"><a href="<?= escape(url('announcements.php')) ?>">View all announcements</a></p>
        <?php else: ?>
          <?php foreach ($fallbackHighlights as [$when, $title, $text]): ?>
            <article class="el-feed-item">
              <div class="el-feed-date"><b>Highlight</b><?= escape($when) ?></div>
              <div><h3><?= escape($title) ?></h3><p><?= escape($text) ?></p></div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="el-docs" id="downloads">
        <h3>Forms and documents</h3>
        <?php foreach ($resources as $item): ?>
          <a class="el-doc" href="<?= escape(url('actions/resources/download.php?id=' . (int) $item['id'])) ?>">
            <i><?= escape(resource_extension($item)) ?></i>
            <div><strong><?= escape($item['title']) ?></strong><span><?= escape(resource_size_label((int) $item['file_size'])) ?></span></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$resources): ?><p class="el-text">Forms and reports from the ELIA Office will be listed here.</p><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="el-partners" id="partners" aria-labelledby="partners-title">
  <div class="el-wrap">
    <h2 class="el-h2" id="partners-title">Partners across borders</h2>
    <hr class="el-rule">
    <p class="el-text">OMSC works with institutions abroad on student and faculty mobility, joint research, and academic cooperation, each documented by a signed MOU or MOA.</p>
    <?php if ($partnerStats['partners'] > 0): ?>
      <div class="el-stats">
        <div class="el-stat"><b><?= (int) $partnerStats['partners'] ?></b><span>Partner institutions with active agreements</span></div>
        <div class="el-stat"><b><?= (int) $partnerStats['countries'] ?></b><span>Countries</span></div>
        <div class="el-stat"><b><?= (int) $partnerStats['agreements'] ?></b><span>Active MOU/MOA</span></div>
      </div>
      <ul class="el-chips" aria-label="Partner institutions">
        <?php foreach ($partnerRows as $row): ?><li><?= escape($row['name']) ?><small><?= escape($row['country']) ?></small></li><?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="el-high">
        <?php foreach ($fallbackHighlights as [$when, $title, $text]): ?>
          <div><h3><?= escape($title) ?></h3><small><?= escape($when) ?></small><p><?= escape($text) ?></p></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

</main>

<footer class="el-footer">
  <div class="el-wrap">
    <div class="el-footer-grid">
      <div>
        <h2><?= escape($site['site_name']) ?> &mdash; <?= escape($site['office_name']) ?></h2>
        <p class="mb-0"><?= escape($site['campus']) ?><br>San Jose, Occidental Mindoro, Philippines</p>
      </div>
      <div>
        <h2>Quick links</h2>
        <ul>
          <li><a href="#about">About the office</a></li>
          <li><a href="#requirements">Requirements</a></li>
          <li><a href="<?= escape(url('announcements.php')) ?>">Announcements</a></li>
          <li><a href="<?= escape(url('login.php')) ?>">Sign in</a></li>
          <li><a href="<?= escape(url('register.php')) ?>">Create an account</a></li>
        </ul>
      </div>
      <div>
        <h2>Contact</h2>
        <ul>
          <li><a href="mailto:<?= escape($site['support_email']) ?>"><?= escape($site['support_email']) ?></a></li>
          <li><a href="tel:+63434570231"><?= escape($site['support_phone']) ?></a></li>
          <li><a href="https://omsc.edu.ph">omsc.edu.ph</a></li>
        </ul>
      </div>
    </div>
    <div class="el-copy">&copy; <?= date('Y') ?> <?= escape($site['office_name']) ?> Office, Occidental Mindoro State College.</div>
  </div>
</footer>

<script>
(function () {
  var toggle = document.getElementById('el-toggle'), nav = document.getElementById('el-nav');
  if (!toggle || !nav) { return; }
  toggle.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  nav.addEventListener('click', function (e) {
    if (e.target.closest('a.el-link')) { nav.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); }
  });
})();
</script>
</body>
</html>