<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Contact - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index-user.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <!-- REGISTERED USER NAVBAR: Home, Announcements, Contact, Logout -->
      <nav class="nav">
        <a href="index-user.php">Home</a>
        <a href="user-consult-user.php">Announcements</a>
        <a href="profile.php">Profile</a>
        <a class="active" href="contact-user.php">Contact</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <a class="cta" href="post-user.php">Post Your Ad</a>
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <section class="hero">
    <div class="hero-overlay"></div>
    <img class="hero-bg" src="https://images.unsplash.com/photo-1485217988980-11786ced9454?q=80&w=1600&auto=format&fit=crop" alt="Contact Hero" />
    <div class="container hero-inner">
      <div class="pill">We're here to help</div>
      <h1>Get in touch</h1>
      <p>Questions, feedback, or partnership ideas — our team responds fast.</p>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="#contact-form">Send a message</a>
        <a class="btn ghost" href="#contact-methods">See contact options</a>
      </div>
    </div>
  </section>

  <section id="contact-methods" class="container contact-grid">
    <article class="method">
      <div class="method-icon">@</div>
      <h3>Email</h3>
      <p class="muted">support@quickannonce.com</p>
      <a class="btn ghost" href="mailto:support@quickannonce.com">Email us</a>
    </article>
    <article class="method">
      <div class="method-icon">☎</div>
      <h3>Phone</h3>
      <p class="muted">+225 01 23 45 67</p>
      <a class="btn ghost" href="tel:+22501234567">Call now</a>
    </article>
    <article class="method">
      <div class="method-icon">💬</div>
      <h3>Live chat</h3>
      <p class="muted">Weekdays 09:00–18:00</p>
      <a class="btn ghost" href="#">Open chat</a>
    </article>
    <article class="method">
      <div class="method-icon">📍</div>
      <h3>Visit us</h3>
      <p class="muted">Plateau, Abidjan, CI</p>
      <a class="btn ghost" href="#map">View map</a>
    </article>
  </section>

  <section id="contact-form" class="container contact-simple">
    <div class="contact-card">
      <h2>Send a message</h2>
      <p class="muted">We typically respond within 24 hours.</p>
      <form class="simple-form">
        <div class="two">
          <input class="input" type="text" name="name" placeholder="Your name" required />
          <input class="input" type="email" name="email" placeholder="Email address" required />
        </div>
        <div class="two">
          <input class="input" type="text" name="subject" placeholder="Subject" required />
          <input class="input" type="text" name="phone" placeholder="Phone (optional)" />
        </div>
        <textarea class="input" name="message" placeholder="Write your message..." required></textarea>
        <div class="actions">
          <button class="btn" type="submit">Send message</button>
          <button class="btn ghost" type="reset">Clear</button>
        </div>
      </form>
    </div>
  </section>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a class="active" href="contact-user.php">Contact</a></div>
    </div>
  </footer>
  <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>
