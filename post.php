<?php
  include 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Post Announcement - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <nav class="nav">
        <a href="index.php">Home</a>
        <a href="user-consult.php">Announcements</a>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
        <a href="contact.php">Contact</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <a class="cta" href="post.php">Post Your Ad</a>
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <section class="post-head">
    <div class="container">
      <h1>Post your announcement</h1>
      <p class="muted">Create a clear ad to sell faster. Photos must be JPG and ≤ 100KB each.</p>
    </div>
  </section>

  <section class="container post-grid">
    <!-- Left: Form card -->
    <div class="post-card">
      <form id="post-form" class="simple-form">
        <div class="two">
          <input class="input" id="title" name="title" type="text" placeholder="Title (e.g., iPhone 12 like new)" required />
          <select class="input" id="category" name="category" required>
          <option value="" disabled selected>Select category</option>
          <option>Vehicles</option>
          <option>Real Estate</option>
          <option>Electronics</option>
          <option>Clothing</option>
          <option>Jobs</option>
          </select>
        </div>
        <div class="two">
          <input class="input" id="price" name="price" type="number" step="0.01" min="0" placeholder="Price" required />
          <select class="input" id="city" name="city" required>
          <option value="" disabled selected>Select city</option>
          <option>Agadir</option>
          <option>Ahfir</option>
          <option>Al Hoceima</option>
          <option>Arfoud</option>
          <option>Asilah</option>
          <option>Azemmour</option>
          <option>Azilal</option>
          <option>Beni Mellal</option>
          <option>Berkane</option>
          <option>Berrechid</option>
          <option>Bouarfa</option>
          <option>Boujdour</option>
          <option>Boulemane</option>
          <option>Bouskoura</option>
          <option>Casablanca</option>
          <option>Chefchaouen</option>
          <option>Chichaoua</option>
          <option>Dakhla</option>
          <option>Dar Bouazza</option>
          <option>Demnate</option>
          <option>El Hajeb</option>
          <option>El Jadida</option>
          <option>El Kelaa des Sraghna</option>
          <option>Errachidia</option>
          <option>Essaouira</option>
          <option>Fès</option>
          <option>Fnideq</option>
          <option>Fquih Ben Salah</option>
          <option>Guelmim</option>
          <option>Guercif</option>
          <option>Ifrane</option>
          <option>Imzouren</option>
          <option>Inzegane</option>
          <option>Jerada</option>
          <option>Kalaat Mgouna</option>
          <option>Kenitra</option>
          <option>Khemisset</option>
          <option>Khenifra</option>
          <option>Khouribga</option>
          <option>Ksar El Kebir</option>
          <option>Larache</option>
          <option>Laayoune</option>
          <option>Marrakech</option>
          <option>Martil</option>
          <option>Mediouna</option>
          <option>Mechra Bel Ksiri</option>
          <option>Meknès</option>
          <option>Midelt</option>
          <option>Mohammedia</option>
          <option>Nador</option>
          <option>Ouarzazate</option>
          <option>Ouezzane</option>
          <option>Oujda</option>
          <option>Oulad Teima</option>
          <option>Rabat</option>
          <option>Safi</option>
          <option>Salé</option>
          <option>Sefrou</option>
          <option>Settat</option>
          <option>Sidi Bennour</option>
          <option>Sidi Ifni</option>
          <option>Sidi Kacem</option>
          <option>Sidi Slimane</option>
          <option>Skhirat</option>
          <option>Smara</option>
          <option>Souk El Arbaa</option>
          <option>Tafraout</option>
          <option>Taliouine</option>
          <option>Tan-Tan</option>
          <option>Tanger</option>
          <option>Taounate</option>
          <option>Tarfaya</option>
          <option>Taroudant</option>
          <option>Tata</option>
          <option>Taza</option>
          <option>Témara</option>
          <option>Tétouan</option>
          <option>Tinghir</option>
          <option>Tiznit</option>
          <option>Youssoufia</option>
          <option>Zagora</option>
        </select>

        </div>
        <div class="two">
          <input class="input" id="phone" name="phone" type="tel" placeholder="Phone number (optional)" pattern="[\d\s\-\+\(\)]+" />
        </div>
        <textarea class="input" id="description" name="description" placeholder="Describe your item..." required></textarea>

        <div class="uploader">
          <div class="uploader-area">
            <div class="uploader-icon">📷</div>
            <div>
              <div class="card-title">Upload photos</div>
              <div class="card-meta">JPG only, ≤ 100KB each (up to 6)</div>
            </div>
          </div>
          <input id="photos" name="photos" type="file" accept="image/jpeg" multiple />
        </div>

        <div class="actions">
          <button class="btn" type="submit">Publish</button>
          <a class="btn ghost" href="user-consult.php">Cancel</a>
        </div>
      </form>
    </div>

    <!-- Right: Tips and preview -->
    <aside class="tips">
      <div class="tips-card">
        <div class="card-title">Posting tips</div>
        <ul>
          <li>Use a clear, specific title.</li>
          <li>Go Pro to upload more than one picture.</li>
          <li>Be honest and include key details.</li>
          <li>Set a fair, competitive price.</li>
        </ul>
      </div>
      <div class="preview-card">
        <div class="preview-image">Image preview</div>
        <div class="card-body">
          <div class="card-title">Your title will appear here</div>
          <div class="card-meta">City • Price</div>
        </div>
      </div>
    </aside>
  </section>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a href="contact.php">Contact</a></div>
    </div>
  </footer>
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
  <script src="js/post-preview.js"></script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>
