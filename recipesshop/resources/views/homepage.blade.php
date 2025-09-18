<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FoodShop 🍏</title>
  <style>
    :root { --w: 900px; }
    body { margin:0; font-family: system-ui, Segoe UI, Roboto, Arial, sans-serif; color:#111; }
    header { border-bottom:1px solid #eee; background:#fff; position:sticky; top:0; }
    .wrap { max-width: var(--w); margin:0 auto; padding:16px; }
    .logo { font-weight:700; letter-spacing:.3px; }
    main { min-height: 60vh; display:flex; align-items:center; }
    .hero { padding:32px 0; }
    .hero h1 { margin:0 0 10px; font-size:28px; }
    .muted { color:#666; }
    a.primary { display:inline-block; margin-top:12px; padding:10px 14px; border-radius:10px; border:1px solid #111; background:#111; color:#fff; text-decoration:none; }
    footer { border-top:1px solid #eee; }
      .heart {
    color: #e11d48;           /* malinasto crvena */
    display: inline-block;
    transform-origin: center;
    animation: pulse 1.2s ease-in-out infinite;
    margin-left: 6px;
  }
  @keyframes pulse {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.15); }
  }
  .sr-only {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
  }
  </style>
</head>
<body>
  <header>
    <div class="wrap">
      <div class="logo" aria-label="FoodShop">FoodShop 🍏</div>
    </div>
  </header>

  <main>
    <section class="wrap hero" aria-labelledby="title">
      <h1 id="title">Dobro došli u FoodShop</h1>
      <p class="muted">
        Ovo je backend API za prodavnicu recepata i sastojaka.
        Sve mogućnosti možete pregledati i isprobati kroz ugrađenu API dokumentaciju.
      </p>
      <a class="primary" href="/api/documentation">Otvori Swagger dokumentaciju</a>
    </section>
  </main>

  <footer>
    <div class="wrap">
    <p class="muted">
      © 2025 FoodShop — Vanja i Jovana
      <span class="heart" aria-hidden="true">♥</span>
      <span class="sr-only">srce</span>
    </p>
  </div>
  </footer>
</body>
</html>