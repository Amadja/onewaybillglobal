<?php
require_once __DIR__ . '/config.php';

$siteAsset = static fn(string $file): string => siteAssetUrl($file);
$trackingApiUrl = appUrl('public/track-api.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Garamba Sky Cargo Ltd | Cargo &amp; Logistics</title>
<meta name="description" content="Garamba Sky Cargo Ltd - Cargo, freight forwarding, shipping and logistics from Uganda through Nairobi to Dubai and beyond.">
<link rel="stylesheet" href="<?= htmlspecialchars($siteAsset('style.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($siteAsset('warehouse-tracking.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($siteAsset('alert-severity.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<header class="header">
    <div class="container nav">
        <a class="logo" href="#home" aria-label="Garamba Sky Cargo Ltd home"><img src="<?= htmlspecialchars($siteAsset('logogaramba.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Garamba Sky Cargo Ltd"></a>
        <nav id="navMenu">
            <a href="#home">Home</a><a href="#services">Services</a><a href="#about">About</a><a href="#tracking">Track Shipment</a><a href="#contact">Contact</a>
        </nav>
        <a class="nav-cta" href="#tracking">Track Package &rarr;</a>
        <button class="menu-btn" id="menuBtn" type="button" aria-label="Open navigation">&#9776;</button>
    </div>
</header>

<section class="hero" id="home">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <div class="eyebrow">CARGO &bull; FREIGHT &bull; LOGISTICS</div>
        <h1>Moving your goods<br><span>across borders.</span></h1>
        <p>Reliable cargo and logistics solutions connecting Uganda, Nairobi, Dubai and international destinations.</p>
        <div class="hero-actions"><a class="btn primary" href="#tracking">Track Your Shipment</a><a class="btn ghost" href="#services">Explore Services</a></div>
        <div class="trust-row"><span>Secure handling</span><span>Shipment visibility</span><span>International routes</span></div>
    </div>
</section>

<section class="quick-track">
    <div class="container quick-inner">
        <div><span class="eyebrow">SHIPMENT TRACKING</span><h2>Where is your package?</h2></div>
        <form id="quickForm"><input id="quickInput" aria-label="Tracking number" placeholder="Enter tracking number"><button type="submit">Track Shipment &rarr;</button></form>
    </div>
</section>

<section class="section" id="services">
    <div class="container">
        <div class="section-head"><div><span class="eyebrow">OUR SERVICES</span><h2>Logistics built around<br>your shipment.</h2></div><p>From collection to final destination, we help move cargo with clear communication and practical logistics support.</p></div>
        <div class="services-grid">
            <article class="service"><span class="service-icon" aria-hidden="true">&#9992;</span><h3>Air Freight</h3><p>Fast international cargo solutions for shipments that need speed and reliable handling.</p><a href="#contact">Request a quote &rarr;</a></article>
            <article class="service"><span class="service-icon" aria-hidden="true">&#9635;</span><h3>Road Transport</h3><p>Land transportation and cross-border movement for cargo travelling within East Africa.</p><a href="#contact">Request a quote &rarr;</a></article>
            <article class="service"><span class="service-icon" aria-hidden="true">&#9875;</span><h3>Sea Freight</h3><p>Practical ocean freight coordination for larger or less time-sensitive international shipments.</p><a href="#contact">Request a quote &rarr;</a></article>
            <article class="service"><span class="service-icon" aria-hidden="true">&#9672;</span><h3>Freight Forwarding</h3><p>Coordination of shipment movement, documentation and logistics from origin to destination.</p><a href="#contact">Request a quote &rarr;</a></article>
            <article class="service"><span class="service-icon" aria-hidden="true">&#9638;</span><h3>Warehousing</h3><p>Storage, handling and inventory support while goods are waiting for their next movement.</p><a href="#contact">Request a quote &rarr;</a></article>
            <article class="service"><span class="service-icon" aria-hidden="true">&#10003;</span><h3>Package Delivery</h3><p>Package collection and delivery services with tracking updates throughout the journey.</p><a href="#contact">Request a quote &rarr;</a></article>
        </div>
    </div>
</section>

<section class="route-section" id="about">
    <div class="container route-grid">
        <div>
            <span class="eyebrow">OUR NETWORK</span>
            <h2>From Uganda to the world.</h2>
            <p>Garamba Sky Cargo Ltd is here to give customers a simple way to send, monitor and manage shipments across regional and international routes.</p>
            <div class="route-tags"><span>Uganda</span><span>Nairobi</span><span>Dubai</span><span>International</span></div>
        </div>
        <div class="route-map">
            <div class="map-line"><span></span></div>
            <div class="location"><b>Kampala</b><small>Origin</small></div>
            <div class="location"><b>Nairobi</b><small>Transit Hub</small></div>
            <div class="location"><b>Dubai</b><small>Destination</small></div>
        </div>
    </div>
</section>

<section class="partners-section" aria-labelledby="partners-title">
    <div class="container">
        <div class="partners-heading"><span class="eyebrow">OUR AIRLINE NETWORK</span><h2 id="partners-title">Working with trusted airline partners.</h2></div>
        <div class="partners-grid">
            <div class="partner-logo"><img src="<?= htmlspecialchars($siteAsset('kenya_airways_logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Kenya Airways"></div>
            <div class="partner-logo"><img src="<?= htmlspecialchars($siteAsset('EgyptAir_logo_PNG_(8).png'), ENT_QUOTES, 'UTF-8') ?>" alt="EgyptAir"></div>
            <div class="partner-logo"><img src="<?= htmlspecialchars($siteAsset('Qatar_Airways-Logo.wine.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Qatar Airways"></div>
            <div class="partner-logo"><img src="<?= htmlspecialchars($siteAsset('ethiopian_airline_logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Ethiopian Airlines"></div>
        </div>
    </div>
</section>

<section class="tracking-section" id="tracking">
    <div class="container">
        <div class="tracking-panel">
            <div class="tracking-copy"><span class="eyebrow">TRACK YOUR SHIPMENT</span><h2>Know where your<br>package is.</h2><p>Enter your Garamba Sky Cargo tracking number to view the latest shipment status and movement history.</p></div>
            <form id="trackingForm" class="tracking-form"><label for="trackingInput">Tracking Number</label><input id="trackingInput" required placeholder="Enter tracking number"><button class="btn primary" type="submit">Track Shipment &rarr;</button></form>
        </div>
        <div id="result" class="result hidden" aria-live="polite">
            <div id="error" class="error hidden" role="alert"></div>
            <div id="shipment" class="hidden">
                <div class="result-header"><div><span class="eyebrow">SHIPMENT DETAILS</span><h3 id="statusTitle"></h3></div><span id="badge" class="badge"></span></div>
                <div class="summary"><div><small>Tracking Number</small><b id="trackingOut"></b></div><div><small>Origin</small><b id="originOut"></b></div><div><small>Destination</small><b id="destinationOut"></b></div><div><small>Service</small><b id="serviceOut"></b></div></div>
                <div class="shipment-extras"><div class="package-info"><small>PACKAGE INFORMATION</small><b id="packageOut"></b><div id="customerDetails" class="customer-details hidden"></div><div id="storageDetails" class="storage-details hidden"></div><div id="carrierDetails" class="carrier-details hidden"><img src="<?= htmlspecialchars($siteAsset('kenyaairwayslogo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Kenya Airways Cargo logo"><div><small>OPERATED BY</small><b id="carrierOut"></b></div></div></div><div class="barcode-card"><svg id="barcode" role="img" aria-label="Shipment tracking barcode"></svg></div></div>
                <h4 class="history-title">Shipment history</h4><div id="timeline" class="timeline" aria-live="polite"></div>
            </div>
        </div>
    </div>
</section>

<section class="why section">
    <div class="container">
        <div class="section-head"><div><span class="eyebrow">WHY GARAMBA SKY CARGO</span><h2>Simple logistics.<br>Professional service.</h2></div></div>
        <div class="features"><div><b>01</b><h3>Clear communication</h3><p>Keep customers informed with shipment updates and a simple tracking system.</p></div><div><b>02</b><h3>Flexible solutions</h3><p>Air, road, sea, forwarding and storage options for different shipment needs.</p></div><div><b>03</b><h3>End-to-end visibility</h3><p>Follow your shipment through important checkpoints from origin to destination.</p></div></div>
    </div>
</section>

<section class="contact" id="contact">
    <div class="container contact-grid">
        <div><span class="eyebrow">CONTACT US</span><h2>Ready to move<br>your cargo?</h2><p>Contact Garamba Sky Cargo Ltd for shipment enquiries, quotations and logistics support.</p></div>
        <div class="contact-card"><div><small>EMAIL</small><a href="mailto:info@garambacargo.com">info@garambacargo.com</a></div><div><small>WEBSITE</small><a href="https://garambacargo.com">garambacargo.com</a></div><div><small>OFFICE LINE</small><a href="tel:+256200444231">0200 444 231</a></div><div><small>ADDRESS</small><span>Entebbe, Uganda<br>P.O. Box 52719</span></div><a class="btn primary full" href="mailto:info@garambacargo.com">Send an enquiry &rarr;</a></div>
    </div>
</section>

<footer><div class="container footer"><a class="logo" href="#home" aria-label="Garamba Sky Cargo Ltd home"><img src="<?= htmlspecialchars($siteAsset('logogaramba.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Garamba Sky Cargo Ltd"></a><p>&copy; <span id="year"></span> Garamba Sky Cargo Ltd. All rights reserved.</p><p>Cargo &bull; Freight &bull; Logistics</p></div></footer>
<script>window.TRACKING_API_URL = <?= json_encode($trackingApiUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= htmlspecialchars($siteAsset('JsBarcode.all.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($siteAsset('script.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
